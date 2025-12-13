<?php

namespace App\Modules\Orders\Services;

use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Enums\DocumentType;
use App\Modules\Orders\Enums\QuoteStatus;
use App\Modules\Orders\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * OrderSyncService
 * 
 * Handles synchronization of orders from external systems:
 * - TunerStop (retail platform)
 * - Wholesale platform
 * 
 * External orders are imported as QUOTES in DRAFT status
 */
class OrderSyncService
{
    public function __construct(
        protected OrderService $orderService,
        protected \App\Services\AddonSyncService $addonSyncService,
        protected \App\Services\BrandLookupService $brandService,
        protected \App\Services\ModelLookupService $modelService,
        protected \App\Services\FinishLookupService $finishService,
        protected \App\Services\OrderProductSyncService $productSyncService
    ) {}

    /**
     * Sync an order from external system
     * 
     * @param array $orderData External order data
     * @param string $source 'retail' or 'wholesale'
     * @return Order
     */
    public function syncFromExternal(array $orderData, string $source): Order
    {
        return DB::transaction(function () use ($orderData, $source) {
            
            Log::info("Syncing external order from {$source}", [
                'external_order_id' => $orderData['order_id'] ?? null,
            ]);
            
            // Check if order already exists
            $existingOrder = Order::where('external_order_id', $orderData['order_id'])
                ->where('external_source', $source)
                ->first();
            
            if ($existingOrder) {
                Log::info("Order already exists, updating", ['order_id' => $existingOrder->id]);
                return $this->updateExternalOrder($existingOrder, $orderData);
            }
            
            // Find or create customer
            $customer = $this->findOrCreateCustomer($orderData['customer'], $source, $orderData['addresses'] ?? []);
            
            // Prepare order data
            $preparedData = [
                'document_type' => DocumentType::QUOTE,
                'quote_status' => QuoteStatus::SENT,  // Changed from DRAFT to SENT so approve/reject buttons show
                'customer_id' => $customer->id,
                'external_order_id' => $orderData['order_id'],
                'external_source' => $source,
                'tax_inclusive' => $orderData['tax_inclusive'] ?? true,
                'currency' => $orderData['currency'] ?? 'USD',
                'channel' => $orderData['channel'] ?? $this->getDefaultChannel(),  // Default to Retail channel
                
                // Financial fields
                'sub_total' => $orderData['sub_total'] ?? 0,
                'tax' => $orderData['tax'] ?? 0,  // Changed from tax_amount
                'shipping' => $orderData['shipping_cost'] ?? $orderData['shipping'] ?? 0,
                'discount' => $orderData['discount_amount'] ?? $orderData['discount'] ?? 0,
                'total' => $orderData['order_total'] ?? $orderData['total'] ?? 0,
                
                
                // Payment information
                'payment_status' => strtolower($orderData['payment_status'] ?? 'pending'),
                'payment_method' => $orderData['payment_method'] ?? null,
                'paid_amount' => $orderData['paid_amount'] ?? 0,
                'outstanding_amount' => $orderData['outstanding_amount'] ?? 0,
                
                // Order metadata
                'order_status' => $orderData['order_status'] ?? 'processing',
                'order_number' => $orderData['order_number'] ?? null,  // Changed from external_order_number
                
                // Vehicle info
                'vehicle_year' => $orderData['vehicle_year'] ?? $orderData['vehicle']['year'] ?? null,
                'vehicle_make' => $orderData['vehicle_make'] ?? $orderData['vehicle']['make'] ?? null,
                'vehicle_model' => $orderData['vehicle_model'] ?? $orderData['vehicle']['model'] ?? null,
                'vehicle_sub_model' => $orderData['vehicle_sub_model'] ?? $orderData['vehicle']['sub_model'] ?? null,
                
                // Notes
                'order_notes' => $orderData['customer_notes'] ?? $orderData['order_notes'] ?? null, // Fallback to null if empty
                'internal_notes' => $orderData['internal_notes'] ?? null,
                
                // Items
                'items' => $this->prepareOrderItems($orderData['items'] ?? []),
            ];
            
            // Force paid status if amount covers total (robustness check)
            if (($preparedData['paid_amount'] ?? 0) >= ($preparedData['total'] ?? 0) && ($preparedData['total'] ?? 0) > 0) {
                 $preparedData['payment_status'] = 'paid';
            }
            
            // Create order using OrderService
            $order = $this->orderService->createOrder($preparedData);
            
            // FORCE UPDATE TOTALS: createOrder recalculates totals based on items, 
            // which might be 0 or different from external source. We trust external source.
            // ALSO: Auto-approve synced orders since they're already paid/confirmed from Tunerstop
            $order->update([
                'sub_total' => $preparedData['sub_total'],
                'tax' => $preparedData['tax'],
                'vat' => $preparedData['tax'], // VAT = Tax for display purposes
                'total' => $preparedData['total'],
                'quote_status' => QuoteStatus::APPROVED, // Auto-approve for Convert to Invoice
                'approved_at' => now(),
            ]);
            
            Log::info("External order synced successfully", [
                'order_id' => $order->id,
                'quote_number' => $order->quote_number,
                'external_order_id' => $order->external_order_id,
            ]);
            
            return $order;
        });
    }

