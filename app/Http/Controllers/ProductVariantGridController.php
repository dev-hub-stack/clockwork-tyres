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
        $variants = ProductVariant::with(['product.brand', 'product.model', 'finish'])
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
                    'finish' => $variant->finish ? $variant->finish->name : '',
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
                    'images' => $variant->product ? $variant->product->images : '',
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
        
        if ($variant->finish) {
            $parts[] = $variant->finish->name;
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
}
