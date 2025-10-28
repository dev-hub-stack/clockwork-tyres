<?php

namespace App\Modules\Consignments\Services;

use App\Modules\Products\Models\ProductVariant;
use App\Services\ProductSnapshotService;
use App\Services\VariantSnapshotService;
use Illuminate\Support\Facades\Log;

/**
 * ConsignmentSnapshotService
 * 
 * Captures product/variant data at consignment time to prevent price changes
 * from affecting historical consignment records.
 * 
 * Extends the ProductSnapshotService and VariantSnapshotService patterns.
 */
class ConsignmentSnapshotService
{
    public function __construct(
        protected ProductSnapshotService $productSnapshotService,
        protected VariantSnapshotService $variantSnapshotService,
    ) {}

    /**
     * Create a comprehensive snapshot for consignment item
     * 
     * @param ProductVariant $variant
     * @param int|null $customerId For customer-specific pricing
     * @return array Snapshot data
     */
    public function createSnapshot(ProductVariant $variant, ?int $customerId = null): array
    {
        try {
            // Get product snapshot
            $productSnapshot = $this->productSnapshotService->createSnapshot($variant->product);
            
            // Get variant snapshot
            $variantSnapshot = $this->variantSnapshotService->createSnapshot($variant);

            // Combine into consignment snapshot
            return [
                // Core identification
                'snapshot_date' => now()->toISOString(),
                'customer_id' => $customerId,
                
                // Product data
                'product_data' => $productSnapshot,
                
                // Variant data
                'variant_data' => $variantSnapshot,
                
                // Quick access fields (denormalized for performance)
                'product_id' => $variant->product_id,
                'variant_id' => $variant->id,
                'sku' => $variant->sku,
                'product_name' => $variant->product->name ?? '',
                'brand_name' => $variant->product->brand->name ?? '',
                'model_name' => $variant->product->model->name ?? '',
                'finish_name' => $variant->product->finish->name ?? '',
                
                // Pricing at time of consignment
                'variant_price' => $variant->price ?? 0,
                'product_retail_price' => $variant->product->retail_price ?? 0,
                'product_wholesale_price' => $variant->product->wholesale_price ?? 0,
                
                // Inventory snapshot
                'quantity_available' => $variant->total_quantity ?? 0,
                
                // Images
                'images' => $this->getProductImages($variant),
                
                // Specifications
                'specifications' => [
                    'size' => $variant->size,
                    'bolt_pattern' => $variant->bolt_pattern,
                    'offset' => $variant->offset,
                    'center_bore' => $variant->center_bore,
                    'finish' => $variant->finish,
                    'color' => $variant->color,
                ],
                
                // Fitment information
                'fitment' => $variant->product->fitment ?? [],
            ];

        } catch (\Exception $e) {
            Log::error('ConsignmentSnapshotService: Error creating snapshot', [
                'variant_id' => $variant->id,
                'error' => $e->getMessage(),
            ]);

            // Return minimal snapshot on error
            return [
                'snapshot_date' => now()->toISOString(),
                'variant_id' => $variant->id,
                'sku' => $variant->sku,
                'product_name' => $variant->product->name ?? 'Unknown Product',
                'error' => 'Partial snapshot due to error',
            ];
        }
    }

    /**
     * Get product images for snapshot
     */
    protected function getProductImages(ProductVariant $variant): array
    {
        $images = [];
        $product = $variant->product;

        // Get primary image from product
        if ($product && $product->images) {
            foreach ($product->images as $key => $image) {
                if (!empty($image)) {
                    $images[] = [
                        'url' => $image,
                        'type' => 'product',
                        'position' => $key + 1,
                    ];
                }
            }
        }

        return $images;
    }

