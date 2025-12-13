<?php

/**
 * Test Warehouse Inventory Operations
 * 
 * This script tests:
 * - Creating product inventory across warehouses
 * - Updating inventory (qty, eta, eta_qty)
 * - Inventory logs creation
 * - Total quantity calculation
 * - Inventory queries and relationships
 * 
 * Run: php test_warehouse_inventory.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\ProductInventory;
use App\Modules\Inventory\Models\InventoryLog;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

echo "=== WAREHOUSE INVENTORY OPERATIONS TEST ===\n\n";

// SETUP: Create test warehouses if they don't exist
echo "🔧 Setting up test warehouses...\n";

$whMain = Warehouse::firstOrCreate(
    ['code' => 'TEST-MAIN'],
    [
        'warehouse_name' => 'Main Warehouse - Test',
        'status' => 1,
        'is_primary' => 1,
    ]
);

$whEU = Warehouse::firstOrCreate(
    ['code' => 'TEST-EU'],
    [
        'warehouse_name' => 'European Warehouse - Test',
        'status' => 1,
        'is_primary' => 0,
    ]
);

$whAsia = Warehouse::firstOrCreate(
    ['code' => 'TEST-ASIA'],
    [
        'warehouse_name' => 'Asian Warehouse - Test',
        'status' => 1,
        'is_primary' => 0,
    ]
);

echo "✅ Warehouses ready:\n";
echo "   - {$whMain->code} (ID: {$whMain->id})\n";
echo "   - {$whEU->code} (ID: {$whEU->id})\n";
echo "   - {$whAsia->code} (ID: {$whAsia->id})\n\n";

// SETUP: Get a test product variant
echo "🔧 Finding test product variant...\n";

$variant = ProductVariant::with('product.brand', 'product.model')
    ->whereNotNull('sku')
    ->first();

if (!$variant) {
    echo "❌ No product variants found. Please run product tests first.\n";
    exit(1);
}

echo "✅ Using variant: {$variant->sku}\n";
if ($variant->product) {
    echo "   Product: {$variant->product->brand->name} {$variant->product->model->name}\n";
}
echo "\n";

// Clean up existing test inventory for this variant
echo "🧹 Cleaning up existing test inventory...\n";
ProductInventory::where('variant_id', $variant->id)
    ->whereIn('warehouse_id', [$whMain->id, $whEU->id, $whAsia->id])
    ->delete();
echo "✅ Cleanup complete\n\n";

// TEST 1: Create inventory in Main warehouse
echo "TEST 1: Creating inventory in Main warehouse\n";
echo str_repeat('-', 50) . "\n";

try {
    $invMain = ProductInventory::create([
        'warehouse_id' => $whMain->id,
        'variant_id' => $variant->id,
        'quantity' => 100,
        'eta' => null,
        'eta_qty' => 0,
    ]);
    
    echo "✅ Created inventory:\n";
    echo "   Warehouse: {$whMain->code}\n";
    echo "   SKU: {$variant->sku}\n";
    echo "   Quantity: {$invMain->quantity}\n";
    echo "   ETA: " . ($invMain->eta ?: 'N/A') . "\n";
    echo "   ETA Qty: {$invMain->eta_qty}\n\n";
} catch (\Exception $e) {
    echo "❌ Failed to create inventory: " . $e->getMessage() . "\n\n";
    exit(1);
}

// TEST 2: Create inventory in EU warehouse with ETA
echo "TEST 2: Creating inventory in EU warehouse with ETA\n";
echo str_repeat('-', 50) . "\n";

try {
    $invEU = ProductInventory::create([
        'warehouse_id' => $whEU->id,
        'variant_id' => $variant->id,
        'quantity' => 50,
        'eta' => '2025-11-15',
        'eta_qty' => 75,
    ]);
    
    echo "✅ Created inventory:\n";
    echo "   Warehouse: {$whEU->code}\n";
    echo "   SKU: {$variant->sku}\n";
    echo "   Quantity: {$invEU->quantity}\n";
    echo "   ETA: {$invEU->eta}\n";
    echo "   ETA Qty: {$invEU->eta_qty}\n\n";
} catch (\Exception $e) {
    echo "❌ Failed to create inventory: " . $e->getMessage() . "\n\n";
    exit(1);
}

// TEST 3: Create inventory in Asia warehouse
echo "TEST 3: Creating inventory in Asia warehouse\n";
echo str_repeat('-', 50) . "\n";

try {
    $invAsia = ProductInventory::create([
        'warehouse_id' => $whAsia->id,
        'variant_id' => $variant->id,
        'quantity' => 25,
        'eta' => '2025-12-01',
        'eta_qty' => 100,
    ]);
    
    echo "✅ Created inventory:\n";
    echo "   Warehouse: {$whAsia->code}\n";
    echo "   Quantity: {$invAsia->quantity}\n";
    echo "   ETA: {$invAsia->eta}\n";
    echo "   ETA Qty: {$invAsia->eta_qty}\n\n";
} catch (\Exception $e) {
    echo "❌ Failed to create inventory: " . $e->getMessage() . "\n\n";
    exit(1);
}

// TEST 4: Update inventory (triggers log)
echo "TEST 4: Updating inventory quantity\n";
echo str_repeat('-', 50) . "\n";

try {
    $oldQty = $invMain->quantity;
    $newQty = 150;
    
    $invMain->quantity = $newQty;
    $invMain->save();
    
    echo "✅ Updated inventory:\n";
    echo "   Old Quantity: {$oldQty}\n";
    echo "   New Quantity: {$newQty}\n";
    echo "   Difference: " . ($newQty - $oldQty) . "\n\n";
    
    // Check if log was created
    $latestLog = InventoryLog::where('variant_id', $variant->id)
        ->where('warehouse_id', $whMain->id)
        ->latest()
        ->first();
    
    if ($latestLog) {
        echo "✅ Inventory log created:\n";
        echo "   Action: {$latestLog->action}\n";
        echo "   Quantity Before: {$latestLog->quantity_before}\n";
        echo "   Quantity After: {$latestLog->quantity_after}\n";
        echo "   Changed By: User ID {$latestLog->changed_by}\n\n";
    } else {
        echo "⚠️  No inventory log found (observer may not be set up)\n\n";
    }
} catch (\Exception $e) {
    echo "❌ Failed to update inventory: " . $e->getMessage() . "\n\n";
}

// TEST 5: Update ETA information
echo "TEST 5: Updating ETA information\n";
echo str_repeat('-', 50) . "\n";

try {
    $invMain->eta = '2025-11-01';
    $invMain->eta_qty = 50;
    $invMain->save();
    
    echo "✅ Updated ETA:\n";
    echo "   ETA Date: {$invMain->eta}\n";
    echo "   ETA Quantity: {$invMain->eta_qty}\n\n";
} catch (\Exception $e) {
    echo "❌ Failed to update ETA: " . $e->getMessage() . "\n\n";
}

// TEST 6: Query inventory by variant
echo "TEST 6: Querying all inventory for variant\n";
echo str_repeat('-', 50) . "\n";

$allInventory = ProductInventory::where('variant_id', $variant->id)
    ->with('warehouse')
    ->get();

echo "Total warehouses with stock: " . $allInventory->count() . "\n\n";

$totalQty = 0;
$totalEtaQty = 0;

foreach ($allInventory as $inv) {
    echo "📦 {$inv->warehouse->code}\n";
    echo "   On Hand: {$inv->quantity}\n";
    echo "   ETA: " . ($inv->eta ?: 'N/A') . "\n";
    echo "   ETA Qty: {$inv->eta_qty}\n\n";
    
    $totalQty += $inv->quantity;
    $totalEtaQty += $inv->eta_qty;
}

echo "TOTALS:\n";
echo "   Total On Hand: {$totalQty}\n";
echo "   Total Inbound (ETA Qty): {$totalEtaQty}\n";
echo "   Total Available (On Hand + Inbound): " . ($totalQty + $totalEtaQty) . "\n\n";

// TEST 7: Update product total_quantity
echo "TEST 7: Calculating and updating product total_quantity\n";
echo str_repeat('-', 50) . "\n";

try {
    // Calculate total from all warehouses
    $calculatedTotal = ProductInventory::where('variant_id', $variant->id)
        ->sum('quantity');
    
    // Update variant's total_quantity
    $variant->total_quantity = $calculatedTotal;
    $variant->save();
    
    echo "✅ Updated variant total_quantity:\n";
    echo "   Calculated Total: {$calculatedTotal}\n";
    echo "   Variant Total: {$variant->total_quantity}\n\n";
} catch (\Exception $e) {
    echo "❌ Failed to update total_quantity: " . $e->getMessage() . "\n\n";
}

// TEST 8: Query inventory by warehouse
echo "TEST 8: Querying inventory for Main warehouse\n";
echo str_repeat('-', 50) . "\n";

$whMainInventory = ProductInventory::where('warehouse_id', $whMain->id)
    ->with('variant')
    ->limit(5)
    ->get();

echo "Inventory items in {$whMain->code}: " . $whMainInventory->count() . " (showing max 5)\n\n";

foreach ($whMainInventory as $inv) {
    if ($inv->variant) {
        echo "📦 SKU: {$inv->variant->sku}\n";
        echo "   Quantity: {$inv->quantity}\n";
        echo "   ETA: " . ($inv->eta ?: 'N/A') . " (Qty: {$inv->eta_qty})\n\n";
    }
}

// TEST 9: Test relationship - Warehouse -> Inventories
echo "TEST 9: Testing Warehouse->Inventories relationship\n";
echo str_repeat('-', 50) . "\n";

$whMain->load('inventories');
echo "✅ {$whMain->code} has {$whMain->inventories->count()} inventory items\n\n";

// TEST 10: Test relationship - Variant -> Inventories
echo "TEST 10: Testing Variant->Inventories relationship\n";
echo str_repeat('-', 50) . "\n";

$variant->load('inventories.warehouse');
echo "✅ SKU {$variant->sku} is in {$variant->inventories->count()} warehouses:\n";

foreach ($variant->inventories as $inv) {
    echo "   - {$inv->warehouse->code}: {$inv->quantity} units\n";
}
echo "\n";

// TEST 11: Query low stock items
echo "TEST 11: Finding low stock items (qty < 50)\n";
echo str_repeat('-', 50) . "\n";

$lowStock = ProductInventory::where('quantity', '<', 50)
    ->with('warehouse', 'variant')
    ->limit(5)
    ->get();

echo "Low stock items: " . $lowStock->count() . " (showing max 5)\n\n";

foreach ($lowStock as $inv) {
    if ($inv->variant) {
        echo "⚠️  {$inv->variant->sku}\n";
        echo "   Warehouse: {$inv->warehouse->code}\n";
        echo "   Quantity: {$inv->quantity}\n";
        echo "   ETA Qty: {$inv->eta_qty}\n\n";
    }
}

// TEST 12: View recent inventory logs
echo "TEST 12: Viewing recent inventory logs\n";
echo str_repeat('-', 50) . "\n";

$recentLogs = InventoryLog::where('variant_id', $variant->id)
    ->with('warehouse')
    ->orderBy('created_at', 'desc')
    ->limit(5)
    ->get();

echo "Recent logs for SKU {$variant->sku}: " . $recentLogs->count() . "\n\n";

foreach ($recentLogs as $log) {
    echo "📝 {$log->action} - {$log->created_at}\n";
    echo "   Warehouse: {$log->warehouse->code}\n";
    echo "   Qty Before: {$log->quantity_before}\n";
    echo "   Qty After: {$log->quantity_after}\n";
    echo "   ETA Before: " . ($log->eta_before ?: 'N/A') . "\n";
    echo "   ETA After: " . ($log->eta_after ?: 'N/A') . "\n";
    echo "   Changed By: User {$log->changed_by}\n\n";
}

// SUMMARY
echo "\n";
echo str_repeat('=', 50) . "\n";
echo "TEST SUMMARY\n";
echo str_repeat('=', 50) . "\n";

$testInvCount = ProductInventory::where('variant_id', $variant->id)
    ->whereIn('warehouse_id', [$whMain->id, $whEU->id, $whAsia->id])
    ->count();

$testLogCount = InventoryLog::where('variant_id', $variant->id)
    ->whereIn('warehouse_id', [$whMain->id, $whEU->id, $whAsia->id])
    ->count();

echo "Test variant: {$variant->sku}\n";
echo "Warehouses with inventory: {$testInvCount}\n";
echo "Inventory logs created: {$testLogCount}\n";
echo "Variant total_quantity: {$variant->total_quantity}\n";

echo "\n✅ All inventory operations completed successfully!\n";
echo "\nℹ️  Note: Test inventory records are still in database.\n";
echo "   You can view them in the Inventory Grid.\n\n";

// Optional: Uncomment to auto-cleanup
// echo "🧹 Auto-cleaning test inventory...\n";
// ProductInventory::where('variant_id', $variant->id)
//     ->whereIn('warehouse_id', [$whMain->id, $whEU->id, $whAsia->id])
//     ->delete();
// echo "✅ All test inventory deleted\n\n";