    /**
     * Find or create customer from external data
     * 
     * @param array $customerData
     * @param string $source
     * @return Customer
     */
    protected function findOrCreateCustomer(array $customerData, string $source, array $addresses = []): Customer
    {
        // Log customer data for debugging
        Log::info('OrderSyncService: Finding/Creating customer', [
            'first_name' => $customerData['first_name'] ?? null,
            'last_name' => $customerData['last_name'] ?? null,
            'email' => $customerData['email'] ?? null,
            'source' => $source
        ]);

        // Try to find by email first
        if (isset($customerData['email'])) {
            $customer = Customer::where('email', $customerData['email'])->first();
            if ($customer) {
                // Update customer details if changed
                $customer->update([
                    'first_name' => $customerData['first_name'] ?? $customer->first_name,
                    'last_name' => $customerData['last_name'] ?? $customer->last_name,
                    'phone' => $customerData['phone'] ?? $customer->phone,
                ]);
                
                // Force update name if it's empty or "Unknown Customer"
                if ($customer->name === 'Unknown Customer' && !empty($customerData['first_name'])) {
                     Log::info('OrderSyncService: Customer updated', ['name' => $customer->name]);
                }
                
                // Sync addresses for existing customer too
                if (!empty($addresses)) {
                    $this->syncCustomerAddresses($customer, $addresses);
                }
                return $customer;
            }
        }
        
        // Try to find by external customer ID
        if (isset($customerData['customer_id'])) {
            $externalIdField = $source === 'retail' ? 'retail_customer_id' : 'wholesale_customer_id';
            $customer = Customer::where($externalIdField, $customerData['customer_id'])->first();
            if ($customer) {
                // Update customer details if changed
                $customer->update([
                    'first_name' => $customerData['first_name'] ?? $customer->first_name,
                    'last_name' => $customerData['last_name'] ?? $customer->last_name,
                    'phone' => $customerData['phone'] ?? $customer->phone,
                    'email' => $customerData['email'] ?? $customer->email,
                ]);

                // Sync addresses for existing customer too
                if (!empty($addresses)) {
                    $this->syncCustomerAddresses($customer, $addresses);
                }
                return $customer;
            }
        }
        
        // Create new customer
        $customer = Customer::create([
            'customer_type' => 'retail', // Default to retail
            'first_name' => $customerData['first_name'] ?? null,
            'last_name' => $customerData['last_name'] ?? null,
            'email' => $customerData['email'] ?? null,
            'phone' => $customerData['phone'] ?? null,
            'external_source' => $source,
            'external_customer_id' => $customerData['id'] ?? null,
            'status' => 'active'
        ]);
        
        Log::info('OrderSyncService: Created customer', [
            'id' => $customer->id, 
            'first_name' => $customer->first_name,
            'last_name' => $customer->last_name
        ]);

        
        // Sync customer addresses if provided
        if (!empty($addresses)) {
            $this->syncCustomerAddresses($customer, $addresses);
        }
        
        return $customer;
    }
    
