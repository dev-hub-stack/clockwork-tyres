<?php

/**
 * Test Bulk Import Functionality
 * Run: php test_bulk_import.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Modules\Products\Models\ProductVariant;
use App\Modules\Products\Models\Brand;
use App\Modules\Products\Models\ProductModel;
use App\Modules\Products\Models\Finish;
use App\Modules\Products\Models\Product;
use Maatwebsite\Excel\Facades\Excel;

echo "=== BULK IMPORT TEST ===\n\n";

// Test file path
$testFile = __DIR__ . '/public/uploads/samplefiles/test-products.csv';

if (!file_exists($testFile)) {
    echo "❌ Test file not found: {$testFile}\n";
    exit(1);
}

echo "📄 Reading file: test-products.csv\n";

try {
    // Read CSV using Laravel Excel
    $data = Excel::toArray([], $testFile);
    
    if (empty($data) || empty($data[0])) {
        echo "❌ File is empty or invalid format\n";
        exit(1);
    }
    
    $rows = $data[0];
    $headers = array_shift($rows); // Remove header row
    
    echo "✅ Found " . count($rows) . " data rows\n";
    echo "📋 Headers: " . implode(', ', array_slice($headers, 0, 5)) . "...\n\n";
    
    // Count before import
    $beforeCount = ProductVariant::count();
    $beforeBrands = Brand::count();
    $beforeModels = ProductModel::count();
    $beforeFinishes = Finish::count();
    
    echo "📊 BEFORE IMPORT:\n";
    echo "   - Product Variants: {$beforeCount}\n";
    echo "   - Brands: {$beforeBrands}\n";
    echo "   - Models: {$beforeModels}\n";
    echo "   - Finishes: {$beforeFinishes}\n\n";
    
    // Normalize headers
    $headers = array_map(function($h) {
        return strtolower(trim($h));
    }, $headers);
    
    echo "🔄 Processing rows...\n";
    
    $imported = 0;
    foreach ($rows as $index => $row) {
        // Skip empty rows
        if (empty(array_filter($row))) continue;
        
        // Map row to associative array
        $rowData = array_combine($headers, $row);
        
        $sku = $rowData['sku'] ?? '';
        if (empty($sku)) {
            echo "   ⚠️  Row " . ($index + 2) . ": SKU is empty, skipping\n";
            continue;
        }
        
        echo "   → Processing SKU: {$sku}... ";
        
        // Find or create Brand
        $brand = null;
        if (!empty($rowData['brand'])) {
            $brand = Brand::firstOrCreate(
                ['name' => $rowData['brand']],
                ['slug' => \Illuminate\Support\Str::slug($rowData['brand'])]
            );
        }
        
        // Find or create Model
        $model = null;
        if (!empty($rowData['model'])) {
            $model = ProductModel::firstOrCreate(['name' => $rowData['model']]);
        }
        
        // Find or create Finish
        $finish = null;
        if (!empty($rowData['finish'])) {
            $finish = Finish::firstOrCreate(['finish' => $rowData['finish']]);
        }
        
        // Create Product
        $product = Product::updateOrCreate(
            ['sku' => $rowData['sku']],
            [
                'name' => $rowData['sku'],
                'brand_id' => $brand ? $brand->id : null,
                'model_id' => $model ? $model->id : null,
                'finish_id' => $finish ? $finish->id : null,
                'construction' => $rowData['construction'] ?? null,
                'price' => $rowData['us retail price'] ?? 0,
                'status' => 1, // Active (boolean: 1 = active, 0 = inactive)
            ]
        );
        
        // Create ProductVariant
        $variant = ProductVariant::updateOrCreate(
            ['sku' => $rowData['sku']],
            [
                'product_id' => $product->id,
                'finish_id' => $finish ? $finish->id : null,
                'finish' => $rowData['finish'] ?? null,
                'rim_width' => $rowData['rim width'] ?? null,
                'rim_diameter' => $rowData['rim diameter'] ?? null,
                'size' => $rowData['size'] ?? null,
                'bolt_pattern' => $rowData['bolt pattern'] ?? null,
                'hub_bore' => $rowData['hub bore'] ?? null,
                'offset' => $rowData['offset'] ?? null,
                'backspacing' => $rowData['warranty'] ?? null,
                'max_wheel_load' => $rowData['max wheel load'] ?? null,
                'weight' => $rowData['weight'] ?? null,
                'lipsize' => $rowData['lipsize'] ?? null,
                'us_retail_price' => $rowData['us retail price'] ?? 0,
                'uae_retail_price' => $rowData['uae retail price'] ?? 0,
                'sale_price' => $rowData['sale price'] ?? 0,
                'clearance_corner' => !empty($rowData['clearance corner']) ? (int)$rowData['clearance corner'] : 0,
                'supplier_stock' => $rowData['supplier stock'] ?? 0,
            ]
        );
        
        echo "✅\n";
        $imported++;
    }
    
    // Count after import
    $afterCount = ProductVariant::count();
    $afterBrands = Brand::count();
    $afterModels = ProductModel::count();
    $afterFinishes = Finish::count();
    
    echo "\n📊 AFTER IMPORT:\n";
    echo "   - Product Variants: {$afterCount} (+" . ($afterCount - $beforeCount) . ")\n";
    echo "   - Brands: {$afterBrands} (+" . ($afterBrands - $beforeBrands) . ")\n";
    echo "   - Models: {$afterModels} (+" . ($afterModels - $beforeModels) . ")\n";
    echo "   - Finishes: {$afterFinishes} (+" . ($afterFinishes - $beforeFinishes) . ")\n\n";
    
    echo "✅ SUCCESS! Imported {$imported} products\n\n";
    
    // Display sample data
    echo "📝 Sample imported data:\n";
    $samples = ProductVariant::with(['product.brand', 'product.model', 'finishRelation'])
        ->whereIn('sku', ['TEST-20x9-001', 'TEST-22x9-003', 'TEST-24x12-005'])
        ->get();
    
    foreach ($samples as $sample) {
        echo "\n   SKU: {$sample->sku}\n";
        echo "   Brand: " . ($sample->product && $sample->product->brand ? $sample->product->brand->name : 'N/A') . "\n";
        echo "   Model: " . ($sample->product && $sample->product->model ? $sample->product->model->name : 'N/A') . "\n";
        echo "   Finish: " . ($sample->finishRelation ? $sample->finishRelation->finish : 'N/A') . "\n";
        echo "   Size: {$sample->size}\n";
        echo "   US Price: $" . number_format($sample->us_retail_price, 2) . "\n";
        echo "   Supplier Stock: {$sample->supplier_stock}\n";
    }
    
    echo "\n=== TEST COMPLETE ===\n";
    
} catch (\Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