    /**
     * Compare snapshot with current product data
     * 
     * Useful for showing customers what changed since consignment was created
     * 
     * @param array $snapshot
     * @param ProductVariant $currentVariant
     * @return array Changes detected
     */
    public function compareWithCurrent(array $snapshot, ProductVariant $currentVariant): array
    {
        $changes = [];

        // Price changes
        $snapshotPrice = $snapshot['variant_price'] ?? 0;
        $currentPrice = $currentVariant->price ?? 0;
        
        if ($snapshotPrice != $currentPrice) {
            $changes['price'] = [
                'old' => $snapshotPrice,
                'new' => $currentPrice,
                'difference' => $currentPrice - $snapshotPrice,
                'percentage' => $snapshotPrice > 0 
                    ? round((($currentPrice - $snapshotPrice) / $snapshotPrice) * 100, 2) 
                    : 0,
            ];
        }

        // Inventory changes
        $snapshotQty = $snapshot['quantity_available'] ?? 0;
        $currentQty = $currentVariant->total_quantity ?? 0;
        
        if ($snapshotQty != $currentQty) {
            $changes['inventory'] = [
                'old' => $snapshotQty,
                'new' => $currentQty,
                'difference' => $currentQty - $snapshotQty,
            ];
        }

        // Product name changes
        $snapshotName = $snapshot['product_name'] ?? '';
        $currentName = $currentVariant->product->name ?? '';
        
        if ($snapshotName != $currentName) {
            $changes['product_name'] = [
                'old' => $snapshotName,
                'new' => $currentName,
            ];
        }

        // SKU changes
        $snapshotSku = $snapshot['sku'] ?? '';
        $currentSku = $currentVariant->sku ?? '';
        
        if ($snapshotSku != $currentSku) {
            $changes['sku'] = [
                'old' => $snapshotSku,
                'new' => $currentSku,
            ];
        }

        return $changes;
    }

    /**
     * Check if snapshot has changed compared to current data
     */
    public function hasChanged(array $snapshot, ProductVariant $currentVariant): bool
    {
        $changes = $this->compareWithCurrent($snapshot, $currentVariant);
        return !empty($changes);
    }

    /**
     * Get formatted changes description
     */
    public function getChangesDescription(array $changes): string
    {
        $descriptions = [];

        if (isset($changes['price'])) {
            $diff = $changes['price']['difference'];
            $desc = $diff > 0 ? 'increased' : 'decreased';
            $descriptions[] = "Price {$desc} by $" . abs($diff);
        }

        if (isset($changes['inventory'])) {
            $diff = $changes['inventory']['difference'];
            $desc = $diff > 0 ? 'increased' : 'decreased';
            $descriptions[] = "Inventory {$desc} by " . abs($diff);
        }

        if (isset($changes['product_name'])) {
            $descriptions[] = "Product name changed";
        }

        if (isset($changes['sku'])) {
            $descriptions[] = "SKU changed";
        }

        return implode(', ', $descriptions);
    }

    /**
     * Validate snapshot integrity
     * 
     * @param array $snapshot
     * @return bool
     */
    public function validateSnapshot(array $snapshot): bool
    {
        $requiredFields = [
            'variant_id',
            'sku',
            'product_name',
        ];

        foreach ($requiredFields as $field) {
            if (!isset($snapshot[$field]) || empty($snapshot[$field])) {
                Log::warning('ConsignmentSnapshotService: Invalid snapshot - missing field', [
                    'field' => $field,
                ]);
                return false;
            }
        }

        return true;
    }

    /**
     * Restore product data from snapshot
     * 
     * Useful for displaying historical consignment details
     * 
     * @param array $snapshot
     * @return object Product-like object with snapshot data
     */
    public function restoreFromSnapshot(array $snapshot): object
    {
        return (object) [
            'id' => $snapshot['variant_id'] ?? null,
            'sku' => $snapshot['sku'] ?? '',
            'name' => $snapshot['product_name'] ?? '',
            'brand_name' => $snapshot['brand_name'] ?? '',
            'model_name' => $snapshot['model_name'] ?? '',
            'finish_name' => $snapshot['finish_name'] ?? '',
            'price' => $snapshot['variant_price'] ?? 0,
            'specifications' => $snapshot['specifications'] ?? [],
            'images' => $snapshot['images'] ?? [],
            'snapshot_date' => $snapshot['snapshot_date'] ?? null,
        ];
    }
}
