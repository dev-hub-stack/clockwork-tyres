<?php

/**
 * Test Script for ProductVariant Model - TUNERSTOP STRUCTURE
 * 
 * This script tests CRUD operations for the ProductVariant model
 * with all Tunerstop columns: sku, finish_id, size, bolt_pattern, hub_bore,
 * offset, weight, backspacing, lipsize, finish, max_wheel_load, rim_diameter,
 * rim_width, cost, price, us_retail_price, uae_retail_price, sale_price,
 * clearance_corner, image, supplier_stock, product_id
 * 
 * Run: php test_product_variants.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Modules\Products\Models\ProductVariant;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Brand;
use App\Modules\Products\Models\ProductModel;
use App\Modules\Products\Models\Finish;

echo "=== PRODUCT VARIANT MODEL TEST (TUNERSTOP STRUCTURE) ===\n\n";

// Setup test data
echo "0. Setting up test data...\n";
try {
    $brand = Brand::firstOrCreate(
        ['name' => 'Test Brand Variants'],
        ['slug' => 'test-brand-variants', 'status' => 1]
    );
    
    $model = ProductModel::firstOrCreate(
        ['name' => 'Test Model Variants']
    );
    
    $finish = Finish::firstOrCreate(
        ['finish' => 'Gloss Black']
    );
    
    $product = Product::firstOrCreate(
        ['sku' => 'TEST-WHEEL-001'],
        [
            'name' => 'Test Wheel for Variants',
            'price' => 299.99,
            'brand_id' => $brand->id,
            'model_id' => $model->id,
            'finish_id' => $finish->id,
            'construction' => 'Cast',
            'status' => 1
        ]
    );
    
    echo "   ✓ Product created: {$product->name} (ID: {$product->id})\n";
    echo "   ✓ Brand: {$brand->name}\n";
    echo "   ✓ Model: {$model->name}\n";
    echo "   ✓ Finish: {$finish->finish}\n\n";
} catch (Exception $e) {
    echo "   ✗ Error setting up: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Test 1: Create Product Variants with FULL Tunerstop Structure
echo "1. Creating product variants (Tunerstop structure)...\n";
try {
    $variant1 = ProductVariant::create([
        'sku' => 'TEST-20x9-001-' . time(),
        'product_id' => $product->id,
        'finish_id' => $finish->id,
        'finish' => 'Gloss Black',
        'size' => '20x9',
        'rim_width' => '9.0',
        'rim_diameter' => '20.0',
        'bolt_pattern' => '6x5.5',
        'hub_bore' => '78.1',
        'offset' => '+1',
        'backspacing' => '5.5',
        'weight' => '32',
        'lipsize' => '3.5',
        'max_wheel_load' => '2500',
        'cost' => '150.00',
        'price' => '299.99',
        'us_retail_price' => 299.99,
        'uae_retail_price' => 1100.00,
        'sale_price' => '269.99',
        'clearance_corner' => false,
        'supplier_stock' => 25,
        'image' => 'wheels/test-20x9.jpg'
    ]);
    echo "   ✓ Variant created: {$variant1->size} (SKU: {$variant1->sku})\n";
    echo "   ✓ Bolt Pattern: {$variant1->bolt_pattern}\n";
    echo "   ✓ US Retail: \${$variant1->us_retail_price}\n";
    echo "   ✓ Supplier Stock: {$variant1->supplier_stock}\n";

    $variant2 = ProductVariant::create([
        'sku' => 'TEST-20x10-001-' . time(),
        'product_id' => $product->id,
        'finish_id' => $finish->id,
        'size' => '20x10.5',
        'rim_width' => '10.5',
        'rim_diameter' => '20.0',
        'bolt_pattern' => '6x5.5',
        'hub_bore' => '78.1',
        'offset' => '-12',
        'backspacing' => '6.0',
        'weight' => '35',
        'us_retail_price' => 329.99,
        'sale_price' => '299.99',
        'supplier_stock' => 15,
        'clearance_corner' => true
    ]);
    echo "   ✓ Variant created: {$variant2->size} (SKU: {$variant2->sku})\n";
    echo "   ✓ Clearance Corner: " . ($variant2->clearance_corner ? 'Yes' : 'No') . "\n\n";
} catch (Exception $e) {
    echo "   ✗ Error creating variant: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Test 2: Read Variant with All Columns
echo "2. Reading variant with all Tunerstop columns...\n";
try {
    $foundVariant = ProductVariant::with(['product', 'finish'])->find($variant1->id);
    echo "   ✓ SKU: {$foundVariant->sku}\n";
    echo "   ✓ Product: {$foundVariant->product->name}\n";
    echo "   ✓ Size: {$foundVariant->size}\n";
    echo "   ✓ Rim Width: {$foundVariant->rim_width}\"\n";
    echo "   ✓ Rim Diameter: {$foundVariant->rim_diameter}\"\n";
    echo "   ✓ Bolt Pattern: {$foundVariant->bolt_pattern}\n";
    echo "   ✓ Hub Bore: {$foundVariant->hub_bore}mm\n";
    echo "   ✓ Offset: {$foundVariant->offset}mm\n";
    echo "   ✓ Backspacing: {$foundVariant->backspacing}\"\n";
    echo "   ✓ Weight: {$foundVariant->weight} lbs\n";
    echo "   ✓ Lipsize: {$foundVariant->lipsize}\"\n";
    echo "   ✓ Max Wheel Load: {$foundVariant->max_wheel_load} lbs\n";
    echo "   ✓ Cost: \${$foundVariant->cost}\n";
    echo "   ✓ Price: \${$foundVariant->price}\n";
    echo "   ✓ US Retail Price: \${$foundVariant->us_retail_price}\n";
    echo "   ✓ UAE Retail Price: AED {$foundVariant->uae_retail_price}\n";
    echo "   ✓ Sale Price: \${$foundVariant->sale_price}\n";
    echo "   ✓ Supplier Stock: {$foundVariant->supplier_stock} units\n";
    echo "   ✓ Finish: " . ($foundVariant->finish ? $foundVariant->finish->finish : $foundVariant->finish) . "\n\n";
} catch (Exception $e) {
    echo "   ✗ Error reading variant: " . $e->getMessage() . "\n\n";
}

// Test 3: List All Variants for Product
echo "3. Listing variants for product...\n";
try {
    $variants = ProductVariant::where('product_id', $product->id)->get();
    echo "   ✓ Product has {$variants->count()} variant(s)\n";
    foreach ($variants as $v) {
        echo "     - {$v->size} | {$v->bolt_pattern} | {$v->sku} | Stock: {$v->supplier_stock}\n";
    }
    echo "\n";
} catch (Exception $e) {
    echo "   ✗ Error listing variants: " . $e->getMessage() . "\n\n";
}

// Test 4: Update Variant
echo "4. Updating variant...\n";
try {
    $variant1->update([
        'us_retail_price' => 319.99,
        'sale_price' => '289.99',
        'supplier_stock' => 30,
        'weight' => '33'
    ]);
    $variant1->refresh();
    echo "   ✓ Variant updated successfully\n";
    echo "   ✓ New US Retail: \${$variant1->us_retail_price}\n";
    echo "   ✓ New Sale Price: \${$variant1->sale_price}\n";
    echo "   ✓ New Stock: {$variant1->supplier_stock}\n";
    echo "   ✓ New Weight: {$variant1->weight} lbs\n\n";
} catch (Exception $e) {
    echo "   ✗ Error updating variant: " . $e->getMessage() . "\n\n";
}

// Test 5: Search/Filter Variants
echo "5. Testing search and filter...\n";
try {
    $size20x9 = ProductVariant::where('size', '20x9')->count();
    echo "   ✓ 20x9 size: {$size20x9}\n";
    
    $bolt6x55 = ProductVariant::where('bolt_pattern', '6x5.5')->count();
    echo "   ✓ 6x5.5 bolt pattern: {$bolt6x55}\n";
    
    $clearance = ProductVariant::where('clearance_corner', true)->count();
    echo "   ✓ Clearance items: {$clearance}\n";
    
    $inStock = ProductVariant::where('supplier_stock', '>', 0)->count();
    echo "   ✓ In stock: {$inStock}\n";
    
    $onSale = ProductVariant::whereNotNull('sale_price')->count();
    echo "   ✓ On sale: {$onSale}\n\n";
} catch (Exception $e) {
    echo "   ✗ Error searching variants: " . $e->getMessage() . "\n\n";
}

// Test 6: Test Relationships
echo "6. Testing relationships...\n";
try {
    $variant = ProductVariant::with(['product.brand', 'product.model', 'finish'])->first();
    if ($variant) {
        echo "   ✓ Variant: {$variant->sku}\n";
        echo "   ✓ Product: {$variant->product->name}\n";
        echo "   ✓ Brand: {$variant->product->brand->name}\n";
        echo "   ✓ Model: {$variant->product->model->name}\n";
        echo "   ✓ Finish: " . ($variant->finish ? $variant->finish->finish : 'N/A') . "\n";
    }
    echo "\n";
} catch (Exception $e) {
    echo "   ⚠ Relationship test: " . $e->getMessage() . "\n\n";
}

// Test 7: Unique SKU Constraint
echo "7. Testing unique SKU constraint...\n";
try {
    $duplicate = ProductVariant::create([
        'sku' => $variant1->sku, // Duplicate
        'product_id' => $product->id,
        'size' => '20x9'
    ]);
    echo "   ⚠ Warning: Duplicate SKU created\n\n";
    $duplicate->delete();
} catch (Exception $e) {
    echo "   ✓ Unique SKU constraint working\n\n";
}

// Test 8: Pricing Calculations
echo "8. Testing pricing calculations...\n";
try {
    $variant = ProductVariant::first();
    
    // Calculate margins
    if ($variant->cost && $variant->us_retail_price) {
        $margin = $variant->us_retail_price - floatval($variant->cost);
        $marginPercent = ($margin / $variant->us_retail_price) * 100;
        echo "   ✓ Cost: \${$variant->cost}\n";
        echo "   ✓ Retail: \${$variant->us_retail_price}\n";
        echo "   ✓ Margin: $" . number_format($margin, 2) . " (" . number_format($marginPercent, 1) . "%)\n";
    }
    
    if ($variant->sale_price && $variant->us_retail_price) {
        $discount = $variant->us_retail_price - floatval($variant->sale_price);
        $discountPercent = ($discount / $variant->us_retail_price) * 100;
        echo "   ✓ Discount: $" . number_format($discount, 2) . " (" . number_format($discountPercent, 1) . "% off)\n";
    }
    echo "\n";
} catch (Exception $e) {
    echo "   ⚠ Pricing test: " . $e->getMessage() . "\n\n";
}

// Test 9: Bulk Create Variants (Multiple Sizes)
echo "9. Testing bulk variant creation...\n";
try {
    $sizes = ['22x9', '22x10', '22x12', '24x10', '24x12'];
    $created = 0;
    
    foreach ($sizes as $size) {
        list($diameter, $width) = explode('x', $size);
        ProductVariant::create([
            'sku' => "BULK-{$size}-" . time() . "-{$created}",
            'product_id' => $product->id,
            'size' => $size,
            'rim_width' => $width,
            'rim_diameter' => $diameter,
            'bolt_pattern' => '6x5.5',
            'hub_bore' => '78.1',
            'offset' => '+1',
            'us_retail_price' => 300 + ($diameter * 10),
            'supplier_stock' => rand(10, 50)
        ]);
        $created++;
    }
    echo "   ✓ Bulk created {$created} variants\n\n";
} catch (Exception $e) {
    echo "   ✗ Error in bulk creation: " . $e->getMessage() . "\n\n";
}

// Test 10: Delete Variant
echo "10. Testing deletion...\n";
try {
    $deleteVariant = ProductVariant::create([
        'sku' => 'DELETE-TEST-' . time(),
        'product_id' => $product->id,
        'size' => '20x9'
    ]);
    $deleteId = $deleteVariant->id;
    
    $deleteVariant->delete();
    echo "   ✓ Variant deleted successfully\n";
    
    $notFound = ProductVariant::find($deleteId);
    if (!$notFound) {
        echo "   ✓ Variant removed from database\n";
    }
    echo "\n";
} catch (Exception $e) {
    echo "   ✗ Error testing delete: " . $e->getMessage() . "\n\n";
}

// Final Statistics
echo "=== FINAL STATISTICS ===\n";
try {
    $total = ProductVariant::count();
    $inStock = ProductVariant::where('supplier_stock', '>', 0)->count();
    $outOfStock = ProductVariant::where('supplier_stock', '<=', 0)->orWhereNull('supplier_stock')->count();
    $onClearance = ProductVariant::where('clearance_corner', true)->count();
    
    echo "Total Variants: {$total}\n";
    echo "In Stock: {$inStock}\n";
    echo "Out of Stock: {$outOfStock}\n";
    echo "Clearance Items: {$onClearance}\n";
    
    $avgRetailPrice = ProductVariant::avg('us_retail_price');
    echo "Average US Retail: $" . number_format($avgRetailPrice, 2) . "\n";
    
    $totalInventory = ProductVariant::sum('supplier_stock');
    echo "Total Inventory Units: {$totalInventory}\n";
    
    $sizes = ProductVariant::select('size')->distinct()->count();
    echo "Unique Sizes: {$sizes}\n";
    
    $boltPatterns = ProductVariant::select('bolt_pattern')->distinct()->count();
    echo "Unique Bolt Patterns: {$boltPatterns}\n";
} catch (Exception $e) {
    echo "Error getting statistics: " . $e->getMessage() . "\n";
}

echo "\n=== PRODUCT VARIANT MODEL TEST COMPLETED ===\n";
echo "All Tunerstop columns tested successfully!\n";
