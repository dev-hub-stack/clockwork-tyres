<?php

namespace App\Services;

use App\Modules\Products\Models\Product;

class ProductSnapshotService
{
    /**
     * Create a snapshot of product data at the time of order
     * This preserves historical accuracy even if product is modified/deleted later
     * 
     * @param Product $product
     * @return array
     */
    public function createSnapshot(Product $product): array
    {
        // Load relationships needed for snapshot
        $product->load(['brand', 'model', 'finish', 'images']);
        
        return [
            // Core product data
            'product_id' => $product->id,
            'name' => $product->name,
            'description' => $product->description,
            'sku' => $product->sku,
            
            // Brand information
            'brand_id' => $product->brand_id,
            'brand_name' => $product->brand?->name,
            'brand_logo' => $product->brand?->logo_url,
            
            // Model information
            'model_id' => $product->model_id,
            'model_name' => $product->model?->name,
            'model_description' => $product->model?->description,
            
            // Finish information
            'finish_id' => $product->finish_id,
            'finish_name' => $product->finish?->name,
            'finish_code' => $product->finish?->code,
            
            // Pricing at time of order
            'retail_price' => $product->retail_price,
            'wholesale_price' => $product->wholesale_price,
            'msrp' => $product->msrp,
            
            // Images
            'images' => $product->images->map(function ($image) {
                return [
                    'url' => $image->url,
                    'is_primary' => $image->is_primary ?? false,
                    'alt_text' => $image->alt_text,
                ];
            })->toArray(),
            
            // Primary image for quick access
            'primary_image' => $product->images->where('is_primary', true)->first()?->url 
                            ?? $product->images->first()?->url,
            
            // Status
            'is_active' => $product->is_active ?? true,
            'stock_status' => $product->stock_status,
            
            // Metadata
            'snapshot_date' => now()->toISOString(),
            'snapshot_version' => '1.0',
        ];
    }

    /**
     * Create snapshots for multiple products
     * 
     * @param \Illuminate\Support\Collection $products
     * @return array
     */
    public function createBulkSnapshots($products): array
    {
        return $products->map(function ($product) {
            return $this->createSnapshot($product);
        })->toArray();
    }

    /**
     * Get a specific value from a snapshot
     * 
     * @param array $snapshot
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getSnapshotValue(array $snapshot, string $key, $default = null)
    {
        return data_get($snapshot, $key, $default);
    }

    /**
     * Check if snapshot is valid (has required fields)
     * 
     * @param array $snapshot
     * @return bool
     */
    public function isValidSnapshot(array $snapshot): bool
    {
        $requiredFields = ['product_id', 'name', 'snapshot_date'];
        
        foreach ($requiredFields as $field) {
            if (!isset($snapshot[$field])) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Compare two snapshots to detect changes
     * 
     * @param array $oldSnapshot
     * @param array $newSnapshot
     * @return array Changes detected
     */
    public function compareSnapshots(array $oldSnapshot, array $newSnapshot): array
    {
        $changes = [];
        
        $fieldsToCompare = ['name', 'retail_price', 'wholesale_price', 'brand_name', 'model_name'];
        
        foreach ($fieldsToCompare as $field) {
            $oldValue = data_get($oldSnapshot, $field);
            $newValue = data_get($newSnapshot, $field);
            
            if ($oldValue !== $newValue) {
                $changes[$field] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }
        
        return $changes;
    }
}
