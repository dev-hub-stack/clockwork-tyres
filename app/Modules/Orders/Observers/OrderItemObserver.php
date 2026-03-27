<?php

namespace App\Modules\Orders\Observers;

use App\Modules\Orders\Models\OrderItem;
use App\Modules\Orders\Models\OrderItemQuantity;
use App\Modules\Products\Models\AddOn;
use App\Modules\Products\Models\ProductVariant;

class OrderItemObserver
{
    /**
     * Handle the OrderItem "creating" event.
     * Populate product details from variant before saving
     */
    public function creating(OrderItem $orderItem): void
    {
        $this->normalizeNumericDefaults($orderItem);

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
        $this->normalizeNumericDefaults($orderItem);

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

    private function normalizeNumericDefaults(OrderItem $orderItem): void
    {
        $orderItem->quantity = max(1, (int) ($orderItem->quantity ?? 1));
        $orderItem->unit_price = round((float) ($orderItem->unit_price ?? 0), 2);
        $orderItem->discount = round((float) ($orderItem->discount ?? 0), 2);
        $orderItem->tax_amount = round((float) ($orderItem->tax_amount ?? 0), 2);
        $orderItem->allocated_quantity = (int) ($orderItem->allocated_quantity ?? 0);
        $orderItem->shipped_quantity = (int) ($orderItem->shipped_quantity ?? 0);
        $orderItem->tax_inclusive = $orderItem->tax_inclusive ?? true;
    }
    private function populateProductDetails(OrderItem $orderItem): void
    {
        // Custom one-off items
        if (!$orderItem->product_variant_id && !$orderItem->add_on_id) {
            // Keep the custom product_name and compute line_total if needed
            if (!$orderItem->line_total) {
                $qty = floatval($orderItem->quantity ?? 0);
                $price = floatval($orderItem->unit_price ?? 0);
                $discount = floatval($orderItem->discount ?? 0);
                $orderItem->line_total = ($qty * $price) - $discount;
            }
            return;
        }

        if (!$orderItem->product_variant_id) {
            // If this is an add-on item, populate product_name from the addon title
            if ($orderItem->add_on_id) {
                $addon = AddOn::withTrashed()->find($orderItem->add_on_id);
                if ($addon) {
                    $orderItem->product_name = $orderItem->product_name ?? $addon->title;
                    $orderItem->sku          = $orderItem->sku ?? $addon->part_number;
                    $orderItem->brand_name   = $orderItem->brand_name ?? null;
                    $orderItem->addon_snapshot = json_encode($addon->toArray());
                    // Calculate line total if not set
                    if (!$orderItem->line_total) {
                        $qty      = floatval($orderItem->quantity ?? 1);
                        $price    = floatval($orderItem->unit_price ?? 0);
                        $discount = floatval($orderItem->discount ?? 0);
                        $orderItem->line_total = ($qty * $price) - $discount;
                    }
                } else {
                    // Addon not found — set a fallback so DB constraint doesn't fail
                    $orderItem->product_name = $orderItem->product_name ?? 'Add-On Item';
                }
            }
            return;
        }

        $variant = ProductVariant::with(['product.brand', 'product.model', 'product.finish', 'finishRelation'])
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
        // Include finish_name in variant snapshot so preview/PDF/email templates can read it
        $variantArray = $variant->toArray();
        $variantArray['finish_name'] = $variant->finishRelation?->finish ?? null;
        $orderItem->variant_snapshot = json_encode($variantArray);
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

        // Skip allocation for consignment-sourced invoices.
        // Inventory was already deducted from the warehouse when the consignment
        // was marked as SENT. Creating an OrderItemQuantity here would cause a
        // second deduction and show inflated "sold" figures in the inventory grid.
        if ($orderItem->order?->external_source === 'consignment') {
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