    /**
     * Sync customer addresses from external data
     * 
     * @param Customer $customer
     * @param array $addresses
     * @return void
     */
    protected function syncCustomerAddresses(Customer $customer, array $addresses): void
    {
        // Sync billing address
        if (isset($addresses['billing'])) {
            $billingData = $addresses['billing'];
            $customer->addresses()->updateOrCreate(
                ['address_type' => 1], // 1 = Billing
                [
                    'nickname' => 'Billing Address',
                    'address' => $billingData['address'] ?? '',
                    'address2' => $billingData['address2'] ?? null,
                    'city' => $billingData['city'] ?? '',
                    'state' => $billingData['state'] ?? '',
                    'zip' => $billingData['postal_code'] ?? '',
                    'zip_code' => $billingData['postal_code'] ?? '',
                    'country' => $billingData['country'] ?? 'US',
                    'phone_no' => $billingData['phone'] ?? $customer->phone,
                    'email' => $billingData['email'] ?? $customer->email,
                ]
            );
        }
        
        // Sync shipping address
        if (isset($addresses['shipping'])) {
            $shippingData = $addresses['shipping'];
            $customer->addresses()->updateOrCreate(
                ['address_type' => 2], // 2 = Shipping
                [
                    'nickname' => 'Shipping Address',
                    'address' => $shippingData['address'] ?? '',
                    'address2' => $shippingData['address2'] ?? null,
                    'city' => $shippingData['city'] ?? '',
                    'state' => $shippingData['state'] ?? '',
                    'zip' => $shippingData['postal_code'] ?? '',
                    'zip_code' => $shippingData['postal_code'] ?? '',
                    'country' => $shippingData['country'] ?? 'US',
                    'phone_no' => $shippingData['phone'] ?? $customer->phone,
                    'email' => $shippingData['email'] ?? $customer->email,
                ]
            );
        }
        
        Log::info("Customer addresses synced", [
            'customer_id' => $customer->id,
            'billing' => isset($addresses['billing']),
            'shipping' => isset($addresses['shipping']),
        ]);
    }

    /**
     * Prepare order items for OrderService
     * 
     * @param array $externalItems
     * @return array
     */
    protected function prepareOrderItems(array $externalItems): array
    {
        $items = [];
        
        Log::info('OrderSyncService: Preparing items', ['count' => count($externalItems)]);
        
        foreach ($externalItems as $externalItem) {
            $item = [
                'quantity' => $externalItem['quantity'] ?? 1,
                'unit_price' => $externalItem['unit_price'] ?? $externalItem['price'] ?? 0,
                'discount' => $externalItem['discount'] ?? 0,
                'tax_inclusive' => $externalItem['tax_inclusive'] ?? true,
                'warehouse_id' => $externalItem['warehouse_id'] ?? $this->getDefaultWarehouseId(),  // Default to Non-Stock
                
                // Denormalized fields from external data
                'sku' => $externalItem['sku'] ?? null,
                'product_name' => $externalItem['product_name'] ?? null,
                'brand_name' => $externalItem['brand_name'] ?? $externalItem['brand'] ?? null,
                'model_name' => $externalItem['model_name'] ?? $externalItem['model'] ?? null,
                'item_attributes' => [
                    'size' => $externalItem['size'] ?? null,
                    'bolt_pattern' => $externalItem['bolt_pattern'] ?? null,
                    'offset' => $externalItem['offset'] ?? null,
                    'type' => $externalItem['type'] ?? null,
                    'finish_name' => $externalItem['finish_name'] ?? $externalItem['finish'] ?? null,
                ],
            ];
            
            // CASE 1: Item is an ADDON (has addon_data)
            if (isset($externalItem['addon_data'])) {
                try {
                    // Sync the addon (and its category) on the fly
                    $addon = $this->addonSyncService->syncAddon($externalItem['addon_data']);
                    
                    // Set addon foreign key
                    $item['add_on_id'] = $addon->id;
                    
                    // Create addon snapshot (store current addon state)
                    $item['addon_snapshot'] = [
                        'id' => $addon->id,
                        'name' => $addon->title,
                        'part_number' => $addon->part_number,
                        'description' => $addon->description,
                        'price' => $addon->price,
                        'category_id' => $addon->category_id,
                        'category_name' => $addon->category->name ?? null,
                    ];
                    
                    // Override denormalized fields with addon data if not set
                    $item['product_name'] = $item['product_name'] ?? $addon->title;
                    $item['sku'] = $item['sku'] ?? $addon->part_number;
                    
                    Log::info('OrderSyncService: Addon item prepared', [
                        'addon_id' => $addon->id,
                        'name' => $addon->name
                    ]);
                    
                } catch (\Exception $e) {
                    Log::error("Failed to sync embedded addon for order item", [
                        'sku' => $externalItem['sku'] ?? null,
                        'error' => $e->getMessage()
                    ]);
                    continue; // Skip this item
                }
            }
            // CASE 2: Item is a PRODUCT (has product_id, external_product_id, or product_name)
            else if (isset($externalItem['product_id']) || isset($externalItem['external_product_id']) || isset($externalItem['product_name'])) {
                try {
                    // NEW: Use OrderProductSyncService to find or create Product and Variant with FK linking
                    $result = $this->productSyncService->findOrCreateFromOrderItem($externalItem);
                    $product = $result['product'];
                    $variant = $result['variant'];
                    
                    // Set FOREIGN KEYS (the key improvement!)
                    $item['product_id'] = $product->id;
                    $item['product_variant_id'] = $variant?->id;
                    
                    // Create product snapshot (for historical record)
                    $item['product_snapshot'] = [
                        'id' => $product->id,
                        'external_product_id' => $externalItem['product_id'] ?? $externalItem['external_product_id'] ?? null,
                        'external_variant_id' => $externalItem['variant_id'] ?? $externalItem['external_variant_id'] ?? null,
                        'external_source' => $externalItem['external_source'] ?? 'tunerstop',
                        'name' => $product->name,
                        'sku' => $product->sku,
                        'brand_id' => $product->brand_id,
                        'brand_name' => $externalItem['brand_name'] ?? null,
                        'model_id' => $product->model_id,
                        'model_name' => $externalItem['model_name'] ?? null,
                        'variant_id' => $variant?->id,
                        'variant_title' => $externalItem['variant_title'] ?? null,
                    ];
                    
                    Log::info('OrderSyncService: Product item with FK linked', [
                        'product_id' => $product->id,
                        'variant_id' => $variant?->id,
                        'sku' => $product->sku
                    ]);
                    
                } catch (\Exception $e) {
                    Log::error("Failed to sync product for order item", [
                        'sku' => $externalItem['sku'] ?? null,
                        'error' => $e->getMessage()
                    ]);
                    
                    // Fallback: Create snapshot without FK
                    $item['product_snapshot'] = [
                        'external_product_id' => $externalItem['product_id'] ?? $externalItem['external_product_id'] ?? null,
                        'external_variant_id' => $externalItem['variant_id'] ?? $externalItem['external_variant_id'] ?? null,
                        'external_source' => $externalItem['external_source'] ?? 'tunerstop',
                        'name' => $externalItem['product_name'],
                        'sku' => $externalItem['sku'],
                        'brand_name' => $externalItem['brand_name'] ?? null,
                        'model_name' => $externalItem['model_name'] ?? null,
                        'variant_title' => $externalItem['variant_title'] ?? null,
                    ];
                }
            }
            // CASE 3: Unknown item type
            else {
                Log::warning("Could not determine item type", [
                    'item_data' => $externalItem
                ]);
                continue; // Skip unknown items
            }
            
            $items[] = $item;
            Log::info('OrderSyncService: Item added', ['item' => $item]);
        }
        
        return $items;
    }

