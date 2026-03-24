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
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ProductSyncService
{
    private ?array $productColumns = null;

    private ?array $variantColumns = null;

    /**
     * Sync a product and its variants from external data
     */
    public function syncProduct(array $data): Product
    {
        return DB::transaction(function () use ($data) {
            $source = $this->normalizeSource($data['external_source'] ?? 'retail');
            $images = $this->normalizeImages($data['images'] ?? null);

            // 1. Find or Create Brand
            $brand = $this->syncBrand($data['brand'] ?? []);
            
            // 2. Find or Create Model
            $model = $this->syncModel($data['model'] ?? [], $brand->id);
            
            // 3. Find or Create Finish
            $finish = $this->syncFinish($data['finish'] ?? []);

            // 4. Sync Product
            Log::info('ProductSyncService: Syncing product', [
                'sku' => $data['sku'],
                'images_received' => $images,
                'images_type' => gettype($data['images'] ?? null),
                'images_count' => count($images),
            ]);

            $productColumns = $this->getProductColumns();
            $identity = $this->buildProductIdentity($data, $source, $productColumns);
            $productAttributes = $this->buildProductAttributes($data, $source, $brand->id, $model->id, $finish->id, $finish->finish, $productColumns, $brand->name, $model->name);

            $product = Product::query()->firstOrNew($identity);
            $product->forceFill($productAttributes);
            $product->save();

            // 5. Sync Variants
            if (isset($data['variants']) && is_array($data['variants'])) {
                $this->syncVariants($product, $data['variants'], $source);
            }

            // 6. Sync ProductImage (for the /admin/products/images page)
            $this->syncProductImages($product, $images);

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
                ['slug' => $slug, 'status' => 1]
            );
        } catch (\Illuminate\Database\QueryException $e) {
            // Race condition: another concurrent request inserted first
            if ($e->errorInfo[1] === 1062) {
                $existingBrand = Brand::query()
                    ->where('name', $name)
                    ->orWhere('slug', $slug)
                    ->first();

                if ($existingBrand) {
                    return $existingBrand;
                }

                return Brand::create([
                    'name' => $name,
                    'slug' => $slug,
                    'status' => 1,
                ]);
            }
            throw $e;
        }
    }

    protected function syncModel(array $data, int $brandId): ProductModel
    {
        $name = $data['name'] ?? 'Unknown Model';
        // 'models' table has no brand_id column — name is the unique key
        try {
            return ProductModel::firstOrCreate(
                ['name' => $name],
                []
            );
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->errorInfo[1] === 1062) {
                return ProductModel::where('name', $name)->first();
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
                ['finish' => $name]
            );
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->errorInfo[1] === 1062) {
                return Finish::where('finish', $name)->first();
            }
            throw $e;
        }
    }

    protected function syncVariants(Product $product, array $variants, string $source): void
    {
        // Get existing variant SKUs to handle deletions if needed (optional)
        // For now, we just upsert
        $variantColumns = $this->getVariantColumns();
        
        foreach ($variants as $variantData) {
            // Resolve finish for variant if specific
            $finishId = $product->finish_id;
            if (!empty($variantData['finish'])) {
                $variantFinish = $this->syncFinish(['name' => $variantData['finish']]);
                $finishId = $variantFinish->id;
            }

            $variantIdentity = $this->buildVariantIdentity($variantData, $source, $variantColumns);
            $variantAttributes = $this->buildVariantAttributes($variantData, $source, $product->id, $finishId, $variantColumns);

            $variant = ProductVariant::query()->firstOrNew($variantIdentity);
            $variant->fill($variantAttributes);
            $variant->save();
        }
    }

    private function getProductColumns(): array
    {
        return $this->productColumns ??= Schema::getColumnListing('products');
    }

    private function getVariantColumns(): array
    {
        return $this->variantColumns ??= Schema::getColumnListing('product_variants');
    }

    private function buildProductIdentity(array $data, string $source, array $columns): array
    {
        if (
            ! empty($data['external_product_id'])
            && in_array('external_product_id', $columns, true)
            && in_array('external_source', $columns, true)
        ) {
            return [
                'external_product_id' => (string) $data['external_product_id'],
                'external_source' => $source,
            ];
        }

        return ['sku' => $data['sku']];
    }

    private function buildProductAttributes(
        array $data,
        string $source,
        int $brandId,
        int $modelId,
        int $finishId,
        string $finishName,
        array $columns,
        string $brandName,
        string $modelName,
    ): array {
        $attributes = [
            'name' => $data['name'],
            'sku' => $data['sku'],
            'product_full_name' => trim(implode(' ', array_filter([
                $brandName,
                $modelName,
                $finishName,
            ]))),
            'brand_id' => $brandId,
            'model_id' => $modelId,
            'price' => $data['price'] ?? 0,
            'images' => $data['images'] ?? [],
            'construction' => $data['construction'] ?? null,
            'status' => $data['status'] ?? true,
        ];

        if (in_array('finish_id', $columns, true)) {
            $attributes['finish_id'] = $finishId;
        } elseif (in_array('finish_iid', $columns, true)) {
            $attributes['finish_iid'] = $finishId;
        }

        if (in_array('external_product_id', $columns, true)) {
            $attributes['external_product_id'] = $data['external_product_id'] ?? null;
        }

        if (in_array('external_source', $columns, true)) {
            $attributes['external_source'] = $source;
        }

        return array_intersect_key($attributes, array_flip($columns));
    }

    private function buildVariantIdentity(array $variantData, string $source, array $columns): array
    {
        if (
            ! empty($variantData['external_variant_id'])
            && in_array('external_variant_id', $columns, true)
            && in_array('external_source', $columns, true)
        ) {
            return [
                'external_variant_id' => (string) $variantData['external_variant_id'],
                'external_source' => $this->normalizeSource($variantData['external_source'] ?? $source),
            ];
        }

        return ['sku' => $variantData['sku']];
    }

    private function buildVariantAttributes(array $variantData, string $source, int $productId, int $finishId, array $columns): array
    {
        $attributes = [
            'product_id' => $productId,
            'sku' => $variantData['sku'],
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
            'finish' => $variantData['finish'] ?? null,
            'external_variant_id' => $variantData['external_variant_id'] ?? null,
            'external_source' => $this->normalizeSource($variantData['external_source'] ?? $source),
        ];

        if (in_array('finish_id', $columns, true)) {
            $attributes['finish_id'] = $finishId;
        }

        return array_intersect_key($attributes, array_flip($columns));
    }

    private function normalizeSource(?string $source): string
    {
        return match (strtolower((string) $source)) {
            'tunerstop', 'tunerstop_admin', 'retail' => 'retail',
            'wholesale' => 'wholesale',
            default => 'retail',
        };
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

    private function normalizeImages(mixed $images): array
    {
        if (is_null($images) || $images === '') {
            return [];
        }

        if (is_string($images)) {
            return [$images];
        }

        if (! is_array($images)) {
            return [];
        }

        return array_values(array_filter($images, static fn ($image) => filled($image)));
    }
}
