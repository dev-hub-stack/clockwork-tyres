<?php

namespace App\Modules\Orders\Services;

use App\Modules\Customers\Models\Customer;
use App\Modules\Products\Models\AddOn;
use App\Modules\Orders\Enums\DocumentType;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Enums\QuoteStatus;
use App\Modules\Orders\Events\OrderCreated;
use App\Modules\Orders\Events\OrderStatusChanged;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductVariant;
use App\Modules\Customers\Services\DealerPricingService;
use App\Services\AddonSnapshotService;
use App\Services\ProductSnapshotService;
use App\Services\VariantSnapshotService;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(
        protected ProductSnapshotService $productSnapshotService,
        protected VariantSnapshotService $variantSnapshotService,
        protected DealerPricingService $dealerPricingService,
    ) {}

    /**
     * Create a new order/quote/invoice
     * 
     * @param array $data Order data
     * @return Order
     */
    public function createOrder(array $data): Order
    {
        return DB::transaction(function () use ($data) {
            $data = $this->normalizeVehicleData($data);

            $documentType = $data['document_type'] ?? DocumentType::QUOTE;

            if (is_string($documentType)) {
                $documentType = DocumentType::from($documentType);
            }

            // Create the order
            $order = Order::create([
                'document_type' => $documentType,
                'customer_id' => $data['customer_id'],
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'representative_id' => $data['representative_id'] ?? null,
                'external_order_id' => $data['external_order_id'] ?? null,
                'external_source' => $data['external_source'] ?? null,
                'tax_inclusive' => $data['tax_inclusive'] ?? true,
                'currency' => $data['currency'] ?? 'USD',
                'channel' => $data['channel'] ?? null, // Added channel field
                'shipping' => $data['shipping'] ?? 0,
                'discount' => $data['discount'] ?? 0,
                'vehicle_year' => $data['vehicle_year'] ?? null,
                'vehicle_make' => $data['vehicle_make'] ?? null,
                'vehicle_model' => $data['vehicle_model'] ?? null,
                'vehicle_sub_model' => $data['vehicle_sub_model'] ?? null,
                'order_notes' => $data['order_notes'] ?? null,
                'issue_date' => $data['issue_date'] ?? now(),
                'valid_until' => $data['valid_until'] ?? now()->addDays(30),
            ]);

            // Add order items if provided
            if (isset($data['items']) && is_array($data['items'])) {
                foreach ($data['items'] as $itemData) {
                    $this->addItem($order, $itemData);
                }
            }

            // Calculate totals
            $this->calculateTotals($order);

            // Fire event
            event(new OrderCreated($order));

            return $order->fresh(['items', 'customer', 'warehouse']);
        });
    }

    public function normalizeVehicleData(array $data): array
    {
        $data['vehicle_year'] = $this->normalizeVehicleYear($data['vehicle_year'] ?? null);
        $data['vehicle_make'] = $this->normalizeVehicleText($data['vehicle_make'] ?? null);
        $data['vehicle_model'] = $this->normalizeVehicleText($data['vehicle_model'] ?? null);
        $data['vehicle_sub_model'] = $this->normalizeVehicleText($data['vehicle_sub_model'] ?? null);

        return $data;
    }

    protected function normalizeVehicleYear(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/\b(\d{4})\b/', $value, $matches)) {
            return $matches[1];
        }

        return strlen($value) <= 4 ? $value : substr($value, 0, 4);
    }

    protected function normalizeVehicleText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * Add an item to an order
     * 
     * @param Order $order
     * @param array $itemData
     * @return OrderItem
     */
    public function addItem(Order $order, array $itemData): OrderItem
    {
        $item = new OrderItem();
        $item->order_id = $order->id;
        $item->quantity = $itemData['quantity'] ?? 1;
        $item->tax_inclusive = $itemData['tax_inclusive'] ?? $order->tax_inclusive;
        $item->discount = $itemData['discount'] ?? 0;
        $item->warehouse_id = $itemData['warehouse_id'] ?? null;
        $item->item_attributes = $itemData['item_attributes'] ?? null;

        // Determine if this is a product, variant, or addon
        if (isset($itemData['product_variant_id'])) {
            // Product Variant
            $variant = ProductVariant::with(['product', 'product.brand', 'product.model'])->findOrFail($itemData['product_variant_id']);
            
            $item->product_id = $variant->product_id;
            $item->product_variant_id = $variant->id;
            
            // Create snapshots
            $item->product_snapshot = $this->productSnapshotService->createSnapshot($variant->product);
            $item->variant_snapshot = $this->variantSnapshotService->createSnapshot($variant);
            
            // Denormalized fields
            $item->sku = $variant->sku;
            $item->product_name = $variant->product->name;
            $item->product_description = $variant->product->description;
            $item->brand_name = $variant->product->brand?->name;
            $item->model_name = $variant->product->model?->name;
            
            // Determine base price
            $basePrice = $itemData['unit_price'] ?? $variant->price ?? $variant->product->retail_price;

            // Only apply dealer pricing when unit_price was NOT explicitly provided (e.g. from cart).
            // If unit_price is explicitly passed, trust it — cart already applied correct pricing.
            if (!isset($itemData['unit_price']) && $order->customer_id && $basePrice) {
                $customer = Customer::find($order->customer_id);
                $pricingResult = $this->dealerPricingService->calculateProductPrice(
                    $customer,
                    $basePrice,
                    $variant->product->model_id,
                    $variant->product->brand_id
                );
                $item->unit_price = $pricingResult['final_price'];
            } else {
                $item->unit_price = $basePrice;
            }
            
        } elseif (isset($itemData['product_id'])) {
            // Product only (no specific variant)
            $product = Product::with(['brand', 'model'])->findOrFail($itemData['product_id']);
            
            $item->product_id = $product->id;
            
            // Create snapshot
            $item->product_snapshot = $this->productSnapshotService->createSnapshot($product);
            
            // Denormalized fields
            $item->sku = $product->sku;
            $item->product_name = $product->name;
            $item->product_description = $product->description;
            $item->brand_name = $product->brand?->name;
            $item->model_name = $product->model?->name;
            
            $item->unit_price = $itemData['unit_price'] ?? $product->retail_price;
            
        } elseif (isset($itemData['add_on_id'])) {
            // Addon
            $addon = AddOn::with(['category'])->findOrFail($itemData['add_on_id']);
            
            $item->add_on_id = $addon->id;
            
            // Create snapshot using AddonSnapshotService
            $item->addon_snapshot = AddonSnapshotService::createSnapshot(
                $addon, 
                $order->customer_id, 
                $item->quantity
            );
            
            // Denormalized fields
            $item->sku = $addon->part_number;
            $item->product_name = $addon->title;
            $item->product_description = $addon->description;

            if (isset($itemData['unit_price'])) {
                $item->unit_price = $itemData['unit_price'];
            } else {
                $customer = $order->customer_id ? Customer::find($order->customer_id) : null;
                $item->unit_price = $addon->resolvePriceForCustomer($customer);
            }
        } else {
            // External sync items: Use denormalized data from itemData
            // These items have external IDs stored in snapshots but no local FKs yet
            $item->sku = $itemData['sku'] ?? null;
            $item->product_name = $itemData['product_name'] ?? 'Unknown Product';
            $item->product_description = $itemData['product_description'] ?? null;
            $item->brand_name = $itemData['brand_name'] ?? null;
            $item->model_name = $itemData['model_name'] ?? null;
            $item->unit_price = $itemData['unit_price'] ?? 0;
            
            // Store snapshots if provided
            if (isset($itemData['product_snapshot'])) {
                $item->product_snapshot = $itemData['product_snapshot'];
            }
            if (isset($itemData['variant_snapshot'])) {
                $item->variant_snapshot = $itemData['variant_snapshot'];
            }
            if (isset($itemData['addon_snapshot'])) {
                $item->addon_snapshot = $itemData['addon_snapshot'];
            }
        }

        // Calculate line total
        $item->line_total = $item->calculateLineTotal();
        
        $item->save();

        return $item;
    }

    /**
     * Remove an item from an order
     * 
     * @param OrderItem $item
     * @return bool
     */
    public function removeItem(OrderItem $item): bool
    {
        $order = $item->order;
        $item->delete();
        
        // Recalculate order totals
        $this->calculateTotals($order);
        
        return true;
    }

    /**
     * Calculate order totals based on line items
     * 
     * @param Order $order
     * @return void
     */
    public function calculateTotals(Order $order): void
    {
        $order->load('items');
        
        // Sum all line item totals
        $subTotal = $order->items->sum('line_total');
        
        $taxAmount = 0;
        $vatAmount = 0;
        
        // If zero-rated, skip all tax calculation
        if (!$order->is_zero_rated && !$order->tax_inclusive) {
            // Tax/VAT is added on top of subtotal
            if ($order->tax > 0) {
                $taxAmount = $subTotal * ($order->tax / 100);
            }
            
            if ($order->vat > 0) {
                $vatAmount = $subTotal * ($order->vat / 100);
            }
        }

        
        // Calculate final total
        $total = $subTotal + $taxAmount + $vatAmount + $order->shipping - $order->discount;
        
        // Calculate outstanding amount: if no payments made, outstanding = total
        $totalPaid = $order->paid_amount ?? 0;
        $outstandingAmount = max(0, $total - $totalPaid);
        
        // Update order
        $order->update([
            'sub_total' => $subTotal,
            'tax' => $taxAmount,
            'vat' => $vatAmount,
            'total' => $total,
            'outstanding_amount' => $outstandingAmount,
        ]);
    }

    /**
     * Update order status
     * 
     * @param Order $order
     * @param OrderStatus $newStatus
     * @return bool
     */
    public function updateStatus(Order $order, OrderStatus $newStatus): bool
    {
        $oldStatus = $order->order_status;
        
        // Validate status transition
        if ($oldStatus && !in_array($newStatus, $oldStatus->nextStatuses())) {
            throw new \Exception("Cannot transition from {$oldStatus->value} to {$newStatus->value}");
        }
        
        $order->order_status = $newStatus;
        $order->save();
        
        // Fire event
        event(new OrderStatusChanged($order, $oldStatus, $newStatus));
        
        return true;
    }

    /**
     * Update payment status
     * 
     * @param Order $order
     * @param PaymentStatus $newStatus
     * @return bool
     */
    public function updatePaymentStatus(Order $order, PaymentStatus $newStatus): bool
    {
        $order->payment_status = $newStatus;
        $order->save();
        
        return true;
    }

    /**
     * Mark quote as sent
     * 
     * @param Order $quote
     * @return bool
     */
    public function sendQuote(Order $quote): bool
    {
        if (!$quote->isQuote()) {
            throw new \Exception('Only quotes can be sent');
        }
        
        $quote->update([
            'quote_status' => QuoteStatus::SENT,
            'sent_at' => now(),
        ]);
        
        return true;
    }

    /**
     * Approve a quote
     * 
     * @param Order $quote
     * @return bool
     */
    public function approveQuote(Order $quote): bool
    {
        if (!$quote->isQuote()) {
            throw new \Exception('Only quotes can be approved');
        }
        
        $quote->update([
            'quote_status' => QuoteStatus::APPROVED,
            'approved_at' => now(),
        ]);
        
        return true;
    }

    /**
     * Reject a quote
     * 
     * @param Order $quote
     * @param string $reason
     * @return bool
     */
    public function rejectQuote(Order $quote, string $reason = ''): bool
    {
        if (!$quote->isQuote()) {
            throw new \Exception('Only quotes can be rejected');
        }
        
        $quote->update([
            'quote_status' => QuoteStatus::REJECTED,
            'order_notes' => $quote->order_notes . "\n\nRejection reason: " . $reason,
        ]);
        
        return true;
    }

    /**
     * Cancel an order
     * 
     * @param Order $order
     * @param string $reason
     * @return bool
     */
    public function cancelOrder(Order $order, string $reason = ''): bool
    {
        if (!$order->canCancel()) {
            throw new \Exception('This order cannot be cancelled');
        }
        
        // Release allocated inventory
        if ($order->order_status === OrderStatus::PROCESSING) {
            $fulfillmentService = app(OrderFulfillmentService::class);
            $fulfillmentService->releaseInventory($order);
        }
        
        $order->update([
            'order_status' => OrderStatus::CANCELLED,
            'order_notes' => $order->order_notes . "\n\nCancellation reason: " . $reason,
        ]);
        
        return true;
    }

    /**
     * Update shipping information
     * 
     * @param Order $order
     * @param string $trackingNumber
     * @param string $carrier
     * @return bool
     */
    public function updateShipping(Order $order, string $trackingNumber, string $carrier): bool
    {
        $order->update([
            'tracking_number' => $trackingNumber,
            'shipping_carrier' => $carrier,
            'order_status' => OrderStatus::SHIPPED,
        ]);
        
        return true;
    }

    /**
     * Confirm order and allocate inventory
     * 
     * @param Order $order
     * @param int|null $warehouseId
     * @return array Allocation results
     */
    public function confirmOrder(Order $order, ?int $warehouseId = null): array
    {
        $order->loadMissing('items.productVariant.product', 'items.addon');

        // Validate inventory availability first
        $fulfillmentService = app(OrderFulfillmentService::class);
        $validation = $fulfillmentService->validateInventoryAvailability($order);
        
        if (!$validation['can_fulfill']) {
            throw new \Exception('Insufficient inventory to fulfill this order');
        }
        
        // Allocate inventory
        $results = $fulfillmentService->allocateInventory($order, $warehouseId);
        
        // Update order status
        if (count($results['failed']) === 0 && $order->order_status !== OrderStatus::PROCESSING) {
            $this->updateStatus($order, OrderStatus::PROCESSING);
        }
        
        return $results;
    }

    /**
     * Ship order (mark items as shipped)
     * 
     * @param Order $order
     * @param array $itemQuantities Optional ['item_id' => quantity]
     * @param string|null $trackingNumber
     * @param string|null $carrier
     * @return bool
     */
    public function shipOrder(
        Order $order, 
        array $itemQuantities = [], 
        ?string $trackingNumber = null, 
        ?string $carrier = null
    ): bool {
        $fulfillmentService = app(OrderFulfillmentService::class);
        
        // Mark items as shipped
        $fulfillmentService->markAsShipped($order, $itemQuantities);
        
        // Update shipping info if provided
        $updateData = ['order_status' => OrderStatus::SHIPPED];
        
        if ($trackingNumber) {
            $updateData['tracking_number'] = $trackingNumber;
        }
        
        if ($carrier) {
            $updateData['shipping_carrier'] = $carrier;
        }
        
        $order->update($updateData);
        
        return true;
    }

    /**
     * Complete an order
     * 
     * @param Order $order
     * @return bool
     */
    public function completeOrder(Order $order): bool
    {
        if ($order->order_status !== OrderStatus::SHIPPED) {
            throw new \Exception('Only shipped orders can be marked as completed');
        }
        
        $this->updateStatus($order, OrderStatus::COMPLETED);
        
        return true;
    }

    /**
     * Validate inventory before confirming order
     * 
     * @param Order $order
     * @return array
     */
    public function validateInventory(Order $order): array
    {
        $fulfillmentService = app(OrderFulfillmentService::class);
        return $fulfillmentService->validateInventoryAvailability($order);
    }

    /**
     * Get fulfillment summary for an order
     * 
     * @param Order $order
     * @return array
     */
    public function getFulfillmentSummary(Order $order): array
    {
        $fulfillmentService = app(OrderFulfillmentService::class);
        return $fulfillmentService->getFulfillmentSummary($order);
    }
}
