<?php

namespace App\Modules\Orders\Services;

use App\Models\Customer;
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
        protected OrderService $orderService
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
            $customer = $this->findOrCreateCustomer($orderData['customer'], $source);
            
            // Prepare order data
            $preparedData = [
                'document_type' => DocumentType::QUOTE,
                'quote_status' => QuoteStatus::DRAFT,
                'customer_id' => $customer->id,
                'external_order_id' => $orderData['order_id'],
                'external_source' => $source,
                'tax_inclusive' => $orderData['tax_inclusive'] ?? true,
                'currency' => $orderData['currency'] ?? 'USD',
                'shipping' => $orderData['shipping_cost'] ?? 0,
                'discount' => $orderData['discount_amount'] ?? 0,
                'vehicle_year' => $orderData['vehicle']['year'] ?? null,
                'vehicle_make' => $orderData['vehicle']['make'] ?? null,
                'vehicle_model' => $orderData['vehicle']['model'] ?? null,
                'vehicle_sub_model' => $orderData['vehicle']['sub_model'] ?? null,
                'order_notes' => "Synced from {$source} - Order ID: {$orderData['order_id']}",
                'items' => $this->prepareOrderItems($orderData['items'] ?? []),
            ];
            
            // Create order using OrderService
            $order = $this->orderService->createOrder($preparedData);
            
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
    protected function findOrCreateCustomer(array $customerData, string $source): Customer
    {
        // Try to find by email first
        if (isset($customerData['email'])) {
            $customer = Customer::where('email', $customerData['email'])->first();
            if ($customer) {
                return $customer;
            }
        }
        
        // Try to find by external customer ID
        if (isset($customerData['customer_id'])) {
            $externalIdField = $source === 'retail' ? 'retail_customer_id' : 'wholesale_customer_id';
            $customer = Customer::where($externalIdField, $customerData['customer_id'])->first();
            if ($customer) {
                return $customer;
            }
        }
        
        // Create new customer
        $customer = Customer::create([
            'name' => $customerData['name'] ?? $customerData['first_name'] . ' ' . $customerData['last_name'],
            'email' => $customerData['email'] ?? null,
            'phone' => $customerData['phone'] ?? null,
            'company' => $customerData['company'] ?? null,
            'customer_type' => $source === 'wholesale' ? 'dealer' : 'retail',
            'retail_customer_id' => $source === 'retail' ? ($customerData['customer_id'] ?? null) : null,
            'wholesale_customer_id' => $source === 'wholesale' ? ($customerData['customer_id'] ?? null) : null,
        ]);
        
        Log::info("Created new customer from external order", [
            'customer_id' => $customer->id,
            'source' => $source,
        ]);
        
        return $customer;
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
        
        foreach ($externalItems as $externalItem) {
            $item = [
                'quantity' => $externalItem['quantity'] ?? 1,
                'unit_price' => $externalItem['price'] ?? 0,
                'discount' => $externalItem['discount'] ?? 0,
            ];
            
            // Map external SKU to our product/variant
            if (isset($externalItem['sku'])) {
                $mapping = $this->mapSkuToProduct($externalItem['sku']);
                if ($mapping) {
                    $item = array_merge($item, $mapping);
                }
            }
            
            // If no mapping found, add as custom item
            if (!isset($item['product_id']) && !isset($item['product_variant_id']) && !isset($item['add_on_id'])) {
                // Skip for now - we'll handle custom items later
                Log::warning("Could not map external item", ['sku' => $externalItem['sku'] ?? null]);
                continue;
            }
            
            $items[] = $item;
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
        $addon = \App\Modules\AddOns\Models\Addon::where('part_number', $sku)->first();
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
        if ($order->quote_status !== QuoteStatus::DRAFT) {
            Log::warning("Skipping update - order not in draft status", [
                'order_id' => $order->id,
                'status' => $order->quote_status->value,
            ]);
            return $order;
        }
        
        // Update order fields
        $order->update([
            'shipping' => $orderData['shipping_cost'] ?? $order->shipping,
            'discount' => $orderData['discount_amount'] ?? $order->discount,
            'order_notes' => $order->order_notes . "\n\nUpdated from external system at " . now(),
        ]);
        
        // Recalculate totals
        $this->orderService->calculateTotals($order);
        
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
}