    /**
     * Map external SKU to our product/variant/addon
     * 
     * @param string $sku
     * @return array|null
     */
    protected function mapSkuToProduct(string $sku): ?array
    {
        // Try to find variant by SKU
        $variant = \App\Modules\Products\Models\ProductVariant::where('sku', $sku)->first();
        if ($variant) {
            return ['product_variant_id' => $variant->id];
        }
        
        // Try to find addon by part number
        $addon = \App\Modules\Products\Models\AddOn::where('part_number', $sku)->first();
        if ($addon) {
            return ['add_on_id' => $addon->id];
        }
        
        // Try to find product by SKU
        $product = \App\Modules\Products\Models\Product::where('sku', $sku)->first();
        if ($product) {
            return ['product_id' => $product->id];
        }
        
        return null;
    }

    /**
     * Update existing external order
     * 
     * @param Order $order
     * @param array $orderData
     * @return Order
     */
    protected function updateExternalOrder(Order $order, array $orderData): Order
    {
        // Only update if still in draft status
        if ($order->quote_status && $order->quote_status !== QuoteStatus::DRAFT) {
            Log::warning("Skipping update - order not in draft status", [
                'order_id' => $order->id,
                'status' => $order->quote_status->value,
            ]);
            return $order;
        }
        
        Log::info("Updating external order {$order->id}", [
            'received_data' => array_intersect_key($orderData, array_flip(['sub_total', 'tax', 'order_total', 'items'])),
            'current_sub_total' => $order->sub_total,
        ]);
        
        // Update order fields
        $updateData = [
            // Financial fields
            'sub_total' => $orderData['sub_total'] ?? $order->sub_total,
            'tax' => $orderData['tax'] ?? $order->tax,
            'vat' => $orderData['tax'] ?? $order->vat, // VAT = Tax for display
            'shipping' => $orderData['shipping_cost'] ?? $orderData['shipping'] ?? $order->shipping,
            'discount' => $orderData['discount_amount'] ?? $orderData['discount'] ?? $order->discount,
            'total' => $orderData['order_total'] ?? $orderData['total'] ?? $order->total,
            
            // Payment information  
            'payment_status' => $orderData['payment_status'] ?? $order->payment_status,
            'payment_method' => $orderData['payment_method'] ?? $order->payment_method,
            'payment_gateway' => $orderData['payment_gateway'] ?? $order->payment_gateway,
            
            // Other fields
            'paid_amount' => $orderData['paid_amount'] ?? $order->paid_amount,
            'outstanding_amount' => $orderData['outstanding_amount'] ?? $order->outstanding_amount,
            
            // Notes
            'order_notes' => $orderData['customer_notes'] ?? $orderData['order_notes'] ?? $order->order_notes,
            'internal_notes' => $orderData['internal_notes'] ?? $order->internal_notes,
            
            // Vehicle info
            'vehicle_year' => $orderData['vehicle_year'] ?? $order->vehicle_year,
            'vehicle_make' => $orderData['vehicle_make'] ?? $order->vehicle_make,
            'vehicle_model' => $orderData['vehicle_model'] ?? $order->vehicle_model,
            'vehicle_sub_model' => $orderData['vehicle_sub_model'] ?? $order->vehicle_sub_model,
        ];

        Log::info("Order update payload", $updateData);

        $order->update($updateData);
        
        // Update items if provided
        if (isset($orderData['items']) && is_array($orderData['items'])) {
            // Delete existing items
            $order->items()->delete();
            
            // Add new items
            $preparedItems = $this->prepareOrderItems($orderData['items']);
            foreach ($preparedItems as $itemData) {
                $this->orderService->addItem($order, $itemData);
            }
            
            Log::info("Order items updated", [
                'order_id' => $order->id,
                'items_count' => count($preparedItems)
            ]);
        }
        
        // Recalculate totals
        // Recalculate totals - SKIPPED for external sync to trust source values
        // $this->orderService->calculateTotals($order);
        
        return $order->fresh();
    }

