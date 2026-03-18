<?php

namespace App\Observers;

use App\Modules\Products\Models\ProductVariant;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\ProductInventory;
use App\Services\ActivityLogService;
use Illuminate\Support\Facades\Log;

/**
 * ProductVariantInventoryObserver
 * 
 * Automatically creates inventory records for all active warehouses
 * when a new ProductVariant is created.
 * 
 * This matches the old Reporting system behavior where
 * ProductInventory::import() was called after variant creation.
 */
class ProductVariantInventoryObserver
{
    /**
     * Handle the ProductVariant "created" event.
     *
     * @param  \App\Modules\Products\Models\ProductVariant  $variant
     * @return void
     */
    public function created(ProductVariant $variant)
    {
        try {
            // Get all active warehouses
            $warehouses = Warehouse::where('status', 1)->get();
            
            if ($warehouses->isEmpty()) {
                Log::info("No active warehouses found. Skipping inventory initialization for variant: {$variant->id}");
                return;
            }
            
            $createdCount = 0;
            
            foreach ($warehouses as $warehouse) {
                // Create inventory record with initial quantity of 0
                $inventory = ProductInventory::updateOrCreate(
                    [
                        'warehouse_id' => $warehouse->id,
                        'product_variant_id' => $variant->id,
                    ],
                    [
                        'quantity' => 0,
                        'eta' => null,
                        'eta_qty' => 0,
                    ]
                );
                
                $createdCount++;
            }
            
            Log::info("Initialized inventory for variant {$variant->id} (SKU: {$variant->sku}) across {$createdCount} warehouses");

            if (auth()->check()) {
                ActivityLogService::log(
                    'product_added',
                    'Added product variant ' . ($variant->sku ?: ('#' . $variant->id)),
                    $variant,
                );
            }
            
        } catch (\Exception $e) {
            Log::error("Failed to initialize inventory for variant {$variant->id}: " . $e->getMessage());
        }
    }

    /**
     * Handle the ProductVariant "updated" event.
     */
    public function updated(ProductVariant $variant): void
    {
        if (! auth()->check() || empty($variant->getChanges())) {
            return;
        }

        ActivityLogService::log(
            'product_updated',
            'Updated product variant ' . ($variant->sku ?: ('#' . $variant->id)),
            $variant,
        );
    }
    
    /**
     * Handle the ProductVariant "deleted" event.
     * 
     * When a variant is deleted, we should also clean up its inventory records.
     *
     * @param  \App\Modules\Products\Models\ProductVariant  $variant
     * @return void
     */
    public function deleted(ProductVariant $variant)
    {
        try {
            // Delete all inventory records for this variant
            $deletedCount = ProductInventory::where('product_variant_id', $variant->id)->delete();
            
            if ($deletedCount > 0) {
                Log::info("Deleted {$deletedCount} inventory records for variant {$variant->id} (SKU: {$variant->sku})");
            }
            
        } catch (\Exception $e) {
            Log::error("Failed to delete inventory for variant {$variant->id}: " . $e->getMessage());
        }
    }
}
