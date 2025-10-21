<?php

/**
 * Test Script for Product Model
 * 
 * This script tests CRUD operations for the Product model
 * Run: php test_products.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Brand;
use App\Modules\Products\Models\ProductModel;
use App\Modules\Products\Models\Finish;
use App\Modules\Products\Models\ProductVariant;

echo "=== PRODUCT MODEL TEST ===\n\n";

// First, ensure we have required related records
echo "0. Setting up test data (Brand, Model, Finish)...\n";
try {
    $brand = Brand::firstOrCreate(
        ['name' => 'Test Brand for Products'],
        ['slug' => 'test-brand-products', 'status' => 1]
    );
    
    $model = ProductModel::firstOrCreate(
        ['name' => 'Test Model for Products']
    );
    
    $finish = Finish::firstOrCreate(
        ['finish' => 'Test Finish for Products']
    );
    
    echo "   ✓ Brand: {$brand->name} (ID: {$brand->id})\n";
    echo "   ✓ Model: {$model->name} (ID: {$model->id})\n";
    echo "   ✓ Finish: {$finish->finish} (ID: {$finish->id})\n\n";
} catch (Exception $e) {
    echo "   ✗ Error setting up test data: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Test 1: Create Products
echo "1. Creating new products...\n";
try {
    $product1 = Product::create([
        'name' => 'Test Wheel Alpha',
        'sku' => 'TWA-' . time(),
        'price' => 299.99,
        'brand_id' => $brand->id,
        'model_id' => $model->id,
        'finish_id' => $finish->id,
        'construction' => 'Cast',
        'images' => json_encode(['wheel1.jpg', 'wheel2.jpg']),
        'status' => 1
    ]);
    echo "   ✓ Product created: {$product1->name} (ID: {$product1->id})\n";
    echo "   ✓ SKU: {$product1->sku}\n";
    echo "   ✓ Price: \${$product1->price}\n";

    $product2 = Product::create([
        'name' => 'Test Wheel Beta',
        'sku' => 'TWB-' . time(),
        'price' => 399.99,
        'brand_id' => $brand->id,
        'construction' => 'Forged',
        'status' => 1
    ]);
    echo "   ✓ Product created: {$product2->name} (ID: {$product2->id})\n\n";
} catch (Exception $e) {
    echo "   ✗ Error creating product: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Test 2: Read Product with Relationships
echo "2. Reading product with relationships...\n";
try {
    $foundProduct = Product::with(['brand', 'model', 'finish'])->find($product1->id);
    echo "   ✓ Found product: {$foundProduct->name}\n";
    echo "   ✓ Brand: {$foundProduct->brand->name}\n";
    echo "   ✓ Model: " . ($foundProduct->model ? $foundProduct->model->name : 'N/A') . "\n";
    echo "   ✓ Finish: " . ($foundProduct->finish ? $foundProduct->finish->finish : 'N/A') . "\n";
    echo "   ✓ Construction: {$foundProduct->construction}\n";
    echo "   ✓ Status: " . ($foundProduct->status ? 'Active' : 'Inactive') . "\n\n";
} catch (Exception $e) {
    echo "   ✗ Error reading product: " . $e->getMessage() . "\n\n";
}

// Test 3: List Products
echo "3. Listing all products...\n";
try {
    $products = Product::with('brand')->get();
    echo "   ✓ Total products in database: {$products->count()}\n";
    foreach ($products->take(5) as $product) {
        echo "     - {$product->name} | {$product->sku} | \${$product->price} | {$product->brand->name}\n";
    }
    echo "\n";
} catch (Exception $e) {
    echo "   ✗ Error listing products: " . $e->getMessage() . "\n\n";
}

// Test 4: Update Product
echo "4. Updating product...\n";
try {
    $product1->update([
        'price' => 349.99,
        'construction' => 'Flow Forged',
        'status' => 1
    ]);
    $product1->refresh();
    echo "   ✓ Product updated successfully\n";
    echo "   ✓ New price: \${$product1->price}\n";
    echo "   ✓ New construction: {$product1->construction}\n\n";
} catch (Exception $e) {
    echo "   ✗ Error updating product: " . $e->getMessage() . "\n\n";
}

// Test 5: Search/Filter Products
echo "5. Testing search and filter...\n";
try {
    $searchResults = Product::where('name', 'like', '%Alpha%')->get();
    echo "   ✓ Search for 'Alpha' found {$searchResults->count()} result(s)\n";
    
    $activeProducts = Product::where('status', 1)->count();
    echo "   ✓ Active products: {$activeProducts}\n";
    
    $castProducts = Product::where('construction', 'Cast')->count();
    echo "   ✓ Cast construction: {$castProducts}\n";
    
    $byBrand = Product::where('brand_id', $brand->id)->count();
    echo "   ✓ Products by test brand: {$byBrand}\n\n";
} catch (Exception $e) {
    echo "   ✗ Error searching products: " . $e->getMessage() . "\n\n";
}

// Test 6: Test Relationships - Product Variants
echo "6. Testing product variants relationship...\n";
try {
    if (method_exists($product1, 'variants')) {
        $variants = $product1->variants;
        echo "   ✓ Product has {$variants->count()} variant(s)\n";
    } else {
        echo "   ⚠ Variants relationship not defined yet\n";
    }
    
    if (method_exists($product1, 'images')) {
        $images = $product1->images;
        echo "   ✓ Product has {$images->count()} image(s)\n";
    } else {
        echo "   ⚠ Images relationship not defined yet\n";
    }
    echo "\n";
} catch (Exception $e) {
    echo "   ⚠ Relationship test: " . $e->getMessage() . "\n\n";
}

// Test 7: Unique SKU Validation
echo "7. Testing unique SKU constraint...\n";
try {
    $duplicate = Product::create([
        'name' => 'Duplicate Product',
        'sku' => $product1->sku, // Duplicate SKU
        'price' => 199.99,
        'brand_id' => $brand->id
    ]);
    echo "   ⚠ Warning: Duplicate SKU created (unique constraint may be missing)\n\n";
    $duplicate->delete();
} catch (Exception $e) {
    echo "   ✓ Unique SKU constraint working: " . $e->getMessage() . "\n\n";
}

// Test 8: Test Image JSON Field
echo "8. Testing images JSON field...\n";
try {
    $imagesArray = json_decode($product1->images);
    if (is_array($imagesArray)) {
        echo "   ✓ Images parsed as array: " . count($imagesArray) . " image(s)\n";
        foreach ($imagesArray as $img) {
            echo "     - {$img}\n";
        }
    }
    echo "\n";
} catch (Exception $e) {
    echo "   ⚠ Images JSON test: " . $e->getMessage() . "\n\n";
}

// Test 9: Delete Product
echo "9. Testing deletion...\n";
try {
    $deleteProduct = Product::create([
        'name' => 'Product To Delete',
        'sku' => 'PTD-' . time(),
        'price' => 99.99,
        'brand_id' => $brand->id
    ]);
    $deleteId = $deleteProduct->id;
    
    $deleteProduct->delete();
    echo "   ✓ Product deleted successfully\n";
    
    $notFound = Product::find($deleteId);
    if (!$notFound) {
        echo "   ✓ Product removed from database\n";
    }
    echo "\n";
} catch (Exception $e) {
    echo "   ✗ Error testing delete: " . $e->getMessage() . "\n\n";
}

// Test 10: Bulk Operations
echo "10. Testing bulk operations...\n";
try {
    $bulkProducts = [];
    for ($i = 1; $i <= 5; $i++) {
        $bulkProducts[] = Product::create([
            'name' => "Bulk Product {$i}",
            'sku' => "BULK-{$i}-" . time(),
            'price' => 100 + ($i * 50),
            'brand_id' => $brand->id,
            'status' => 1
        ]);
    }
    echo "   ✓ Bulk created 5 products\n";
    
    // Update prices
    Product::where('name', 'like', 'Bulk Product%')->update(['status' => 0]);
    echo "   ✓ Bulk updated all bulk products to inactive\n\n";
} catch (Exception $e) {
    echo "   ✗ Error in bulk operations: " . $e->getMessage() . "\n\n";
}

// Final Statistics
echo "=== FINAL STATISTICS ===\n";
try {
    $total = Product::count();
    $active = Product::where('status', 1)->count();
    $inactive = Product::where('status', 0)->count();
    
    echo "Total Products: {$total}\n";
    echo "Active: {$active}\n";
    echo "Inactive: {$inactive}\n";
    
    $avgPrice = Product::avg('price');
    echo "Average Price: $" . number_format($avgPrice, 2) . "\n";
    
    $constructions = Product::select('construction')->distinct()->pluck('construction');
    echo "Construction Types: " . $constructions->filter()->implode(', ') . "\n";
} catch (Exception $e) {
    echo "Error getting statistics: " . $e->getMessage() . "\n";
}

echo "\n=== PRODUCT MODEL TEST COMPLETED ===\n";
