<?php

/**
 * Test Script for Finish Model
 * 
 * This script tests CRUD operations for the Finish model
 * Run: php test_finishes.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Modules\Products\Models\Finish;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductVariant;

echo "=== FINISH MODEL TEST ===\n\n";

// Test 1: Create Finishes
echo "1. Creating new finishes...\n";
try {
    $finish1 = Finish::create([
        'finish' => 'Gloss Black'
    ]);
    echo "   ✓ Finish created: {$finish1->finish} (ID: {$finish1->id})\n";

    $finish2 = Finish::create([
        'finish' => 'Matte Bronze'
    ]);
    echo "   ✓ Finish created: {$finish2->finish} (ID: {$finish2->id})\n";

    $finish3 = Finish::create([
        'finish' => 'Chrome'
    ]);
    echo "   ✓ Finish created: {$finish3->finish} (ID: {$finish3->id})\n\n";
} catch (Exception $e) {
    echo "   ✗ Error creating finish: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Test 2: Read Finish
echo "2. Reading finishes...\n";
try {
    $foundFinish = Finish::find($finish1->id);
    echo "   ✓ Found finish: {$foundFinish->finish}\n";
    echo "   ✓ Created at: {$foundFinish->created_at}\n\n";
} catch (Exception $e) {
    echo "   ✗ Error reading finish: " . $e->getMessage() . "\n\n";
}

// Test 3: List All Finishes
echo "3. Listing all finishes...\n";
try {
    $finishes = Finish::all();
    echo "   ✓ Total finishes in database: {$finishes->count()}\n";
    foreach ($finishes as $finish) {
        echo "     - {$finish->finish} (ID: {$finish->id})\n";
    }
    echo "\n";
} catch (Exception $e) {
    echo "   ✗ Error listing finishes: " . $e->getMessage() . "\n\n";
}

// Test 4: Update Finish
echo "4. Updating finish...\n";
try {
    $finish1->update([
        'finish' => 'Gloss Black Updated'
    ]);
    $finish1->refresh();
    echo "   ✓ Finish updated successfully\n";
    echo "   ✓ New name: {$finish1->finish}\n\n";
} catch (Exception $e) {
    echo "   ✗ Error updating finish: " . $e->getMessage() . "\n\n";
}

// Test 5: Search/Filter
echo "5. Testing search functionality...\n";
try {
    $searchResults = Finish::where('finish', 'like', '%Black%')->get();
    echo "   ✓ Search for 'Black' found {$searchResults->count()} result(s)\n";
    
    $allFinishes = Finish::orderBy('finish')->get();
    echo "   ✓ Finishes ordered alphabetically: {$allFinishes->count()}\n\n";
} catch (Exception $e) {
    echo "   ✗ Error searching finishes: " . $e->getMessage() . "\n\n";
}

// Test 6: Test Relationships
echo "6. Testing relationships...\n";
try {
    if (method_exists($finish1, 'products')) {
        $products = $finish1->products;
        echo "   ✓ Finish has {$products->count()} product(s)\n";
    } else {
        echo "   ⚠ Products relationship not defined yet\n";
    }
    
    if (method_exists($finish1, 'productVariants')) {
        $variants = $finish1->productVariants;
        echo "   ✓ Finish has {$variants->count()} product variant(s)\n";
    } else {
        echo "   ⚠ Product variants relationship not defined yet\n";
    }
    echo "\n";
} catch (Exception $e) {
    echo "   ⚠ Relationship test: " . $e->getMessage() . "\n\n";
}

// Test 7: Unique Constraint Test
echo "7. Testing unique constraint...\n";
try {
    $duplicate = Finish::create([
        'finish' => 'Gloss Black Updated' // Duplicate finish
    ]);
    echo "   ⚠ Warning: Duplicate finish created (unique constraint may be missing)\n\n";
} catch (Exception $e) {
    echo "   ✓ Unique constraint working: " . $e->getMessage() . "\n\n";
}

// Test 8: Delete Finish
echo "8. Testing deletion...\n";
try {
    $deleteFinish = Finish::create([
        'finish' => 'Finish To Delete'
    ]);
    $deleteId = $deleteFinish->id;
    
    $deleteFinish->delete();
    echo "   ✓ Finish deleted successfully\n";
    
    $notFound = Finish::find($deleteId);
    if (!$notFound) {
        echo "   ✓ Finish removed from database\n";
    }
    echo "\n";
} catch (Exception $e) {
    echo "   ✗ Error testing delete: " . $e->getMessage() . "\n\n";
}

// Test 9: Bulk Operations
echo "9. Testing bulk operations...\n";
try {
    $bulkFinishes = [
        ['finish' => 'Satin Silver'],
        ['finish' => 'Gunmetal'],
        ['finish' => 'Polished Aluminum'],
        ['finish' => 'Candy Red'],
    ];
    
    foreach ($bulkFinishes as $finishData) {
        Finish::create($finishData);
    }
    echo "   ✓ Bulk created 4 finishes\n";
    
    $metalicFinishes = Finish::whereIn('finish', ['Satin Silver', 'Gunmetal', 'Polished Aluminum'])->count();
    echo "   ✓ Found {$metalicFinishes} metallic finishes\n\n";
} catch (Exception $e) {
    echo "   ✗ Error in bulk operations: " . $e->getMessage() . "\n\n";
}

// Final Statistics
echo "=== FINAL STATISTICS ===\n";
try {
    $total = Finish::count();
    
    echo "Total Finishes: {$total}\n";
    
    // Common finish types count
    $blackFinishes = Finish::where('finish', 'like', '%Black%')->count();
    $chromeFinishes = Finish::where('finish', 'like', '%Chrome%')->count();
    
    echo "Black finishes: {$blackFinishes}\n";
    echo "Chrome finishes: {$chromeFinishes}\n";
} catch (Exception $e) {
    echo "Error getting statistics: " . $e->getMessage() . "\n";
}

echo "\n=== FINISH MODEL TEST COMPLETED ===\n";
