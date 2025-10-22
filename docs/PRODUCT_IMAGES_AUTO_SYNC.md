# Product Images Auto-Sync Implementation

## Overview
This document explains how product images are automatically synchronized when products are uploaded.

## Current Manual Sync
- **Command**: `php artisan products:sync-images`
- **Function**: Syncs images from `product_variants.image` to `product_images` table
- **Usage**: Run manually after bulk importing products

## Automatic Sync Options

### Option 1: Event Listener (Recommended for Tunerstop Pattern)
When products are saved/updated, automatically sync their images.

**Implementation Steps:**

1. **Create Event Listener**
```php
// app/Listeners/SyncProductImagesListener.php
namespace App\Listeners;

use App\Events\ProductsSaved;
use App\Console\Commands\SyncProductImages;
use Illuminate\Support\Facades\Artisan;

class SyncProductImagesListener
{
    public function handle(ProductsSaved $event)
    {
        // Run sync command
        Artisan::call('products:sync-images');
    }
}
```

2. **Register in EventServiceProvider**
```php
protected $listen = [
    \App\Events\ProductsSaved::class => [
        \App\Listeners\SyncProductImagesListener::class,
    ],
];
```

3. **Dispatch Event After Bulk Import**
In `ProductVariantGridController@saveBatch`:
```php
event(new \App\Events\ProductsSaved($affectedBrands));
```

### Option 2: Model Observer
Automatically sync when a ProductVariant is created/updated.

```php
// app/Observers/ProductVariantObserver.php
namespace App\Observers;

use App\Modules\Products\Models\ProductVariant;
use App\Modules\Products\Models\ProductImage;

class ProductVariantObserver
{
    public function saved(ProductVariant $variant)
    {
        if ($variant->image && $variant->brand_id && $variant->model_id && $variant->finish_id) {
            $this->syncImages($variant);
        }
    }

    private function syncImages(ProductVariant $variant)
    {
        // Get all variants with same brand+model+finish
        $variants = ProductVariant::where([
            'brand_id' => $variant->brand_id,
            'model_id' => $variant->model_id,
            'finish_id' => $variant->finish_id,
        ])->get();

        // Extract images
        $allImages = [];
        foreach ($variants as $v) {
            if ($v->image) {
                $images = array_map('trim', explode(',', $v->image));
                $allImages = array_merge($allImages, $images);
            }
        }
        
        $allImages = array_unique(array_filter($allImages));

        // Update or create ProductImage
        $productImage = ProductImage::updateOrCreate(
            [
                'brand_id' => $variant->brand_id,
                'model_id' => $variant->model_id,
                'finish_id' => $variant->finish_id,
            ],
            []
        );

        // Set images
        for ($i = 0; $i < 9; $i++) {
            $field = 'image_' . ($i + 1);
            $productImage->{$field} = $allImages[$i] ?? null;
        }
        
        $productImage->save();
    }
}
```

Register in `AppServiceProvider`:
```php
public function boot()
{
    ProductVariant::observe(ProductVariantObserver::class);
}
```

### Option 3: After Bulk Import Hook (Current Best Practice)
Call sync command automatically after bulk import completes.

In `ProductVariantGridController@saveBatch` or bulk import method:
```php
// After successful bulk import
Artisan::call('products:sync-images');
```

## Recommended Approach for Tunerstop Pattern

**Use Option 3** - Add automatic sync after bulk operations:

1. In `ProductVariantGridController@saveBatch` (after saving products):
```php
// Sync product images automatically
try {
    Artisan::call('products:sync-images');
    \Log::info('Product images synced automatically after save');
} catch (\Exception $e) {
    \Log::error('Auto-sync images failed: ' . $e->getMessage());
}
```

2. In bulk import controller (after CSV import):
```php
// After import completes successfully
Artisan::call('products:sync-images');
```

## Manual Sync Command
For manual sync or troubleshooting:
```bash
php artisan products:sync-images
```

## Future Enhancements
1. **Real-time sync**: Use queue jobs for large syncs
2. **Incremental sync**: Only sync changed products
3. **Image validation**: Verify images exist in S3 before syncing
4. **Batch processing**: Process in chunks for better performance
