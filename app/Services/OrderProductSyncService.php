<?php

namespace App\Services;

use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductVariant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
        $externalId = $itemData['external_product_id'] ?? null;
        $source = $itemData['external_source'] ?? 'tunerstop';
        
        // 1. Try to find product by SKU first
        if ($sku) {
            $product = Product::where('sku', $sku)->first();
            
            if ($product) {
                Log::info('OrderProductSync: Product found by SKU', [
                    'product_id' => $product->id,
                    'sku' => $sku
                ]);
                
                // Update existing product with new data
                $product->update([
                    'images' => isset($itemData['product_image']) ? json_encode([$itemData['product_image']]) : $product->images,
                    'construction' => $itemData['construction'] ?? $product->construction,
                ]);
                
                return $product;
            }
        }
        
        // 2. Try to find by External ID
        if ($externalId) {
            $product = Product::where('external_product_id', $externalId)
                ->where('external_source', $source)
                ->first();
                
            if ($product) {
                Log::info('OrderProductSync: Product found by External ID', [
                    'product_id' => $product->id,
                    'external_id' => $externalId
                ]);
                
                // Update existing product with new data
                $product->update([
                    'images' => isset($itemData['product_image']) ? json_encode([$itemData['product_image']]) : $product->images,
                    'construction' => $itemData['construction'] ?? $product->construction,
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
        
        // Generate SKU if missing
        if (empty($sku)) {
            if ($externalId) {
                $sku = "TS-{$externalId}";
                // Ensure uniqueness
                if (Product::where('sku', $sku)->exists()) {
                    $sku = "TS-{$externalId}-" . uniqid();
                }
            } else {
                $sku = "TS-GEN-" . uniqid();
            }
            Log::info("OrderProductSync: Generated SKU for missing SKU", ['sku' => $sku]);
        }

        // Create the product
        $productData = [
            'name' => $itemData['product_name'] ?? 'Unknown Product',
            'sku' => $sku,
            'brand_id' => $brand?->id,
            'model_id' => $model?->id,
            'price' => $itemData['unit_price'] ?? 0, // Required field
            'status' => true, // Active
            'external_product_id' => $externalId,
            'external_product_id' => $externalId,
            'external_source' => $source,
            'images' => isset($itemData['product_image']) ? json_encode([$itemData['product_image']]) : null,
            'construction' => $itemData['construction'] ?? null,
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
        $externalVariantId = $itemData['external_variant_id'] ?? null;
        $source = $itemData['external_source'] ?? 'tunerstop';
        
        // Parse size if rim_width/diameter are missing
        if ((empty($itemData['rim_width']) || empty($itemData['rim_diameter'])) && !empty($itemData['size'])) {
            // Expected format: "18x8" or "18x8.5"
            $parts = explode('x', strtolower($itemData['size']));
            if (count($parts) === 2) {
                $diameter = floatval(trim($parts[0]));
                $width = floatval(trim($parts[1]));
                
                if (empty($itemData['rim_diameter'])) $itemData['rim_diameter'] = $diameter;
                if (empty($itemData['rim_width'])) $itemData['rim_width'] = $width;
            }
        }
        
        // 1. Try to find variant by SKU within this product
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
                
                // Update existing variant
                $variant->update([
                    'weight' => $itemData['weight'] ?? $variant->weight,
                    'lipsize' => $itemData['lipsize'] ?? $variant->lipsize,
                    'rim_width' => $itemData['rim_width'] ?? $variant->rim_width,
                    'rim_diameter' => $itemData['rim_diameter'] ?? $variant->rim_diameter,
                    'finish' => $itemData['finish'] ?? $variant->finish,
                    'uae_retail_price' => $itemData['unit_price'] ?? $variant->uae_retail_price,
                    'us_retail_price' => 0,
                ]);
                
                return $variant;
            }
        }
        
        // 2. Try by External Variant ID
        if ($externalVariantId) {
            $variant = ProductVariant::where('external_variant_id', $externalVariantId)
                ->where('external_source', $source)
                ->first();
                
            if ($variant) {
                // Update existing variant
                $variant->update([
                    'weight' => $itemData['weight'] ?? $variant->weight,
                    'lipsize' => $itemData['lipsize'] ?? $variant->lipsize,
                    'rim_width' => $itemData['rim_width'] ?? $variant->rim_width,
                    'rim_diameter' => $itemData['rim_diameter'] ?? $variant->rim_diameter,
                    'finish' => $itemData['finish'] ?? $variant->finish,
                    'uae_retail_price' => $itemData['unit_price'] ?? $variant->uae_retail_price,
                    'us_retail_price' => 0,
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
        
        // Generate SKU if missing
        if (empty($sku)) {
            if ($externalVariantId) {
                $sku = "TS-VAR-{$externalVariantId}";
                if (ProductVariant::where('sku', $sku)->exists()) {
                    $sku = "TS-VAR-{$externalVariantId}-" . uniqid();
                }
            } else {
                $sku = "TS-VAR-GEN-" . uniqid();
            }
        }

        // Create variant
        $variantData = [
            'product_id' => $product->id,
            'title' => $itemData['variant_title'] ?? 'Default',
            'sku' => $sku,
            'size' => $itemData['size'] ?? null,
            'price' => $itemData['unit_price'] ?? $product->retail_price,
            'external_variant_id' => $externalVariantId,
            'external_variant_id' => $externalVariantId,
            'external_source' => $source,
            'weight' => $itemData['weight'] ?? null,
            'lipsize' => $itemData['lipsize'] ?? null,
            'rim_width' => $itemData['rim_width'] ?? null,
            'rim_diameter' => $itemData['rim_diameter'] ?? null,
            'finish' => $itemData['finish'] ?? null,
            'uae_retail_price' => $itemData['unit_price'] ?? 0,
            'us_retail_price' => 0,
        ];
        
        $variant = ProductVariant::create($variantData);
        
        Log::info('OrderProductSync: Variant created', [
            'variant_id' => $variant->id,
            'product_id' => $product->id,
            'sku' => $sku
        ]);
        
        return $variant;
    }
    /**
     * Handle product image download and storage
     */
    protected function handleProductImage($imageUrl)
    {
        if (empty($imageUrl)) return null;
        
        try {
            // Check if it's a valid URL
            if (!filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                return $imageUrl; // Already a path?
            }
            
            // Get filename
            $filename = basename(parse_url($imageUrl, PHP_URL_PATH));
            if (empty($filename)) {
                $filename = 'product_' . uniqid() . '.jpg';
            }
            
            // Download content
            $content = @file_get_contents($imageUrl);
            if ($content === false) {
                Log::warning("OrderProductSync: Failed to download image: {$imageUrl}");
                // Return relative path even if download fails (assuming it exists on S3)
                return 'products/' . $filename;
            }
            
            // Save to public/products
            $path = 'products/' . $filename;
            Storage::disk('public')->put($path, $content);
            
            return $path;
        } catch (\Exception $e) {
            Log::error("OrderProductSync: Image download error: " . $e->getMessage());
            // Return relative path on error too
            $filename = basename(parse_url($imageUrl, PHP_URL_PATH));
            return 'products/' . ($filename ?: 'unknown.jpg');
        }
    }
}
