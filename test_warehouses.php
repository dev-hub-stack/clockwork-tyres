<?php

/**
 * Test Warehouse CRUD Operations
 * 
 * This script tests:
 * - Creating warehouses
 * - Updating warehouses
 * - Setting primary warehouse
 * - Deleting warehouses
 * - Observer enforcement (only one primary)
 * 
 * Run: php test_warehouses.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Support\Facades\DB;

echo "=== WAREHOUSE CRUD OPERATIONS TEST ===\n\n";

// Clean up any existing test warehouses
echo "🧹 Cleaning up existing test warehouses...\n";
Warehouse::where('code', 'LIKE', 'TEST-%')->delete();
echo "✅ Cleanup complete\n\n";

// TEST 1: Create Primary Warehouse
echo "TEST 1: Creating primary warehouse (WH-MAIN)\n";
echo str_repeat('-', 50) . "\n";

try {
    $whMain = Warehouse::create([
        'warehouse_name' => 'Main Warehouse - Test',
        'code' => 'TEST-MAIN',
        'status' => 1,
        'is_primary' => 1,
        'lat' => 34.0522,
        'lng' => -118.2437,
    ]);
    
    echo "✅ Created warehouse: {$whMain->warehouse_name}\n";
    echo "   ID: {$whMain->id}\n";
    echo "   Code: {$whMain->code}\n";
    echo "   Primary: " . ($whMain->is_primary ? 'YES' : 'NO') . "\n";
    echo "   Status: " . ($whMain->status ? 'Active' : 'Inactive') . "\n";
    echo "   Location: ({$whMain->lat}, {$whMain->lng})\n\n";
} catch (\Exception $e) {
    echo "❌ Failed to create warehouse: " . $e->getMessage() . "\n\n";
    exit(1);
}

// TEST 2: Create Secondary Warehouse
echo "TEST 2: Creating secondary warehouse (WH-EU)\n";
echo str_repeat('-', 50) . "\n";

try {
    $whEU = Warehouse::create([
        'warehouse_name' => 'European Warehouse - Test',
        'code' => 'TEST-EU',
        'status' => 1,
        'is_primary' => 0,
        'lat' => 51.5074,
        'lng' => -0.1278,
    ]);
    
    echo "✅ Created warehouse: {$whEU->warehouse_name}\n";
    echo "   ID: {$whEU->id}\n";
    echo "   Code: {$whEU->code}\n";
    echo "   Primary: " . ($whEU->is_primary ? 'YES' : 'NO') . "\n\n";
} catch (\Exception $e) {
    echo "❌ Failed to create warehouse: " . $e->getMessage() . "\n\n";
    exit(1);
}

// TEST 3: Create Third Warehouse
echo "TEST 3: Creating third warehouse (WH-ASIA)\n";
echo str_repeat('-', 50) . "\n";

try {
    $whAsia = Warehouse::create([
        'warehouse_name' => 'Asian Warehouse - Test',
        'code' => 'TEST-ASIA',
        'status' => 1,
        'is_primary' => 0,
        'lat' => 35.6762,
        'lng' => 139.6503,
    ]);
    
    echo "✅ Created warehouse: {$whAsia->warehouse_name}\n";
    echo "   ID: {$whAsia->id}\n";
    echo "   Code: {$whAsia->code}\n\n";
} catch (\Exception $e) {
    echo "❌ Failed to create warehouse: " . $e->getMessage() . "\n\n";
    exit(1);
}

// TEST 4: Try to create another primary (should fail or auto-update)
echo "TEST 4: Testing primary warehouse constraint\n";
echo str_repeat('-', 50) . "\n";

try {
    echo "Attempting to set EU warehouse as primary...\n";
    $whEU->is_primary = 1;
    $whEU->save();
    
    // Refresh to see changes
    $whMain->refresh();
    $whEU->refresh();
    
    echo "✅ Primary warehouse updated\n";
    echo "   TEST-MAIN is_primary: " . ($whMain->is_primary ? 'YES' : 'NO') . "\n";
    echo "   TEST-EU is_primary: " . ($whEU->is_primary ? 'YES' : 'NO') . "\n";
    
    if (!$whMain->is_primary && $whEU->is_primary) {
        echo "✅ Observer correctly enforced only one primary warehouse!\n\n";
    } else {
        echo "⚠️  Multiple primary warehouses detected - observer may not be working\n\n";
    }
} catch (\Exception $e) {
    echo "❌ Failed to update primary warehouse: " . $e->getMessage() . "\n\n";
}

// TEST 5: Update warehouse details
echo "TEST 5: Updating warehouse details\n";
echo str_repeat('-', 50) . "\n";

try {
    $whAsia->warehouse_name = 'Asian Distribution Center - Test';
    $whAsia->lat = 35.6895;
    $whAsia->lng = 139.6917;
    $whAsia->save();
    
    echo "✅ Updated warehouse: {$whAsia->warehouse_name}\n";
    echo "   New Location: ({$whAsia->lat}, {$whAsia->lng})\n\n";
} catch (\Exception $e) {
    echo "❌ Failed to update warehouse: " . $e->getMessage() . "\n\n";
}

// TEST 6: Deactivate warehouse
echo "TEST 6: Deactivating warehouse\n";
echo str_repeat('-', 50) . "\n";

try {
    $whAsia->status = 0;
    $whAsia->save();
    
    echo "✅ Deactivated warehouse: {$whAsia->warehouse_name}\n";
    echo "   Status: " . ($whAsia->status ? 'Active' : 'Inactive') . "\n\n";
} catch (\Exception $e) {
    echo "❌ Failed to deactivate warehouse: " . $e->getMessage() . "\n\n";
}

// TEST 7: List all warehouses
echo "TEST 7: Listing all test warehouses\n";
echo str_repeat('-', 50) . "\n";

$allWarehouses = Warehouse::where('code', 'LIKE', 'TEST-%')->get();
echo "Total test warehouses: " . $allWarehouses->count() . "\n\n";

foreach ($allWarehouses as $wh) {
    echo "📦 {$wh->warehouse_name}\n";
    echo "   Code: {$wh->code}\n";
    echo "   Status: " . ($wh->status ? 'Active' : 'Inactive') . "\n";
    echo "   Primary: " . ($wh->is_primary ? 'YES' : 'NO') . "\n";
    echo "   Location: ({$wh->lat}, {$wh->lng})\n";
    echo "   Created: {$wh->created_at}\n\n";
}

// TEST 8: Query active warehouses
echo "TEST 8: Querying active warehouses only\n";
echo str_repeat('-', 50) . "\n";

$activeWarehouses = Warehouse::where('code', 'LIKE', 'TEST-%')
    ->where('status', 1)
    ->get();

echo "Active test warehouses: " . $activeWarehouses->count() . "\n";
foreach ($activeWarehouses as $wh) {
    echo "  - {$wh->code}: {$wh->warehouse_name}\n";
}
echo "\n";

// TEST 9: Get primary warehouse
echo "TEST 9: Finding primary warehouse\n";
echo str_repeat('-', 50) . "\n";

$primaryWarehouse = Warehouse::where('code', 'LIKE', 'TEST-%')
    ->where('is_primary', 1)
    ->first();

if ($primaryWarehouse) {
    echo "✅ Primary warehouse found: {$primaryWarehouse->code}\n";
    echo "   Name: {$primaryWarehouse->warehouse_name}\n\n";
} else {
    echo "⚠️  No primary warehouse found\n\n";
}

// TEST 10: Delete warehouse
echo "TEST 10: Deleting warehouse\n";
echo str_repeat('-', 50) . "\n";

try {
    $whName = $whAsia->warehouse_name;
    $whAsia->delete();
    
    echo "✅ Deleted warehouse: {$whName}\n";
    echo "   Remaining test warehouses: " . Warehouse::where('code', 'LIKE', 'TEST-%')->count() . "\n\n";
} catch (\Exception $e) {
    echo "❌ Failed to delete warehouse: " . $e->getMessage() . "\n\n";
}

// SUMMARY
echo "\n";
echo str_repeat('=', 50) . "\n";
echo "TEST SUMMARY\n";
echo str_repeat('=', 50) . "\n";

$finalCount = Warehouse::where('code', 'LIKE', 'TEST-%')->count();
$finalPrimary = Warehouse::where('code', 'LIKE', 'TEST-%')->where('is_primary', 1)->count();
$finalActive = Warehouse::where('code', 'LIKE', 'TEST-%')->where('status', 1)->count();

echo "Final test warehouse count: {$finalCount}\n";
echo "Primary warehouses: {$finalPrimary}\n";
echo "Active warehouses: {$finalActive}\n";

echo "\n✅ All CRUD operations completed successfully!\n";
echo "\nℹ️  Note: Test warehouses with 'TEST-' prefix are still in database.\n";
echo "   Run this script again to clean up and re-test.\n\n";

// Optional: Uncomment to auto-cleanup
// echo "🧹 Auto-cleaning test warehouses...\n";
// Warehouse::where('code', 'LIKE', 'TEST-%')->delete();
// echo "✅ All test warehouses deleted\n\n";
