<?php

namespace App\Services;

use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductVariant;
use Illuminate\Support\Facades\Log;

/**
 * Order Product Sync Service
 * 
 * Handles finding or creating products/variants specifically for order sync
 */
class OrderProductSyncService
{
    public function __construct(
        protected BrandLookupService $brandService,
        protected ModelLookupService $modelService
    ) {}
    
    /**
     * Find or create product and variant from order item data
     * Returns both product and variant (if applicable)
     * 
     * @param array $itemData
     * @return array ['product' => Product, 'variant' => ProductVariant|null]
     */
    public function findOrCreateFromOrderItem(array $itemData): array
    {
        $product = $this->findOrCreateProduct($itemData);
        $variant = $this->findOrCreateVariant($itemData, $product);
        
        return [
            'product' => $product,
            'variant' => $variant
        ];
    }
    
    /**
     * Find or create a product from external item data
     * 
     * @param array $itemData
     * @return Product
     */
    protected function findOrCreateProduct(array $itemData): Product
    {
        $sku = $itemData['sku'] ?? null;
        
        // Try to find product by SKU first
        if ($sku) {
            $product = Product::where('sku', $sku)->first();
            
            if ($product) {
                Log::info('OrderProductSync: Product found by SKU', [
                    'product_id' => $product->id,
                    'sku' => $sku
                ]);
                return $product;
            }
        }
        
        // Product not found - need to create it
        // First, find or create brand and model
        $brand = null;
        $model = null;
        
        if (isset($itemData['brand_name']) && !empty($itemData['brand_name'])) {
            try {
                $brand = $this->brandService->findOrCreate($itemData['brand_name']);
            } catch (\Exception $e) {
                Log::warning('OrderProductSync: Failed to create brand', [
                    'brand_name' => $itemData['brand_name'],
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        if (isset($itemData['model_name']) && !empty($itemData['model_name'])) {
            try {
                $model = $this->modelService->findOrCreate($itemData['model_name']);
            } catch (\Exception $e) {
                Log::warning('OrderProductSync: Failed to create model', [
                    'model_name' => $itemData['model_name'],
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        // Create the product
        $productData = [
            'name' => $itemData['product_name'] ?? 'Unknown Product',
            'sku' => $sku,
            'brand_id' => $brand?->id,
            'model_id' => $model?->id,
            'price' => $itemData['unit_price'] ?? 0, // Required field
            'status' => true, // Active
        ];
        
        $product = Product::create($productData);
        
        Log::info('OrderProductSync: Product created', [
            'product_id' => $product->id,
            'sku' => $sku,
            'name' => $product->name,
            'brand_id' => $brand?->id,
            'model_id' => $model?->id
        ]);
        
        return $product;
    }
    
    /**
     * Find or create a product variant
     * 
     * @param array $itemData
     * @param Product $product
     * @return ProductVariant|null
     */
    protected function findOrCreateVariant(array $itemData, Product $product): ?ProductVariant
    {
        $sku = $itemData['sku'] ?? null;
        
        // Try to find variant by SKU within this product
        if ($sku) {
            $variant = ProductVariant::where('product_id', $product->id)
                ->where('sku', $sku)
                ->first();
            
            if ($variant) {
                Log::info('OrderProductSync: Variant found by SKU', [
                    'variant_id' => $variant->id,
                    'product_id' => $product->id,
                    'sku' => $sku
                ]);
                return $variant;
            }
        }
        
        // Check if we have variant-specific data
        $hasVariantData = isset($itemData['variant_title']) 
            || isset($itemData['size']) 
            || isset($itemData['bolt_pattern'])
            || isset($itemData['offset']);
        
        if (!$hasVariantData) {
            // No variant needed
            return null;
        }
        
        // Create variant
        $variantData = [
            'product_id' => $product->id,
            'title' => $itemData['variant_title'] ?? 'Default',
            'sku' => $sku,
            'size' => $itemData['size'] ?? null,
            'price' => $itemData['unit_price'] ?? $product->retail_price,
        ];
        
        $variant = ProductVariant::create($variantData);
        
        Log::info('OrderProductSync: Variant created', [
            'variant_id' => $variant->id,
            'product_id' => $product->id,
            'sku' => $sku
        ]);
        
        return $variant;
    }
}
