<?php

namespace App\Services;

use App\Modules\Products\Models\ProductVariant;

class VariantSnapshotService
{
    /**
     * Create a snapshot of variant data at the time of order
     * This preserves historical specifications even if variant is modified/deleted later
     * 
     * @param ProductVariant $variant
     * @return array
     */
    public function createSnapshot(ProductVariant $variant): array
    {
        // Load relationships needed for snapshot
        $variant->load(['product', 'product.brand', 'product.model', 'finish']);
        
        return [
            // Core variant data
            'variant_id' => $variant->id,
            'sku' => $variant->sku,
            'barcode' => $variant->barcode,
            
            // Wheel specifications
            'size' => $variant->size,
            'width' => $variant->width,
            'diameter' => $variant->diameter,
            'bolt_pattern' => $variant->bolt_pattern,
            'offset' => $variant->offset,
            'center_bore' => $variant->center_bore,
            'hub_bore' => $variant->hub_bore,
            
            // Finish information
            'finish' => $variant->finish,
            'finish_id' => $variant->finish_id,
            'finish_name' => $variant->finishRelation?->name,
            'finish_code' => $variant->finishRelation?->code,
            'color' => $variant->color,
            
            // Pricing at time of order
            'price' => $variant->price,
            'cost' => $variant->cost,
            'msrp' => $variant->msrp,
            'dealer_price' => $variant->dealer_price,
            
            // Inventory at time of order (for reference)
            'quantity_at_order' => $variant->total_quantity ?? 0,
            'weight' => $variant->weight,
            
            // Specifications (JSONB field if exists)
            'specifications' => $variant->specifications ?? [],
            
            // Images specific to this variant
            'images' => $variant->images?->map(function ($image) {
                return [
                    'url' => $image->url,
                    'is_primary' => $image->is_primary ?? false,
                ];
            })->toArray() ?? [],
            
            // Primary image for quick access
            'primary_image' => $variant->images?->where('is_primary', true)->first()?->url 
                            ?? $variant->images?->first()?->url
                            ?? $variant->product?->images?->first()?->url,
            
            // Product reference (parent product snapshot data)
            'product_id' => $variant->product_id,
            'product_name' => $variant->product?->name,
            'brand_name' => $variant->product?->brand?->name,
            'model_name' => $variant->product?->model?->name,
            
            // Status
            'is_active' => $variant->is_active ?? true,
            'stock_status' => $variant->stock_status,
            
            // Compatibility (if stored)
            'compatible_vehicles' => $variant->compatible_vehicles ?? [],
            
            // Metadata
            'snapshot_date' => now()->toISOString(),
            'snapshot_version' => '1.0',
        ];
    }

    /**
     * Create snapshots for multiple variants
     * 
     * @param \Illuminate\Support\Collection $variants
     * @return array
     */
    public function createBulkSnapshots($variants): array
    {
        return $variants->map(function ($variant) {
            return $this->createSnapshot($variant);
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
        $requiredFields = ['variant_id', 'sku', 'snapshot_date'];
        
        foreach ($requiredFields as $field) {
            if (!isset($snapshot[$field])) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Get display name for variant (from snapshot)
     * Format: "Brand Model - Size - Finish"
     * 
     * @param array $snapshot
     * @return string
     */
    public function getDisplayName(array $snapshot): string
    {
        $parts = array_filter([
            data_get($snapshot, 'brand_name'),
            data_get($snapshot, 'model_name'),
            data_get($snapshot, 'size'),
            data_get($snapshot, 'finish_name') ?? data_get($snapshot, 'finish'),
        ]);
        
        return implode(' - ', $parts) ?: 'Unknown Variant';
    }

    /**
     * Get specifications summary from snapshot
     * 
     * @param array $snapshot
     * @return string
     */
    public function getSpecificationsSummary(array $snapshot): string
    {
        $specs = [];
        
        if ($size = data_get($snapshot, 'size')) {
            $specs[] = "Size: {$size}";
        }
        
        if ($boltPattern = data_get($snapshot, 'bolt_pattern')) {
            $specs[] = "Bolt: {$boltPattern}";
        }
        
        if ($offset = data_get($snapshot, 'offset')) {
            $specs[] = "Offset: {$offset}";
        }
        
        if ($centerBore = data_get($snapshot, 'center_bore')) {
            $specs[] = "CB: {$centerBore}";
        }
        
        return implode(' | ', $specs);
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
        
        $fieldsToCompare = [
            'sku', 'price', 'size', 'bolt_pattern', 'offset', 
            'center_bore', 'finish', 'quantity_at_order'
        ];
        
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
