<?php

/**
 * Test Script for Model (Product Model) Model
 * 
 * This script tests CRUD operations for the Model model
 * Run: php test_models.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Modules\Products\Models\ProductModel;
use App\Modules\Products\Models\Product;

echo "=== PRODUCT MODEL TEST ===\n\n";

// Test 1: Create Models
echo "1. Creating new product models...\n";
try {
    $model1 = ProductModel::create([
        'name' => 'Test Model X1',
        'image' => 'models/test-x1.png'
    ]);
    echo "   ✓ Model created: {$model1->name} (ID: {$model1->id})\n";

    $model2 = ProductModel::create([
        'name' => 'Test Model Y2'
    ]);
    echo "   ✓ Model created: {$model2->name} (ID: {$model2->id})\n\n";
} catch (Exception $e) {
    echo "   ✗ Error creating model: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Test 2: Read Model
echo "2. Reading models...\n";
try {
    $foundModel = ProductModel::find($model1->id);
    echo "   ✓ Found model: {$foundModel->name}\n";
    echo "   ✓ Image: " . ($foundModel->image ?? 'No image') . "\n";
    echo "   ✓ Created at: {$foundModel->created_at}\n\n";
} catch (Exception $e) {
    echo "   ✗ Error reading model: " . $e->getMessage() . "\n\n";
}

// Test 3: List All Models
echo "3. Listing all models...\n";
try {
    $models = ProductModel::all();
    echo "   ✓ Total models in database: {$models->count()}\n";
    foreach ($models as $model) {
        echo "     - {$model->name} (ID: {$model->id})\n";
    }
    echo "\n";
} catch (Exception $e) {
    echo "   ✗ Error listing models: " . $e->getMessage() . "\n\n";
}

// Test 4: Update Model
echo "4. Updating model...\n";
try {
    $model1->update([
        'name' => 'Test Model X1 Updated',
        'image' => 'models/updated-x1.png'
    ]);
    $model1->refresh();
    echo "   ✓ Model updated successfully\n";
    echo "   ✓ New name: {$model1->name}\n";
    echo "   ✓ New image: {$model1->image}\n\n";
} catch (Exception $e) {
    echo "   ✗ Error updating model: " . $e->getMessage() . "\n\n";
}

// Test 5: Search/Filter
echo "5. Testing search functionality...\n";
try {
    $searchResults = ProductModel::where('name', 'like', '%X1%')->get();
    echo "   ✓ Search for 'X1' found {$searchResults->count()} result(s)\n";
    
    $modelsWithImages = ProductModel::whereNotNull('image')->get();
    echo "   ✓ Models with images: {$modelsWithImages->count()}\n\n";
} catch (Exception $e) {
    echo "   ✗ Error searching models: " . $e->getMessage() . "\n\n";
}

// Test 6: Test Relationships
echo "6. Testing relationships...\n";
try {
    if (method_exists($model1, 'products')) {
        $products = $model1->products;
        echo "   ✓ Model has {$products->count()} product(s)\n";
    } else {
        echo "   ⚠ Products relationship not defined yet\n";
    }
    echo "\n";
} catch (Exception $e) {
    echo "   ⚠ Relationship test: " . $e->getMessage() . "\n\n";
}

// Test 7: Unique Name Validation
echo "7. Testing unique constraint...\n";
try {
    $duplicate = ProductModel::create([
        'name' => 'Test Model X1 Updated' // Duplicate name
    ]);
    echo "   ⚠ Warning: Duplicate model created (unique constraint may be missing)\n\n";
} catch (Exception $e) {
    echo "   ✓ Unique constraint working: " . $e->getMessage() . "\n\n";
}

// Test 8: Delete Model
echo "8. Testing deletion...\n";
try {
    $deleteModel = ProductModel::create([
        'name' => 'Model To Delete'
    ]);
    $deleteId = $deleteModel->id;
    
    $deleteModel->delete();
    echo "   ✓ Model deleted successfully\n";
    
    $notFound = ProductModel::find($deleteId);
    if (!$notFound) {
        echo "   ✓ Model removed from database\n";
    }
    echo "\n";
} catch (Exception $e) {
    echo "   ✗ Error testing delete: " . $e->getMessage() . "\n\n";
}

// Test 9: Bulk Operations
echo "9. Testing bulk operations...\n";
try {
    $bulkModels = [
        ['name' => 'Bulk Model A'],
        ['name' => 'Bulk Model B'],
        ['name' => 'Bulk Model C'],
    ];
    
    foreach ($bulkModels as $modelData) {
        ProductModel::create($modelData);
    }
    echo "   ✓ Bulk created 3 models\n";
    
    $count = ProductModel::where('name', 'like', 'Bulk Model%')->count();
    echo "   ✓ Found {$count} bulk models\n\n";
} catch (Exception $e) {
    echo "   ✗ Error in bulk operations: " . $e->getMessage() . "\n\n";
}

// Final Statistics
echo "=== FINAL STATISTICS ===\n";
try {
    $total = ProductModel::count();
    $withImages = ProductModel::whereNotNull('image')->count();
    $withoutImages = ProductModel::whereNull('image')->count();
    
    echo "Total Models: {$total}\n";
    echo "With Images: {$withImages}\n";
    echo "Without Images: {$withoutImages}\n";
} catch (Exception $e) {
    echo "Error getting statistics: " . $e->getMessage() . "\n";
}

echo "\n=== PRODUCT MODEL TEST COMPLETED ===\n";
