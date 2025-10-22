<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductVariant;
use App\Modules\Products\Models\Brand;
use App\Modules\Products\Models\ProductModel;
use App\Modules\Products\Models\Finish;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductVariantGridController extends Controller
{
    /**
     * Display the product variants grid view with all data
     */
    public function index()
    {
        // Load ALL product variants with relationships
        $variants = ProductVariant::with(['product.brand', 'product.model', 'finishRelation'])
            ->get()
            ->map(function($variant) {
                return [
                    'id' => $variant->id,
                    'product_id' => $variant->product_id,
                    'sku' => $variant->sku,
                    'name' => $variant->product ? $variant->product->name : '',
                    'product_full_name' => $this->getProductFullName($variant),
                    'brand' => $variant->product && $variant->product->brand ? $variant->product->brand->name : '',
                    'brand_id' => $variant->product ? $variant->product->brand_id : null,
                    'model' => $variant->product && $variant->product->model ? $variant->product->model->name : '',
                    'model_id' => $variant->product ? $variant->product->model_id : null,
                    'supplier_stock' => $variant->supplier_stock ?? 0,
                    'finish' => $variant->finishRelation ? $variant->finishRelation->finish : ($variant->finish ?? ''),
                    'finish_id' => $variant->finish_id,
                    'construction' => $variant->product ? $variant->product->construction : '',
                    'rim_width' => $variant->rim_width,
                    'rim_diameter' => $variant->rim_diameter,
                    'size' => $variant->size,
                    'bolt_pattern' => $variant->bolt_pattern,
                    'hub_bore' => $variant->hub_bore,
                    'offset' => $variant->offset,
                    'backspacing' => $variant->backspacing,
                    'max_wheel_load' => $variant->max_wheel_load,
                    'weight' => $variant->weight,
                    'lipsize' => $variant->lipsize,
                    'us_retail_price' => $variant->us_retail_price,
                    'uae_retail_price' => $variant->uae_retail_price,
                    'sale_price' => $variant->sale_price,
                    'clearance_corner' => $variant->clearance_corner ? 1 : 0,
                    'images' => $variant->image ?? '', // Column name is 'image' in database
                ];
            });

        return view('products.grid', ['products_data' => $variants]);
    }

    /**
     * Generate full product name
     */
    private function getProductFullName($variant)
    {
        if (!$variant->product) return '';
        
        $parts = [];
        
        if ($variant->product->brand) {
            $parts[] = $variant->product->brand->name;
        }
        
        if ($variant->product->model) {
            $parts[] = $variant->product->model->name;
        }
        
        if ($variant->size) {
            $parts[] = $variant->size;
        }
        
        if ($variant->finishRelation) {
            $parts[] = $variant->finishRelation->finish;
        } elseif ($variant->finish) {
            $parts[] = $variant->finish;
        }
        
        return implode(' ', $parts);
    }

    /**
     * Batch save (create/update) product variants
     */
    public function saveBatch(Request $request)
    {
        DB::beginTransaction();
        
        try {
            $changes = $request->input('list', []);
            $addList = $changes['addList'] ?? [];
            $updateList = $changes['updateList'] ?? [];
            $deleteList = $changes['deleteList'] ?? [];
            
            $results = [
                'addList' => [],
                'updateList' => [],
                'deleteList' => [],
                'errors' => []
            ];
            
            // Process additions
            foreach ($addList as $index => $data) {
                try {
                    // Create product variant
                    $variant = ProductVariant::create([
                        'product_id' => $data['product_id'] ?? null,
                        'sku' => $data['sku'],
                        'finish_id' => $data['finish_id'] ?? null,
                        'size' => $data['size'] ?? null,
                        'bolt_pattern' => $data['bolt_pattern'] ?? null,
                        'hub_bore' => $data['hub_bore'] ?? null,
                        'offset' => $data['offset'] ?? null,
                        'weight' => $data['weight'] ?? null,
                        'backspacing' => $data['backspacing'] ?? null,
                        'lipsize' => $data['lipsize'] ?? null,
                        'max_wheel_load' => $data['max_wheel_load'] ?? null,
                        'rim_diameter' => $data['rim_diameter'] ?? null,
                        'rim_width' => $data['rim_width'] ?? null,
                        'us_retail_price' => $data['us_retail_price'] ?? null,
                        'uae_retail_price' => $data['uae_retail_price'] ?? null,
                        'sale_price' => $data['sale_price'] ?? null,
                        'clearance_corner' => $data['clearance_corner'] ?? 0,
                        'supplier_stock' => $data['supplier_stock'] ?? 0,
                    ]);
                    
                    $results['addList'][] = array_merge($data, ['id' => $variant->id]);
                } catch (\Exception $e) {
                    $results['errors'][] = "Row {$index}: " . $e->getMessage();
                }
            }
            
            // Process updates
            foreach ($updateList as $index => $data) {
                try {
                    if (!isset($data['id'])) {
                        $results['errors'][] = "Row {$index}: Missing variant ID";
                        continue;
                    }
                    
                    $variant = ProductVariant::findOrFail($data['id']);
                    
                    $variant->update([
                        'sku' => $data['sku'],
                        'finish_id' => $data['finish_id'] ?? null,
                        'size' => $data['size'] ?? null,
                        'bolt_pattern' => $data['bolt_pattern'] ?? null,
                        'hub_bore' => $data['hub_bore'] ?? null,
                        'offset' => $data['offset'] ?? null,
                        'weight' => $data['weight'] ?? null,
                        'backspacing' => $data['backspacing'] ?? null,
                        'lipsize' => $data['lipsize'] ?? null,
                        'max_wheel_load' => $data['max_wheel_load'] ?? null,
                        'rim_diameter' => $data['rim_diameter'] ?? null,
                        'rim_width' => $data['rim_width'] ?? null,
                        'us_retail_price' => $data['us_retail_price'] ?? null,
                        'uae_retail_price' => $data['uae_retail_price'] ?? null,
                        'sale_price' => $data['sale_price'] ?? null,
                        'clearance_corner' => $data['clearance_corner'] ?? 0,
                        'supplier_stock' => $data['supplier_stock'] ?? 0,
                    ]);
                    
                    $results['updateList'][] = $data;
                } catch (\Exception $e) {
                    $results['errors'][] = "Row {$index} (ID: {$data['id']}): " . $e->getMessage();
                }
            }
            
            DB::commit();
            
            // Auto-sync product images after save (Tunerstop pattern)
            try {
                \Artisan::call('products:sync-images');
                \Log::info('Product images synced automatically after batch save');
            } catch (\Exception $e) {
                \Log::error('Auto-sync images failed: ' . $e->getMessage());
            }
            
            return response()->json($results);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'addList' => [],
                'updateList' => [],
                'deleteList' => [],
                'errors' => ['Batch save failed: ' . $e->getMessage()]
            ], 500);
        }
    }

    /**
     * Batch delete product variants
     */
    public function deleteBatch(Request $request)
    {
        try {
            $ids = $request->input('deleteIds', []);
            
            if (empty($ids)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No variant IDs provided'
                ], 422);
            }
            
            ProductVariant::whereIn('id', $ids)->delete();
            
            return response()->json([
                'success' => true,
                'message' => count($ids) . ' variant(s) deleted successfully',
                'deleteList' => $ids
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Bulk delete failed: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Bulk import products from Excel/CSV
     * Optimized for large imports (5000+ products)
     */
    public function bulkImport(Request $request)
    {
        $request->validate([
            'importFile' => 'required|file|mimes:csv,xlsx,xls|max:51200' // 50MB max
        ]);
        
        try {
            // Increase limits for large imports
            ini_set('memory_limit', '1024M'); // 1GB memory
            ini_set('max_execution_time', '600'); // 10 minutes
            set_time_limit(600);
            
            $file = $request->file('importFile');
            $data = \Maatwebsite\Excel\Facades\Excel::toArray([], $file);
            
            if (empty($data) || empty($data[0])) {
                return redirect()->back()->with('error', 'File is empty or invalid format');
            }
            
            $rows = $data[0];
            $headers = array_shift($rows); // Remove header row
            
            // Normalize headers (lowercase, trim)
            $headers = array_map(function($h) {
                return strtolower(trim($h));
            }, $headers);
            
            $totalRows = count($rows);
            $imported = 0;
            $updated = 0;
            $errors = [];
            $chunkSize = 500; // Process 500 at a time
            
            // Pre-load existing data to reduce queries
            $existingBrands = Brand::pluck('id', 'name')->toArray();
            $existingModels = ProductModel::pluck('id', 'name')->toArray();
            $existingFinishes = Finish::pluck('id', 'finish')->toArray();
            $existingProducts = Product::pluck('id', 'sku')->toArray();
            $existingVariants = ProductVariant::pluck('id', 'sku')->toArray();
            
            // Process in chunks
            $chunks = array_chunk($rows, $chunkSize);
            
            foreach ($chunks as $chunkIndex => $chunk) {
                DB::beginTransaction();
                
                try {
                    foreach ($chunk as $index => $row) {
                        $globalIndex = ($chunkIndex * $chunkSize) + $index;
                        
                        try {
                            // Skip empty rows
                            if (empty(array_filter($row))) continue;
                            
                            // Map row to associative array
                            $rowData = array_combine($headers, $row);
                            
                            // Validate required fields
                            if (empty($rowData['sku'])) {
                                $errors[] = "Row " . ($globalIndex + 2) . ": SKU is required";
                                continue;
                            }
                            
                            // Find or create Brand (use cache)
                            $brandId = null;
                            if (!empty($rowData['brand'])) {
                                $brandName = trim($rowData['brand']);
                                if (!isset($existingBrands[$brandName])) {
                                    $brand = Brand::create([
                                        'name' => $brandName,
                                        'slug' => Str::slug($brandName)
                                    ]);
                                    $existingBrands[$brandName] = $brand->id;
                                }
                                $brandId = $existingBrands[$brandName];
                            }
                            
                            // Find or create Model (use cache)
                            $modelId = null;
                            if (!empty($rowData['model'])) {
                                $modelName = trim($rowData['model']);
                                if (!isset($existingModels[$modelName])) {
                                    $model = ProductModel::create(['name' => $modelName]);
                                    $existingModels[$modelName] = $model->id;
                                }
                                $modelId = $existingModels[$modelName];
                            }
                            
                            // Find or create Finish (use cache)
                            $finishId = null;
                            if (!empty($rowData['finish'])) {
                                $finishName = trim($rowData['finish']);
                                if (!isset($existingFinishes[$finishName])) {
                                    $finish = Finish::create(['finish' => $finishName]);
                                    $existingFinishes[$finishName] = $finish->id;
                                }
                                $finishId = $existingFinishes[$finishName];
                            }
                            
                            $sku = trim($rowData['sku']);
                            
                            // Find or create Product
                            $productData = [
                                'name' => $sku,
                                'brand_id' => $brandId,
                                'model_id' => $modelId,
                                'finish_id' => $finishId,
                                'construction' => $rowData['construction'] ?? null,
                                'price' => $rowData['us retail price'] ?? 0,
                                'status' => 1,
                            ];
                            
                            if (isset($existingProducts[$sku])) {
                                Product::where('id', $existingProducts[$sku])->update($productData);
                                $productId = $existingProducts[$sku];
                                $updated++;
                            } else {
                                $product = Product::create(array_merge(['sku' => $sku], $productData));
                                $existingProducts[$sku] = $product->id;
                                $productId = $product->id;
                                $imported++;
                            }
                            
                            // Create or update ProductVariant
                            $variantData = [
                                'product_id' => $productId,
                                'finish_id' => $finishId,
                                'finish' => $rowData['finish'] ?? null,
                                'rim_width' => !empty($rowData['rim width']) ? (float)$rowData['rim width'] : null,
                                'rim_diameter' => !empty($rowData['rim diameter']) ? (float)$rowData['rim diameter'] : null,
                                'size' => $rowData['size'] ?? null,
                                'bolt_pattern' => $rowData['bolt pattern'] ?? null,
                                'hub_bore' => !empty($rowData['hub bore']) ? (float)$rowData['hub bore'] : null,
                                'offset' => $rowData['offset'] ?? null,
                                'backspacing' => $rowData['warranty'] ?? null,
                                'max_wheel_load' => $rowData['max wheel load'] ?? null,
                                'weight' => $rowData['weight'] ?? null,
                                'lipsize' => $rowData['lipsize'] ?? null,
                                'us_retail_price' => !empty($rowData['us retail price']) ? (float)$rowData['us retail price'] : 0,
                                'uae_retail_price' => !empty($rowData['uae retail price']) ? (float)$rowData['uae retail price'] : 0,
                                'sale_price' => !empty($rowData['sale price']) ? (float)$rowData['sale price'] : 0,
                                'clearance_corner' => !empty($rowData['clearance corner']) ? (int)$rowData['clearance corner'] : 0,
                                'supplier_stock' => !empty($rowData['supplier stock']) ? (int)$rowData['supplier stock'] : 0,
                            ];
                            
                            // CRITICAL: Collect image filenames from image1-image9 columns
                            $images = [];
                            for ($i = 1; $i <= 9; $i++) {
                                $imageKey = 'image' . $i;
                                if (!empty($rowData[$imageKey])) {
                                    $images[] = trim($rowData[$imageKey]);
                                }
                            }
                            // Store as comma-separated string in 'image' column (singular, like Tunerstop)
                            if (!empty($images)) {
                                $variantData['image'] = implode(',', $images);
                            }
                            
                            if (isset($existingVariants[$sku])) {
                                ProductVariant::where('id', $existingVariants[$sku])->update($variantData);
                            } else {
                                $variant = ProductVariant::create(array_merge(['sku' => $sku], $variantData));
                                $existingVariants[$sku] = $variant->id;
                            }
                            
                            
                        } catch (\Exception $e) {
                            $errors[] = "Row " . ($globalIndex + 2) . ": " . $e->getMessage();
                        }
                    }
                    
                    DB::commit();
                    
                } catch (\Exception $e) {
                    DB::rollBack();
                    $errors[] = "Chunk " . ($chunkIndex + 1) . " failed: " . $e->getMessage();
                }
            }
            
            $total = $imported + $updated;
            $message = "✅ Successfully processed {$total} products! ({$imported} new, {$updated} updated from {$totalRows} rows)";
            if (!empty($errors)) {
                $message .= " ⚠️ " . count($errors) . " errors occurred.";
            }
            
            // Auto-sync product images after bulk import (Tunerstop pattern)
            try {
                \Artisan::call('products:sync-images');
                \Log::info('Product images synced automatically after bulk import');
                $message .= " 🖼️ Product images synced!";
            } catch (\Exception $e) {
                \Log::error('Auto-sync images failed: ' . $e->getMessage());
            }
            
            return redirect()->back()
                ->with('success', $message)
                ->with('import_errors', array_slice($errors, 0, 100)); // Show first 100 errors
            
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Import failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Bulk upload product images from ZIP to S3
     */
    public function bulkImages(Request $request)
    {
        $request->validate([
            'imagesZip' => 'required|file|mimes:zip|max:512000' // 500MB max
        ]);
        
        try {
            $zipFile = $request->file('imagesZip');
            $zip = new \ZipArchive;
            
            if ($zip->open($zipFile->getPathname()) !== true) {
                return response()->json(['success' => false, 'error' => 'Failed to open ZIP file'], 400);
            }
            
            $matched = 0;
            $unmatched = [];
            $uploaded = [];
            $errors = [];
            
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $filename = $zip->getNameIndex($i);
                
                // Skip directories and hidden files
                if (substr($filename, -1) == '/' || strpos($filename, '__MACOSX') !== false || strpos($filename, '.DS_Store') !== false) {
                    continue;
                }
                
                // Extract just the filename without path
                $basename = basename($filename);
                
                // Skip if not an image
                $ext = strtolower(pathinfo($basename, PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
                    continue;
                }
                
                // Extract SKU from filename (remove extension and any suffix like -1, -2)
                $sku = pathinfo($basename, PATHINFO_FILENAME);
                // Remove trailing numbers like -1, -2, etc. to match SKU
                $sku = preg_replace('/-\d+$/', '', $sku);
                
                // Find product variant by SKU
                $variant = ProductVariant::with('product.brand', 'product.model')->where('sku', $sku)->first();
                
                if ($variant) {
                    try {
                        // Extract file content from ZIP
                        $fileContent = $zip->getFromIndex($i);
                        
                        if ($fileContent === false) {
                            $errors[] = "Failed to extract {$basename} from ZIP";
                            continue;
                        }
                        
                        // Generate S3 path: products/{brand}/{model}/{sku}/{filename}
                        $brand = $variant->product && $variant->product->brand ? Str::slug($variant->product->brand->name) : 'unknown-brand';
                        $model = $variant->product && $variant->product->model ? Str::slug($variant->product->model->name) : 'unknown-model';
                        $s3Path = "products/{$brand}/{$model}/{$sku}/{$basename}";
                        
                        // Upload to S3
                        \Storage::disk('s3')->put($s3Path, $fileContent, 'public');
                        
                        // Get full S3 URL
                        $s3Url = \Storage::disk('s3')->url($s3Path);
                        
                        // Update product variant images field (comma-separated URLs)
                        $currentImages = $variant->images ?? '';
                        $imagesArray = $currentImages ? explode(',', $currentImages) : [];
                        
                        // Add new image URL if not already present
                        if (!in_array($s3Url, $imagesArray)) {
                            $imagesArray[] = $s3Url;
                            $variant->images = implode(',', array_filter($imagesArray));
                            $variant->save();
                        }
                        
                        $matched++;
                        $uploaded[] = [
                            'filename' => $basename,
                            'sku' => $sku,
                            'url' => $s3Url
                        ];
                        
                    } catch (\Exception $e) {
                        $errors[] = "Error uploading {$basename}: " . $e->getMessage();
                    }
                    
                } else {
                    $unmatched[] = [
                        'filename' => $basename,
                        'sku' => $sku,
                        'reason' => 'No product variant found with this SKU'
                    ];
                }
            }
            
            $zip->close();
            
            return response()->json([
                'success' => true,
                'message' => "Successfully uploaded {$matched} images to S3",
                'matched' => $matched,
                'unmatched_count' => count($unmatched),
                'errors_count' => count($errors),
                'uploaded' => $uploaded,
                'unmatched' => $unmatched,
                'errors' => $errors
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Image upload failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
