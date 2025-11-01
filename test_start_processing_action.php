<?php

/**
 * Test: START PROCESSING Action
 * 
 * This script validates the "Start Processing" action by:
 * 1. Finding a pending invoice
 * 2. Checking stock availability
 * 3. Starting processing
 * 4. Verifying inventory allocation
 * 5. Verifying OrderItemQuantity records created
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItemQuantity;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Inventory\Models\ProductInventory;

echo "═══════════════════════════════════════════════════════════════════════\n";
echo "           TEST: START PROCESSING ACTION\n";
echo "═══════════════════════════════════════════════════════════════════════\n\n";

// Find a pending invoice to test with
$testInvoice = Order::invoices()
    ->where('order_status', OrderStatus::PENDING)
    ->with(['customer', 'items.warehouse'])
    ->first();

if (!$testInvoice) {
    echo "❌ ERROR: No pending invoices found to test with.\n";
    echo "   Create a pending invoice first.\n";
    exit(1);
}

echo "📋 TEST INVOICE SELECTED\n";
echo "───────────────────────────────────────────────────────────────────────\n";
echo "Invoice Number: {$testInvoice->order_number}\n";
echo "Customer: " . ($testInvoice->customer->business_name ?? $testInvoice->customer->name) . "\n";
echo "Current Status: {$testInvoice->order_status->value}\n";
echo "Items: {$testInvoice->items->count()}\n\n";

// STEP 1: Check current inventory levels BEFORE processing
echo "📦 STEP 1: CHECK STOCK AVAILABILITY\n";
echo "───────────────────────────────────────────────────────────────────────\n";

$stockBeforeProcessing = [];
$allItemsInStock = true;

foreach ($testInvoice->items as $item) {
    echo "Item: {$item->product_name}\n";
    echo "  Ordered Quantity: {$item->quantity}\n";
    
    if (!$item->warehouse_id || !$item->product_variant_id) {
        echo "  ⚠️  Non-stock item (no warehouse or variant)\n";
        $stockBeforeProcessing[$item->id] = [
            'non_stock' => true,
            'available' => 'N/A',
        ];
        continue;
    }
    
    $inventory = ProductInventory::where('product_variant_id', $item->product_variant_id)
        ->where('warehouse_id', $item->warehouse_id)
        ->first();
    
    $available = $inventory ? $inventory->quantity : 0;
    $hasStock = $available >= $item->quantity;
    
    $stockBeforeProcessing[$item->id] = [
        'non_stock' => false,
        'available' => $available,
        'warehouse_name' => $item->warehouse->name ?? 'Unknown',
        'inventory_id' => $inventory ? $inventory->id : null,
    ];
    
    echo "  Warehouse: " . ($item->warehouse->name ?? 'Unknown') . "\n";
    echo "  Available Stock: {$available}\n";
    echo "  Status: " . ($hasStock ? "✅ IN STOCK" : "❌ INSUFFICIENT STOCK") . "\n";
    
    if (!$hasStock) {
        $allItemsInStock = false;
    }
    echo "\n";
}

if (!$allItemsInStock) {
    echo "⚠️  WARNING: Some items have insufficient stock!\n";
    echo "   The action may fail or only partially allocate.\n\n";
}

// STEP 2: Check for existing OrderItemQuantity records (should be none)
echo "📊 STEP 2: CHECK EXISTING ALLOCATIONS\n";
echo "───────────────────────────────────────────────────────────────────────\n";

$orderItemIds = $testInvoice->items->pluck('id')->toArray();
$existingAllocations = OrderItemQuantity::whereIn('order_item_id', $orderItemIds)->count();
echo "Existing OrderItemQuantity records: {$existingAllocations}\n";
echo "Expected: 0 (pending orders shouldn't have allocations)\n";

if ($existingAllocations > 0) {
    echo "⚠️  WARNING: Found existing allocations! This invoice may have been processed before.\n";
}
echo "\n";

// STEP 3: Simulate the "Start Processing" action
echo "🔄 STEP 3: EXECUTE START PROCESSING ACTION\n";
echo "───────────────────────────────────────────────────────────────────────\n";
echo "Changing order_status from 'pending' to 'processing'...\n";

try {
    // This simulates what the Filament action does
    $testInvoice->update([
        'order_status' => OrderStatus::PROCESSING,
    ]);
    
    echo "✅ Order status updated successfully\n";
    echo "   New status: {$testInvoice->fresh()->order_status->value}\n\n";
    
    // The OrderObserver should automatically allocate inventory
    // Give it a moment to process
    sleep(1);
    
} catch (\Exception $e) {
    echo "❌ ERROR: Failed to update order status\n";
    echo "   Error: {$e->getMessage()}\n";
    exit(1);
}

// STEP 4: Verify inventory was allocated
echo "🔍 STEP 4: VERIFY INVENTORY ALLOCATION\n";
echo "───────────────────────────────────────────────────────────────────────\n";

$testInvoice = $testInvoice->fresh(['items']);
$allocationSuccess = true;

foreach ($testInvoice->items as $item) {
    echo "Item: {$item->product_name}\n";
    
    if ($stockBeforeProcessing[$item->id]['non_stock'] ?? false) {
        echo "  ⚠️  Non-stock item (skipped allocation)\n\n";
        continue;
    }
    
    // Check allocated_quantity on order_item
    echo "  Allocated Quantity: {$item->allocated_quantity}\n";
    echo "  Expected: {$item->quantity}\n";
    
    if ($item->allocated_quantity == $item->quantity) {
        echo "  ✅ Allocation successful\n";
    } elseif ($item->allocated_quantity > 0) {
        echo "  ⚠️  Partial allocation only\n";
        $allocationSuccess = false;
    } else {
        echo "  ❌ No allocation made\n";
        $allocationSuccess = false;
    }
    
    // Check inventory was reduced
    if ($stockBeforeProcessing[$item->id]['inventory_id']) {
        $inventoryAfter = ProductInventory::find($stockBeforeProcessing[$item->id]['inventory_id']);
        $beforeQty = $stockBeforeProcessing[$item->id]['available'];
        $afterQty = $inventoryAfter->quantity;
        $reduction = $beforeQty - $afterQty;
        
        echo "  Inventory Before: {$beforeQty}\n";
        echo "  Inventory After: {$afterQty}\n";
        echo "  Reduction: {$reduction}\n";
        
        if ($reduction == $item->allocated_quantity) {
            echo "  ✅ Inventory reduced correctly\n";
        } else {
            echo "  ❌ Inventory reduction mismatch!\n";
            $allocationSuccess = false;
        }
    }
    echo "\n";
}

// STEP 5: Verify OrderItemQuantity records were created
echo "📝 STEP 5: VERIFY ORDER ITEM QUANTITY RECORDS\n";
echo "───────────────────────────────────────────────────────────────────────\n";

$orderItemIds = $testInvoice->items->pluck('id')->toArray();
$allocations = OrderItemQuantity::whereIn('order_item_id', $orderItemIds)
    ->with(['orderItem', 'warehouse'])
    ->get();

echo "OrderItemQuantity records created: {$allocations->count()}\n";
echo "Expected: " . $testInvoice->items->filter(function($item) use ($stockBeforeProcessing) {
    return !($stockBeforeProcessing[$item->id]['non_stock'] ?? false);
})->count() . " (one per stock item)\n\n";

if ($allocations->count() > 0) {
    echo "Allocation Details:\n";
    foreach ($allocations as $allocation) {
        echo "  - {$allocation->orderItem->product_name}\n";
        echo "    Warehouse: {$allocation->warehouse->name}\n";
        echo "    Quantity: {$allocation->quantity}\n";
        echo "    Product Variant ID: {$allocation->product_variant_id}\n";
        echo "\n";
    }
} else {
    echo "❌ No allocation records found!\n";
    echo "   The OrderObserver may not be working correctly.\n\n";
    $allocationSuccess = false;
}

// STEP 6: Final verification summary
echo "═══════════════════════════════════════════════════════════════════════\n";
echo "                    TEST RESULTS SUMMARY\n";
echo "═══════════════════════════════════════════════════════════════════════\n\n";

$testInvoice = $testInvoice->fresh();

echo "✅ Order Status: " . ($testInvoice->order_status->value === 'processing' ? "PASS" : "FAIL") . "\n";
echo "   Current: {$testInvoice->order_status->value}\n";
echo "   Expected: processing\n\n";

echo ($allocationSuccess ? "✅" : "❌") . " Inventory Allocation: " . ($allocationSuccess ? "PASS" : "FAIL") . "\n";
if (!$allocationSuccess) {
    echo "   Some items were not allocated correctly\n";
}
echo "\n";

$expectedRecords = $testInvoice->items->filter(function($item) use ($stockBeforeProcessing) {
    return !($stockBeforeProcessing[$item->id]['non_stock'] ?? false);
})->count();

echo ($allocations->count() === $expectedRecords ? "✅" : "❌") . " OrderItemQuantity Records: " . 
     ($allocations->count() === $expectedRecords ? "PASS" : "FAIL") . "\n";
echo "   Created: {$allocations->count()}\n";
echo "   Expected: {$expectedRecords}\n\n";

// Overall result
$overallPass = $testInvoice->order_status->value === 'processing' && 
               $allocationSuccess && 
               $allocations->count() === $expectedRecords;

echo "═══════════════════════════════════════════════════════════════════════\n";
echo "OVERALL: " . ($overallPass ? "✅ ALL TESTS PASSED" : "❌ SOME TESTS FAILED") . "\n";
echo "═══════════════════════════════════════════════════════════════════════\n\n";

if ($overallPass) {
    echo "🎉 The 'Start Processing' action is working correctly!\n";
    echo "   - Order status changed to processing\n";
    echo "   - Inventory was allocated\n";
    echo "   - OrderItemQuantity records created\n\n";
} else {
    echo "⚠️  Some issues were detected. Review the test output above.\n\n";
}

echo "📌 NOTE: You can now test 'Cancel Order' on this invoice (ID: {$testInvoice->id})\n";
echo "         to verify inventory deallocation works correctly.\n";

exit($overallPass ? 0 : 1);
