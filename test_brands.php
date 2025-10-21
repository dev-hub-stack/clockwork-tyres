<?php

/**
 * Test Script for Brand Model
 * 
 * This script tests CRUD operations for the Brand model
 * Run: php test_brands.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Modules\Products\Models\Brand;
use App\Modules\Products\Models\Product;

echo "=== BRAND MODEL TEST ===\n\n";

// Test 1: Create Brand
echo "1. Creating new brands...\n";
try {
    $brand1 = Brand::create([
        'name' => 'Test Brand Alpha',
    ]);
    echo "   ✓ Brand created: {$brand1->name} (ID: {$brand1->id})\n";

    $brand2 = Brand::create([
        'name' => 'Test Brand Beta',
    ]);
    echo "   ✓ Brand created: {$brand2->name} (ID: {$brand2->id})\n\n";
} catch (Exception $e) {
    echo "   ✗ Error creating brand: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Test 2: Read Brand
echo "2. Reading brands...\n";
try {
    $foundBrand = Brand::find($brand1->id);
    echo "   ✓ Found brand: {$foundBrand->name}\n";
    echo "   ✓ Slug: {$foundBrand->slug}\n";
    echo "   ✓ Status: " . ($foundBrand->status ? 'Active' : 'Inactive') . "\n\n";
} catch (Exception $e) {
    echo "   ✗ Error reading brand: " . $e->getMessage() . "\n\n";
}

// Test 3: List All Brands
echo "3. Listing all brands...\n";
try {
    $brands = Brand::all();
    echo "   ✓ Total brands in database: {$brands->count()}\n";
    foreach ($brands as $brand) {
        echo "     - {$brand->name} (ID: {$brand->id})\n";
    }
    echo "\n";
} catch (Exception $e) {
    echo "   ✗ Error listing brands: " . $e->getMessage() . "\n\n";
}

// Test 4: Update Brand
echo "4. Updating brand...\n";
try {
    $brand1->update([
        'description' => 'Updated test brand description',
        'logo' => 'logos/updated-alpha.png'
    ]);
    $brand1->refresh();
    echo "   ✓ Brand updated successfully\n";
    echo "   ✓ New description: {$brand1->description}\n";
    echo "   ✓ New logo: {$brand1->logo}\n\n";
} catch (Exception $e) {
    echo "   ✗ Error updating brand: " . $e->getMessage() . "\n\n";
}

// Test 5: Search/Filter
echo "5. Testing search functionality...\n";
try {
    $searchResults = Brand::where('name', 'like', '%Alpha%')->get();
    echo "   ✓ Search for 'Alpha' found {$searchResults->count()} result(s)\n";
    
    $activeBrands = Brand::where('status', 1)->get();
    echo "   ✓ Active brands: {$activeBrands->count()}\n\n";
} catch (Exception $e) {
    echo "   ✗ Error searching brands: " . $e->getMessage() . "\n\n";
}

// Test 6: Test Relationships (if products exist)
echo "6. Testing relationships...\n";
try {
    // Check if brand has products relationship method
    if (method_exists($brand1, 'products')) {
        $products = $brand1->products;
        echo "   ✓ Brand has {$products->count()} product(s)\n";
    } else {
        echo "   ⚠ Products relationship not defined yet\n";
    }
    
    // Check if brand has models relationship method
    if (method_exists($brand1, 'models')) {
        $models = $brand1->models;
        echo "   ✓ Brand has {$models->count()} model(s)\n";
    } else {
        echo "   ⚠ Models relationship not defined yet\n";
    }
    echo "\n";
} catch (Exception $e) {
    echo "   ⚠ Relationship test: " . $e->getMessage() . "\n\n";
}

// Test 7: Validation Test
echo "7. Testing validation (duplicate name)...\n";
try {
    $duplicate = Brand::create([
        'name' => 'Test Brand Alpha', // Duplicate name
        'slug' => 'test-brand-alpha-duplicate'
    ]);
    echo "   ⚠ Warning: Duplicate brand created (validation may be missing)\n\n";
} catch (Exception $e) {
    echo "   ✓ Validation working: " . $e->getMessage() . "\n\n";
}

// Test 8: Soft Delete Test (if using soft deletes)
echo "8. Testing deletion...\n";
try {
    $deleteBrand = Brand::create([
        'name' => 'Brand To Delete',
        'slug' => 'brand-to-delete'
    ]);
    $deleteId = $deleteBrand->id;
    
    $deleteBrand->delete();
    echo "   ✓ Brand deleted successfully\n";
    
    // Check if soft delete
    if (in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses($deleteBrand))) {
        $withTrashed = Brand::withTrashed()->find($deleteId);
        if ($withTrashed) {
            echo "   ✓ Soft delete working - brand still in database\n";
            echo "   ✓ Deleted at: {$withTrashed->deleted_at}\n";
        }
    } else {
        $notFound = Brand::find($deleteId);
        if (!$notFound) {
            echo "   ✓ Hard delete working - brand removed from database\n";
        }
    }
    echo "\n";
} catch (Exception $e) {
    echo "   ✗ Error testing delete: " . $e->getMessage() . "\n\n";
}

// Test 9: Mass Operations
echo "9. Testing bulk operations...\n";
try {
    // Create multiple brands at once
    $bulkBrands = [
        ['name' => 'Bulk Brand 1', 'slug' => 'bulk-brand-1', 'status' => 1],
        ['name' => 'Bulk Brand 2', 'slug' => 'bulk-brand-2', 'status' => 1],
        ['name' => 'Bulk Brand 3', 'slug' => 'bulk-brand-3', 'status' => 0],
    ];
    
    foreach ($bulkBrands as $brandData) {
        Brand::create($brandData);
    }
    echo "   ✓ Bulk created 3 brands\n";
    
    // Update multiple records
    Brand::where('name', 'like', 'Bulk Brand%')->update(['status' => 1]);
    echo "   ✓ Bulk updated all bulk brands to active\n\n";
} catch (Exception $e) {
    echo "   ✗ Error in bulk operations: " . $e->getMessage() . "\n\n";
}

// Final Statistics
echo "=== FINAL STATISTICS ===\n";
try {
    $total = Brand::count();
    $active = Brand::where('status', 1)->count();
    $inactive = Brand::where('status', 0)->count();
    
    echo "Total Brands: {$total}\n";
    echo "Active: {$active}\n";
    echo "Inactive: {$inactive}\n";
    
    if (in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses(new Brand))) {
        $trashed = Brand::onlyTrashed()->count();
        echo "Soft Deleted: {$trashed}\n";
    }
} catch (Exception $e) {
    echo "Error getting statistics: " . $e->getMessage() . "\n";
}

echo "\n=== BRAND MODEL TEST COMPLETED ===\n";
