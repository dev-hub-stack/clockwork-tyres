<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Brand;
use App\Models\ProductModel;
use App\Models\Finish;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProductGridController extends Controller
{
    /**
     * Display the products grid view.
     */
    public function index()
    {
        return view('products.grid');
    }

    /**
     * Get paginated products data for pqGrid.
     */
    public function getData(Request $request)
    {
        $perPage = $request->input('rpp', 20); // Records per page
        $page = $request->input('pPage', 1); // Current page
        $sortBy = $request->input('sortField', 'id');
        $sortOrder = $request->input('sortOrder', 'asc');
        
        // Build query with relationships
        $query = Product::with(['brand', 'model', 'finish']);
        
        // Apply filters
        if ($request->filled('sku')) {
            $query->where('sku', 'like', '%' . $request->sku . '%');
        }
        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }
        if ($request->filled('brand_id')) {
            $query->where('brand_id', $request->brand_id);
        }
        if ($request->filled('model_id')) {
            $query->where('model_id', $request->model_id);
        }
        if ($request->filled('finish_id')) {
            $query->where('finish_id', $request->finish_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        
        // Apply sorting
        $query->orderBy($sortBy, $sortOrder);
        
        // Get total count before pagination
        $totalRecords = $query->count();
        
        // Get paginated results
        $products = $query->skip(($page - 1) * $perPage)
                         ->take($perPage)
                         ->get();
        
        // Format data for pqGrid
        $data = $products->map(function($product) {
            return [
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'brand_id' => $product->brand_id,
                'brand_name' => $product->brand ? $product->brand->name : '',
                'model_id' => $product->model_id,
                'model_name' => $product->model ? $product->model->name : '',
                'finish_id' => $product->finish_id,
                'finish_name' => $product->finish ? $product->finish->name : '',
                'base_price' => $product->base_price ? (float)$product->base_price : null,
                'dealer_price' => $product->dealer_price ? (float)$product->dealer_price : null,
                'wholesale_price' => $product->wholesale_price ? (float)$product->wholesale_price : null,
                'weight' => $product->weight ? (float)$product->weight : null,
                'status' => $product->status,
                'status_label' => $product->status ? 'Active' : 'Inactive',
                'created_at' => $product->created_at ? $product->created_at->format('Y-m-d H:i:s') : null,
                'updated_at' => $product->updated_at ? $product->updated_at->format('Y-m-d H:i:s') : null,
            ];
        });
        
        return response()->json([
            'data' => $data,
            'totalRecords' => $totalRecords,
            'curPage' => (int)$page
        ]);
    }

    /**
     * Store a new product.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'sku' => 'required|string|max:100|unique:products,sku',
                'name' => 'required|string|max:255',
                'brand_id' => 'required|exists:brands,id',
                'model_id' => 'nullable|exists:product_models,id',
                'finish_id' => 'nullable|exists:finishes,id',
                'base_price' => 'nullable|numeric|min:0',
                'dealer_price' => 'nullable|numeric|min:0',
                'wholesale_price' => 'nullable|numeric|min:0',
                'weight' => 'nullable|numeric|min:0',
                'status' => 'required|boolean',
            ]);

            // Auto-generate slug
            $validated['slug'] = Str::slug($validated['name']);
            
            // Ensure slug is unique
            $originalSlug = $validated['slug'];
            $counter = 1;
            while (Product::where('slug', $validated['slug'])->exists()) {
                $validated['slug'] = $originalSlug . '-' . $counter;
                $counter++;
            }

            $product = Product::create($validated);
            
            return response()->json([
                'success' => true,
                'message' => 'Product created successfully',
                'data' => [
                    'id' => $product->id,
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'brand_id' => $product->brand_id,
                    'model_id' => $product->model_id,
                    'finish_id' => $product->finish_id,
                    'base_price' => $product->base_price,
                    'dealer_price' => $product->dealer_price,
                    'wholesale_price' => $product->wholesale_price,
                    'weight' => $product->weight,
                    'status' => $product->status,
                    'created_at' => $product->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $product->updated_at->format('Y-m-d H:i:s'),
                ]
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating product: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an existing product.
     */
    public function update(Request $request, $id)
    {
        try {
            $product = Product::findOrFail($id);

            $validated = $request->validate([
                'sku' => [
                    'required',
                    'string',
                    'max:100',
                    Rule::unique('products', 'sku')->ignore($id)
                ],
                'name' => 'required|string|max:255',
                'brand_id' => 'required|exists:brands,id',
                'model_id' => 'nullable|exists:product_models,id',
                'finish_id' => 'nullable|exists:finishes,id',
                'base_price' => 'nullable|numeric|min:0',
                'dealer_price' => 'nullable|numeric|min:0',
                'wholesale_price' => 'nullable|numeric|min:0',
                'weight' => 'nullable|numeric|min:0',
                'status' => 'required|boolean',
            ]);

            // Update slug if name changed
            if ($product->name !== $validated['name']) {
                $validated['slug'] = Str::slug($validated['name']);
                
                // Ensure slug is unique
                $originalSlug = $validated['slug'];
                $counter = 1;
                while (Product::where('slug', $validated['slug'])->where('id', '!=', $id)->exists()) {
                    $validated['slug'] = $originalSlug . '-' . $counter;
                    $counter++;
                }
            }

            $product->update($validated);
            
            return response()->json([
                'success' => true,
                'message' => 'Product updated successfully',
                'data' => [
                    'id' => $product->id,
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'brand_id' => $product->brand_id,
                    'model_id' => $product->model_id,
                    'finish_id' => $product->finish_id,
                    'base_price' => $product->base_price,
                    'dealer_price' => $product->dealer_price,
                    'wholesale_price' => $product->wholesale_price,
                    'weight' => $product->weight,
                    'status' => $product->status,
                    'updated_at' => $product->updated_at->format('Y-m-d H:i:s'),
                ]
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found'
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating product: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a product.
     */
    public function destroy($id)
    {
        try {
            $product = Product::findOrFail($id);
            $product->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Product deleted successfully'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting product: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Batch save (create/update) multiple products.
     */
    public function saveBatch(Request $request)
    {
        DB::beginTransaction();
        
        try {
            $addList = $request->input('addList', []);
            $updateList = $request->input('updateList', []);
            
            $results = [
                'added' => 0,
                'updated' => 0,
                'errors' => []
            ];
            
            // Process new products
            foreach ($addList as $index => $productData) {
                try {
                    $validated = validator($productData, [
                        'sku' => 'required|string|max:100|unique:products,sku',
                        'name' => 'required|string|max:255',
                        'brand_id' => 'required|exists:brands,id',
                        'model_id' => 'nullable|exists:product_models,id',
                        'finish_id' => 'nullable|exists:finishes,id',
                        'base_price' => 'nullable|numeric|min:0',
                        'dealer_price' => 'nullable|numeric|min:0',
                        'wholesale_price' => 'nullable|numeric|min:0',
                        'weight' => 'nullable|numeric|min:0',
                        'status' => 'required|boolean',
                    ])->validate();
                    
                    // Auto-generate slug
                    $validated['slug'] = Str::slug($validated['name']);
                    
                    // Ensure slug is unique
                    $originalSlug = $validated['slug'];
                    $counter = 1;
                    while (Product::where('slug', $validated['slug'])->exists()) {
                        $validated['slug'] = $originalSlug . '-' . $counter;
                        $counter++;
                    }
                    
                    Product::create($validated);
                    $results['added']++;
                } catch (\Exception $e) {
                    $results['errors'][] = "Row {$index}: " . $e->getMessage();
                }
            }
            
            // Process updates
            foreach ($updateList as $index => $productData) {
                try {
                    if (!isset($productData['id'])) {
                        $results['errors'][] = "Row {$index}: Missing product ID";
                        continue;
                    }
                    
                    $product = Product::findOrFail($productData['id']);
                    
                    $validated = validator($productData, [
                        'sku' => [
                            'required',
                            'string',
                            'max:100',
                            Rule::unique('products', 'sku')->ignore($productData['id'])
                        ],
                        'name' => 'required|string|max:255',
                        'brand_id' => 'required|exists:brands,id',
                        'model_id' => 'nullable|exists:product_models,id',
                        'finish_id' => 'nullable|exists:finishes,id',
                        'base_price' => 'nullable|numeric|min:0',
                        'dealer_price' => 'nullable|numeric|min:0',
                        'wholesale_price' => 'nullable|numeric|min:0',
                        'weight' => 'nullable|numeric|min:0',
                        'status' => 'required|boolean',
                    ])->validate();
                    
                    // Update slug if name changed
                    if ($product->name !== $validated['name']) {
                        $validated['slug'] = Str::slug($validated['name']);
                        
                        // Ensure slug is unique
                        $originalSlug = $validated['slug'];
                        $counter = 1;
                        while (Product::where('slug', $validated['slug'])->where('id', '!=', $productData['id'])->exists()) {
                            $validated['slug'] = $originalSlug . '-' . $counter;
                            $counter++;
                        }
                    }
                    
                    $product->update($validated);
                    $results['updated']++;
                } catch (\Exception $e) {
                    $results['errors'][] = "Row {$index} (ID: {$productData['id']}): " . $e->getMessage();
                }
            }
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => "Batch save completed. Added: {$results['added']}, Updated: {$results['updated']}",
                'results' => $results
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Batch save failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Batch delete multiple products.
     */
    public function deleteBatch(Request $request)
    {
        try {
            $ids = $request->input('ids', []);
            
            if (empty($ids)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No product IDs provided'
                ], 422);
            }
            
            $deletedCount = Product::whereIn('id', $ids)->delete();
            
            return response()->json([
                'success' => true,
                'message' => "{$deletedCount} product(s) deleted successfully",
                'deleted' => $deletedCount
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Batch delete failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get brands for dropdown.
     */
    public function getBrands()
    {
        try {
            $brands = Brand::where('status', 1)
                          ->orderBy('name')
                          ->get(['id', 'name']);
            
            return response()->json([
                'success' => true,
                'data' => $brands
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching brands: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get models for dropdown (optionally filtered by brand_id).
     */
    public function getModels(Request $request)
    {
        try {
            $query = ProductModel::where('status', 1);
            
            if ($request->filled('brand_id')) {
                $query->where('brand_id', $request->brand_id);
            }
            
            $models = $query->orderBy('name')
                           ->get(['id', 'name', 'brand_id']);
            
            return response()->json([
                'success' => true,
                'data' => $models
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching models: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get finishes for dropdown.
     */
    public function getFinishes()
    {
        try {
            $finishes = Finish::where('status', 1)
                             ->orderBy('name')
                             ->get(['id', 'name', 'hex_color']);
            
            return response()->json([
                'success' => true,
                'data' => $finishes
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching finishes: ' . $e->getMessage()
            ], 500);
        }
    }
}
