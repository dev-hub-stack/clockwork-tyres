<?php

// Test API endpoints directly
require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Testing Consignment API Endpoint ===\n\n";

// Test 1: Find the product variant by SKU
$sku = 'RR7-H-1785-25127-BK';
echo "Looking for SKU: {$sku}\n";

$variant = \App\Modules\Products\Models\ProductVariant::where('sku', $sku)->first();

if (!$variant) {
    echo "❌ ERROR: Product variant with SKU '{$sku}' not found!\n";
    echo "Available SKUs in database:\n";
    $skus = \App\Modules\Products\Models\ProductVariant::take(5)->pluck('sku');
    foreach ($skus as $s) {
        echo "  - {$s}\n";
    }
    exit(1);
}

echo "✅ Found variant ID: {$variant->id}\n";
echo "   Product: {$variant->product->name}\n\n";

// Test 2: Get consignment items
echo "Looking for consignment items...\n";

$consignmentItems = \App\Modules\Consignments\Models\ConsignmentItem::where('product_variant_id', $variant->id)
    ->whereHas('consignment', function($q) {
        $q->whereIn('status', ['sent', 'delivered', 'partially_sold']);
    })
    ->with(['consignment.customer'])
    ->get();

echo "Found {$consignmentItems->count()} consignment items\n\n";

if ($consignmentItems->count() > 0) {
    foreach ($consignmentItems as $item) {
        $availableQty = $item->quantity_sent - $item->quantity_sold - $item->quantity_returned;
        
        if ($availableQty > 0) {
            echo "✅ Consignment ID: {$item->consignment_id}\n";
            echo "   Customer: " . ($item->consignment->customer->business_name ?? $item->consignment->customer->name ?? 'Unknown') . "\n";
            echo "   Available Qty: {$availableQty}\n";
            echo "   Date: " . ($item->consignment->issue_date ? $item->consignment->issue_date->format('d-m-Y') : 'N/A') . "\n";
            echo "\n";
        }
    }
} else {
    echo "ℹ️  No active consignments found for this product\n";
}

// Test 3: Test the controller method directly
echo "\n=== Testing Controller Method ===\n\n";

try {
    $controller = new \App\Http\Controllers\Api\InventoryApiController();
    $response = $controller->getConsignmentsBySku($sku);
    
    echo "✅ Controller method executed successfully\n";
    echo "Response type: " . get_class($response) . "\n";
    
    $data = json_decode($response->getContent(), true);
    echo "Response data count: " . count($data) . "\n";
    
    if (count($data) > 0) {
        echo "\nFirst item:\n";
        print_r($data[0]);
    } else {
        echo "\nℹ️  Response is empty (no consignments)\n";
    }
    
} catch (\Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . "\n";
    echo "   Line: " . $e->getLine() . "\n";
}

echo "\n=== Test Complete ===\n";