    /**
     * Sync order status back to external system
     * (To be implemented when external API is available)
     * 
     * @param Order $order
     * @return bool
     */
    public function syncStatusToExternal(Order $order): bool
    {
        if (!$order->external_order_id || !$order->external_source) {
            return false;
        }
        
        Log::info("Syncing order status to external system", [
            'order_id' => $order->id,
            'external_order_id' => $order->external_order_id,
            'source' => $order->external_source,
            'status' => $order->order_status?->value,
        ]);
        
        // TODO: Implement actual API calls to TunerStop/Wholesale
        // For now, just log it
        
        return true;
    }

    /**
     * Batch sync multiple orders
     * 
     * @param array $ordersData
     * @param string $source
     * @return array ['synced' => array, 'failed' => array]
     */
    public function batchSync(array $ordersData, string $source): array
    {
        $synced = [];
        $failed = [];
        
        foreach ($ordersData as $orderData) {
            try {
                $order = $this->syncFromExternal($orderData, $source);
                $synced[] = [
                    'order_id' => $order->id,
                    'quote_number' => $order->quote_number,
                    'external_order_id' => $order->external_order_id,
                ];
            } catch (\Exception $e) {
                $failed[] = [
                    'external_order_id' => $orderData['order_id'] ?? null,
                    'error' => $e->getMessage(),
                ];
                
                Log::error("Failed to sync external order", [
                    'external_order_id' => $orderData['order_id'] ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        return [
            'synced' => $synced,
            'failed' => $failed,
        ];
    }

    /**
     * Get default channel (Retail)
     */
    protected function getDefaultChannel(): string
    {
        return 'Retail';
    }

    /**
     * Get default warehouse ID (Non-Stock)
     */
    protected function getDefaultWarehouseId(): ?int
    {
        // Find "Non-Stock" warehouse or return null
        // Warehouse table uses 'warehouse_name' column, not 'name'
        $warehouse = \App\Modules\Inventory\Models\Warehouse::where('warehouse_name', 'Non-Stock')->first();
        return $warehouse?->id;
    }
}
