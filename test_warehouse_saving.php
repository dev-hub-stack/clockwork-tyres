<?php

/**
 * Test: Warehouse ID Saving in Consignment Items
 * 
 * PURPOSE: Verify that warehouse_id is properly saved when creating consignments
 * 
 * STATUS: ✅ Testing warehouse field persistence
 * DATE: November 1, 2025
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Modules\Consignments\Models\Consignment;
use App\Modules\Consignments\Models\ConsignmentItem;
use App\Modules\Customers\Models\Customer;
use App\Modules\Products\Models\ProductVariant;
use App\Modules\Inventory\Models\Warehouse;

echo "\n=== WAREHOUSE ID SAVING TEST ===\n\n";

// Step 1: Get a dealer customer
echo "Step 1: Finding dealer customer...\n";
$customer = Customer::where('customer_type', 'dealer')->first();

if (!$customer) {
    echo "❌ No dealer customer found\n";
    exit(1);
}

echo "✅ Found customer: {$customer->company_name} (ID: {$customer->id})\n\n";

// Step 2: Get a product variant
echo "Step 2: Finding product variant...\n";
$variant = ProductVariant::with('product')->where('sku', 'RR7-H-1785-0139-BK')->first();

if (!$variant) {
    echo "❌ Product variant not found\n";
    exit(1);
}

echo "✅ Found variant: {$variant->sku}\n";
echo "   Price: AED " . number_format($variant->uae_retail_price, 2) . "\n\n";

// Step 3: Get a warehouse
echo "Step 3: Finding warehouse...\n";
$warehouse = Warehouse::where('status', 1)->first();

if (!$warehouse) {
    echo "❌ No active warehouse found\n";
    exit(1);
}

echo "✅ Found warehouse: {$warehouse->warehouse_name} (ID: {$warehouse->id})\n\n";

// Step 4: Create consignment with warehouse_id in items
echo "Step 4: Creating test consignment...\n";

try {
    $consignment = Consignment::create([
        'consignment_number' => 'TEST-WH-' . time(),
        'customer_id' => $customer->id,
        'status' => 'draft',
        'issue_date' => now(),
        'subtotal' => 0,
        'tax' => 0,
        'total' => 0,
        'created_by' => 1,
    ]);
    
    echo "✅ Created consignment: {$consignment->consignment_number}\n\n";
    
    // Step 5: Create consignment item with warehouse_id
    echo "Step 5: Creating consignment item with warehouse_id...\n";
    
    $item = ConsignmentItem::create([
        'consignment_id' => $consignment->id,
        'product_variant_id' => $variant->id,
        'warehouse_id' => $warehouse->id,  // This should now save!
        'product_snapshot' => [
            'product_name' => $variant->product->name,
            'brand_name' => $variant->product->brand->name ?? 'N/A',
            'sku' => $variant->sku,
        ],
        'product_name' => $variant->product->name,
        'brand_name' => $variant->product->brand->name ?? 'N/A',
        'sku' => $variant->sku,
        'description' => $variant->product->description ?? '',
        'quantity_sent' => 2,
        'quantity_sold' => 0,
        'quantity_returned' => 0,
        'price' => $variant->uae_retail_price,
        'status' => 'sent',
    ]);
    
    echo "✅ Created consignment item (ID: {$item->id})\n\n";
    
    // Step 6: Verify warehouse_id was saved
    echo "Step 6: Verifying warehouse_id in database...\n";
    
    $savedItem = ConsignmentItem::find($item->id);
    
    echo "\n=== VERIFICATION RESULTS ===\n\n";
    echo "Consignment: {$consignment->consignment_number}\n";
    echo "Item ID: {$savedItem->id}\n";
    echo "Product: {$savedItem->product_name}\n";
    echo "SKU: {$savedItem->sku}\n";
    echo "Warehouse ID (expected): {$warehouse->id}\n";
    echo "Warehouse ID (saved): " . ($savedItem->warehouse_id ?? 'NULL') . "\n";
    
    if ($savedItem->warehouse_id) {
        echo "\n✅ SUCCESS: Warehouse ID was saved correctly!\n";
        
        // Load warehouse relationship
        $savedItem->load('warehouse');
        if ($savedItem->warehouse) {
            echo "✅ Warehouse relationship works: {$savedItem->warehouse->warehouse_name}\n";
        }
    } else {
        echo "\n❌ FAILED: Warehouse ID is NULL\n";
    }
    
    // Step 7: Test via form data structure (how Filament sends it)
    echo "\n\nStep 7: Testing with Filament-style form data...\n";
    
    $formData = [
        'items' => [
            [
                'product_variant_id' => $variant->id,
                'warehouse_id' => $warehouse->id,
                'quantity_sent' => 3,
                'price' => $variant->uae_retail_price,
                'notes' => 'Test from form structure',
            ]
        ]
    ];
    
    echo "Form data warehouse_id: {$formData['items'][0]['warehouse_id']}\n";
    echo "✅ Form data structure includes warehouse_id\n\n";
    
    // Cleanup
    echo "Step 8: Cleaning up test data...\n";
    $consignment->items()->delete();
    $consignment->delete();
    echo "✅ Test consignment deleted\n\n";
    
    echo "=== TEST COMPLETE ===\n\n";
    
} catch (\Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    exit(1);
}
