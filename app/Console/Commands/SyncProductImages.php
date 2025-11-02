<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Modules\Products\Models\ProductVariant;
use App\Modules\Products\Models\ProductImage;
use App\Modules\Products\Models\Brand;
use App\Modules\Products\Models\ProductModel;
use App\Modules\Products\Models\Finish;

class SyncProductImages extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'products:sync-images';

    /**
     * The console command description.
     */
    protected $description = 'Sync product variant images to product_images table';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting product images sync...');
        
        // Get all products with images
        $variants = ProductVariant::with(['product.brand', 'product.model', 'product.finish'])
            ->whereNotNull('image')
            ->where('image', '!=', '')
            ->get();
        
        $this->info("Found {$variants->count()} variants with images");
        
        $synced = 0;
        $imageGroups = [];
        
        // Group by brand+model+finish
        foreach ($variants as $variant) {
            if (!$variant->product || !$variant->product->brand || !$variant->product->model || !$variant->product->finish) {
                continue;
            }
            
            $brandId = $variant->product->brand_id;
            $modelId = $variant->product->model_id;
            $finishId = $variant->product->finish_id;
            
            $key = "{$brandId}|{$modelId}|{$finishId}";
            
            if (!isset($imageGroups[$key])) {
                $imageGroups[$key] = [
                    'brand_id' => $brandId,
                    'model_id' => $modelId,
                    'finish_id' => $finishId,
                    'images' => []
                ];
            }
            
            // Split comma-separated images
            $images = explode(',', $variant->image);
            foreach ($images as $img) {
                $img = trim($img);
                if ($img && !in_array($img, $imageGroups[$key]['images'])) {
                    $imageGroups[$key]['images'][] = $img;
                }
            }
        }
        
        $this->info("Found " . count($imageGroups) . " unique brand+model+finish combinations");
        
        // Create/update product_images records
        foreach ($imageGroups as $group) {
            try {
                $productImage = ProductImage::firstOrNew([
                    'brand_id' => $group['brand_id'],
                    'model_id' => $group['model_id'],
                    'finish_id' => $group['finish_id'],
                ]);
                
                // Assign images to image_1 through image_9
                $images = array_slice($group['images'], 0, 9);
                for ($i = 0; $i < count($images); $i++) {
                    $field = 'image_' . ($i + 1);
                    // Prepend 'products/' folder path if not already present
                    $imagePath = $images[$i];
                    if (!str_starts_with($imagePath, 'products/')) {
                        $imagePath = 'products/' . $imagePath;
                    }
                    $productImage->{$field} = $imagePath;
                }
                
                $productImage->save();
                $synced++;
                
                $brand = Brand::find($group['brand_id']);
                $model = ProductModel::find($group['model_id']);
                $finish = Finish::find($group['finish_id']);
                
                $this->line("✓ Synced: {$brand->name} {$model->name} {$finish->finish} (" . count($images) . " images)");
                
            } catch (\Exception $e) {
                $this->error("Failed to sync: " . $e->getMessage());
            }
        }
        
        $this->info("\n✅ Sync completed! {$synced} product image records created/updated.");
        
        return Command::SUCCESS;
    }
}
