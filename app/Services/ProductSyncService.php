<?php

namespace App\Services;

use App\Modules\Products\Models\Brand;
use App\Modules\Products\Models\Finish;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductImage;
use App\Modules\Products\Models\ProductModel;
use App\Modules\Products\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProductSyncService
{
    /**
     * Sync a product and its variants from external data
     */
    public function syncProduct(array $data): Product
    {
        return DB::transaction(function () use ($data) {
            // 1. Find or Create Brand
            $brand = $this->syncBrand($data['brand'] ?? []);
            
            // 2. Find or Create Model
            $model = $this->syncModel($data['model'] ?? [], $brand->id);
            
            // 3. Find or Create Finish
            $finish = $this->syncFinish($data['finish'] ?? []);

            // 4. Sync Product
            Log::info('ProductSyncService: Syncing product', [
                'sku' => $data['sku'],
                'images_received' => $data['images'] ?? [],
                'images_type' => gettype($data['images'] ?? null),
                'images_count' => is_array($data['images'] ?? []) ? count($data['images']) : 0
            ]);
            
            $product = Product::updateOrCreate(
                ['sku' => $data['sku']], // Unique identifier
                [
                    'name' => $data['name'],
                    'brand_id' => $brand->id,
                    'model_id' => $model->id,
                    'finish_id' => $finish->id,
                    'price' => $data['price'] ?? 0,
                    'images' => $data['images'] ?? [], // Expecting array or JSON
                    'construction' => $data['construction'] ?? null,
                    'status' => $data['status'] ?? true,
                ]
            );

            // 5. Sync Variants
            if (isset($data['variants']) && is_array($data['variants'])) {
                $this->syncVariants($product, $data['variants']);
            }

            // 6. Sync ProductImage (for the /admin/products/images page)
            $this->syncProductImages($product, $data['images'] ?? []);

            return $product;
        });
    }

    protected function syncBrand(array $data): Brand
    {
        $name = $data['name'] ?? 'Unknown Brand';
        $slug = $data['slug'] ?? Str::slug($name);

        try {
            return Brand::firstOrCreate(
                ['name' => $name],
                ['slug' => $slug, 'is_active' => true]
            );
        } catch (\Illuminate\Database\QueryException $e) {
            // Race condition: another concurrent request inserted first
            if ($e->errorInfo[1] === 1062) {
                return Brand::where('name', $name)->first();
            }
            throw $e;
        }
    }

    protected function syncModel(array $data, int $brandId): ProductModel
    {
        $name = $data['name'] ?? 'Unknown Model';
        // ProductModel in CRM uses 'name' and has no 'slug' column
        try {
            return ProductModel::firstOrCreate(
                ['name' => $name, 'brand_id' => $brandId],
                ['is_active' => true]
            );
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->errorInfo[1] === 1062) {
                return ProductModel::where('name', $name)->where('brand_id', $brandId)->first();
            }
            throw $e;
        }
    }

    protected function syncFinish(array $data): Finish
    {
        $name = $data['name'] ?? 'Standard';
        // Finish in CRM uses 'finish' column for the name and has no 'slug'
        try {
            return Finish::firstOrCreate(
                ['finish' => $name],
                ['status' => 1]
            );
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->errorInfo[1] === 1062) {
                return Finish::where('finish', $name)->first();
            }
            throw $e;
        }
    }

    protected function syncVariants(Product $product, array $variants): void
    {
        // Get existing variant SKUs to handle deletions if needed (optional)
        // For now, we just upsert
        
        foreach ($variants as $variantData) {
            // Resolve finish for variant if specific
            $finishId = $product->finish_id;
            if (!empty($variantData['finish'])) {
                $variantFinish = $this->syncFinish(['name' => $variantData['finish']]);
                $finishId = $variantFinish->id;
            }

            ProductVariant::updateOrCreate(
                ['sku' => $variantData['sku']],
                [
                    'product_id' => $product->id,
                    'finish_id' => $finishId,
                    'size' => $variantData['size'] ?? null,
                    'bolt_pattern' => $variantData['bolt_pattern'] ?? null,
                    'hub_bore' => $variantData['hub_bore'] ?? null,
                    'offset' => $variantData['offset'] ?? null,
                    'weight' => $variantData['weight'] ?? null,
                    'backspacing' => $variantData['backspacing'] ?? null,
                    'lipsize' => $variantData['lipsize'] ?? null,
                    'max_wheel_load' => $variantData['max_wheel_load'] ?? null,
                    'rim_diameter' => $variantData['rim_diameter'] ?? null,
                    'rim_width' => $variantData['rim_width'] ?? null,
                    'price' => $variantData['price'] ?? 0,
                    'us_retail_price' => $variantData['us_retail_price'] ?? 0,
                    'uae_retail_price' => $variantData['uae_retail_price'] ?? 0,
                    'sale_price' => $variantData['sale_price'] ?? null,
                    'supplier_stock' => $variantData['supplier_stock'] ?? 0,
                    'finish' => $variantData['finish'] ?? null, // Snapshot of finish name
                    // 'construction' is a product-level attribute, not variant-level in CRM
                ]
            );
        }
    }

    protected function syncProductImages(Product $product, array $images): void
    {
        if (empty($images)) {
            return;
        }

        // Build data for ProductImage table (stores up to 9 images)
        $imageData = [
            'brand_id' => $product->brand_id,
            'model_id' => $product->model_id,
            'finish_id' => $product->finish_id,
        ];

        // Map images to image_1, image_2, etc.
        foreach ($images as $index => $imagePath) {
            if ($index >= 9) break; // Max 9 images
            $imageData["image_" . ($index + 1)] = $imagePath;
        }

        // Update or create ProductImage record
        ProductImage::updateOrCreate(
            [
                'brand_id' => $product->brand_id,
                'model_id' => $product->model_id,
                'finish_id' => $product->finish_id,
            ],
            $imageData
        );
    }
}
