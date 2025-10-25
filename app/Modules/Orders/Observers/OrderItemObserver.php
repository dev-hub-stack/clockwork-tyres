<?php

namespace App\Modules\Orders\Observers;

use App\Modules\Orders\Models\OrderItem;
use App\Modules\Orders\Models\OrderItemQuantity;
use App\Modules\Products\Models\ProductVariant;

class OrderItemObserver
{
    /**
     * Handle the OrderItem "creating" event.
     * Populate product details from variant before saving
     */
    public function creating(OrderItem $orderItem): void
    {
        $this->populateProductDetails($orderItem);
    }

    /**
     * Handle the OrderItem "created" event.
     * Create OrderItemQuantity record for warehouse allocation
     */
    public function created(OrderItem $orderItem): void
    {
        $this->createWarehouseAllocation($orderItem);
    }

    /**
     * Handle the OrderItem "updating" event.
     * Update product details if variant changed
     */
    public function updating(OrderItem $orderItem): void
    {
        // Only repopulate if product_name is missing or variant changed
        if (empty($orderItem->product_name) || $orderItem->isDirty('product_variant_id')) {
            $this->populateProductDetails($orderItem);
        }
    }

    /**
     * Handle the OrderItem "updated" event.
     * Update warehouse allocation if warehouse or quantity changed
     */
    public function updated(OrderItem $orderItem): void
    {
        if ($orderItem->wasChanged(['warehouse_id', 'quantity'])) {
            $this->updateWarehouseAllocation($orderItem);
        }
    }

    /**
     * Populate product details from the associated variant
     */
    private function populateProductDetails(OrderItem $orderItem): void
    {
        if (!$orderItem->product_variant_id) {
            return;
        }

        $variant = ProductVariant::with(['product.brand', 'product.model', 'product.finish'])
            ->find($orderItem->product_variant_id);

        if (!$variant || !$variant->product) {
            // Set defaults if variant not found
            $orderItem->product_name = $orderItem->product_name ?? 'Unknown Product';
            return;
        }

        // Populate product fields
        $orderItem->product_id = $variant->product_id;
        $orderItem->product_name = $variant->product->name ?? 'Unknown Product';
        $orderItem->sku = $variant->sku;
        $orderItem->brand_name = $variant->product->brand?->name;
        $orderItem->model_name = $variant->product->model?->name;
        $orderItem->product_description = $variant->product->description;
        
        // Calculate line total if not set
        if (!$orderItem->line_total) {
            $qty = floatval($orderItem->quantity ?? 0);
            $price = floatval($orderItem->unit_price ?? 0);
            $discount = floatval($orderItem->discount ?? 0);
            $orderItem->line_total = ($qty * $price) - $discount;
        }
        
        // Store snapshots for historical accuracy
        $orderItem->product_snapshot = json_encode($variant->product->toArray());
        $orderItem->variant_snapshot = json_encode($variant->toArray());
    }

    /**
     * Create warehouse allocation record in order_item_quantities table
     */
    private function createWarehouseAllocation(OrderItem $orderItem): void
    {
        // Only create if warehouse_id is set
        if (!$orderItem->warehouse_id) {
            return;
        }

        OrderItemQuantity::create([
            'order_item_id' => $orderItem->id,
            'warehouse_id' => $orderItem->warehouse_id,
            'quantity' => $orderItem->quantity ?? 0,
        ]);
    }

    /**
     * Update warehouse allocation when warehouse or quantity changes
     */
    private function updateWarehouseAllocation(OrderItem $orderItem): void
    {
        if (!$orderItem->warehouse_id) {
            // If warehouse removed, delete allocations
            $orderItem->quantities()->delete();
            return;
        }

        // Find existing allocation or create new one
        $quantity = OrderItemQuantity::firstOrNew([
            'order_item_id' => $orderItem->id,
            'warehouse_id' => $orderItem->warehouse_id,
        ]);

        $quantity->quantity = $orderItem->quantity ?? 0;
        $quantity->save();

        // Delete old allocations for different warehouses
        OrderItemQuantity::where('order_item_id', $orderItem->id)
            ->where('warehouse_id', '!=', $orderItem->warehouse_id)
            ->delete();
    }
}
