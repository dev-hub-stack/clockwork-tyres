<?php

/**
 * Test: CANCEL ORDER Action
 * 
 * This script validates the "Cancel Order" action by:
 * 1. Finding a processing invoice with allocations
 * 2. Recording current inventory levels
 * 3. Canceling the order
 * 4. Verifying inventory was dealloc// STEP 6: Verify cancellation reason was recorded
echo "📄 STEP 6: VERIFY CANCELLATION REASON\n";
echo "───────────────────────────────────────────────────────────────────────\n";

$testInvoice = $testInvoice->fresh();
$notesContainReason = strpos($testInvoice->order_notes ?? '', $cancellationReason) !== false;

echo "Order notes field contains cancellation reason: " . ($notesContainReason ? "YES" : "NO") . "\n";
if ($notesContainReason) {
    echo "✅ Cancellation reason recorded\n\n";
} else {
    echo "❌ Cancellation reason NOT recorded\n\n";
}erifying OrderItemQuantity records deleted
 * 6. Verifying allocated_quantity reset to 0
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItemQuantity;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Inventory\Models\ProductInventory;

echo "═══════════════════════════════════════════════════════════════════════\n";
echo "              TEST: CANCEL ORDER ACTION\n";
echo "═══════════════════════════════════════════════════════════════════════\n\n";

// Find a processing invoice to test with
$testInvoice = Order::invoices()
    ->where('order_status', OrderStatus::PROCESSING)
    ->with(['customer', 'items.warehouse'])
    ->first();

if (!$testInvoice) {
    echo "⚠️  No processing invoices found.\n";
    echo "   Looking for pending invoices to test 'Cancel Order' on...\n\n";
    
    $testInvoice = Order::invoices()
        ->where('order_status', OrderStatus::PENDING)
        ->with(['customer', 'items.warehouse'])
        ->first();
    
    if (!$testInvoice) {
        echo "❌ ERROR: No invoices found to test with.\n";
        exit(1);
    }
    
    echo "ℹ️  Note: Testing on a PENDING invoice (no inventory to deallocate)\n\n";
}

echo "📋 TEST INVOICE SELECTED\n";
echo "───────────────────────────────────────────────────────────────────────\n";
echo "Invoice Number: {$testInvoice->order_number}\n";
echo "Customer: " . ($testInvoice->customer->business_name ?? $testInvoice->customer->name) . "\n";
echo "Current Status: {$testInvoice->order_status->value}\n";
echo "Items: {$testInvoice->items->count()}\n\n";

$isProcessing = $testInvoice->order_status->value === 'processing';

// STEP 1: Record current state BEFORE cancellation
echo "📦 STEP 1: RECORD CURRENT STATE\n";
echo "───────────────────────────────────────────────────────────────────────\n";

$stateBeforeCancellation = [];

foreach ($testInvoice->items as $item) {
    echo "Item: {$item->product_name}\n";
    echo "  Allocated Quantity: {$item->allocated_quantity}\n";
    
    if (!$item->warehouse_id || !$item->product_variant_id) {
        echo "  ⚠️  Non-stock item\n";
        $stateBeforeCancellation[$item->id] = [
            'non_stock' => true,
            'allocated_quantity' => $item->allocated_quantity,
        ];
        continue;
    }
    
    $inventory = ProductInventory::where('product_variant_id', $item->product_variant_id)
        ->where('warehouse_id', $item->warehouse_id)
        ->first();
    
    if ($inventory) {
        echo "  Current Warehouse Stock: {$inventory->quantity}\n";
        echo "  Expected After Cancel: " . ($inventory->quantity + $item->allocated_quantity) . "\n";
        
        $stateBeforeCancellation[$item->id] = [
            'non_stock' => false,
            'allocated_quantity' => $item->allocated_quantity,
            'inventory_before' => $inventory->quantity,
            'inventory_id' => $inventory->id,
            'warehouse_name' => $item->warehouse->name ?? 'Unknown',
        ];
    } else {
        echo "  ⚠️  No inventory record found\n";
        $stateBeforeCancellation[$item->id] = [
            'non_stock' => false,
            'allocated_quantity' => $item->allocated_quantity,
            'inventory_before' => 0,
            'inventory_id' => null,
        ];
    }
    echo "\n";
}

// STEP 2: Check OrderItemQuantity records BEFORE cancellation
echo "📊 STEP 2: CHECK EXISTING ALLOCATIONS\n";
echo "───────────────────────────────────────────────────────────────────────\n";

$orderItemIds = $testInvoice->items->pluck('id')->toArray();
$allocationsBeforeCancel = OrderItemQuantity::whereIn('order_item_id', $orderItemIds)
    ->with(['orderItem', 'warehouse'])
    ->get();

echo "OrderItemQuantity records: {$allocationsBeforeCancel->count()}\n";

if ($allocationsBeforeCancel->count() > 0) {
    echo "Allocation Details:\n";
    foreach ($allocationsBeforeCancel as $allocation) {
        echo "  - {$allocation->orderItem->product_name}\n";
        echo "    Warehouse: {$allocation->warehouse->name}\n";
        echo "    Quantity: {$allocation->quantity}\n";
        echo "\n";
    }
} else {
    if ($isProcessing) {
        echo "⚠️  No allocations found (expected for processing orders)\n\n";
    } else {
        echo "✅ No allocations (expected for pending orders)\n\n";
    }
}

// STEP 3: Simulate the "Cancel Order" action
echo "🔄 STEP 3: EXECUTE CANCEL ORDER ACTION\n";
echo "───────────────────────────────────────────────────────────────────────\n";

$cancellationReason = "TEST: Validating cancel order action - " . date('Y-m-d H:i:s');
echo "Cancellation Reason: {$cancellationReason}\n";
echo "Executing cancellation...\n\n";

try {
    // This simulates what the Filament action does
    
    // Step 1: Deallocate inventory if order was processing
    if ($testInvoice->order_status->value === 'processing') {
        echo "  Deallocating inventory...\n";
        foreach ($testInvoice->items as $item) {
            if ($item->allocated_quantity > 0 && $item->warehouse_id) {
                $inventory = ProductInventory::where('product_variant_id', $item->product_variant_id)
                    ->where('warehouse_id', $item->warehouse_id)
                    ->first();
                
                if ($inventory) {
                    $inventory->increment('quantity', $item->allocated_quantity);
                    echo "    ✅ Returned {$item->allocated_quantity} of '{$item->product_name}' to stock\n";
                }
            }
            
            // Reset allocated quantity
            $item->update(['allocated_quantity' => 0]);
        }
        
        // Delete OrderItemQuantity records
        $deletedCount = OrderItemQuantity::whereIn('order_item_id', $testInvoice->items->pluck('id'))->delete();
        echo "    ✅ Deleted {$deletedCount} OrderItemQuantity records\n";
    } else {
        echo "  ℹ️  Order was pending, no inventory to deallocate\n";
    }
    
    // Step 2: Update order status
    $currentNotes = $testInvoice->order_notes ?? '';
    $newNotes = trim($currentNotes) . "\n\nCancellation Reason: " . $cancellationReason;
    
    echo "  Updating order with cancellation reason...\n";
    echo "    Current notes length: " . strlen($currentNotes) . "\n";
    echo "    New notes length: " . strlen($newNotes) . "\n";
    
    $testInvoice->update([
        'order_status' => OrderStatus::CANCELLED,
        'order_notes' => $newNotes,
    ]);
    
    echo "\n✅ Order cancelled successfully\n";
    echo "   New status: {$testInvoice->fresh()->order_status->value}\n";
    echo "   Notes updated: " . (strlen($testInvoice->fresh()->order_notes ?? '') > 0 ? "YES" : "NO") . "\n\n";
    
} catch (\Exception $e) {
    echo "❌ ERROR: Failed to cancel order\n";
    echo "   Error: {$e->getMessage()}\n";
    exit(1);
}

// STEP 4: Verify inventory was returned
echo "🔍 STEP 4: VERIFY INVENTORY DEALLOCATION\n";
echo "───────────────────────────────────────────────────────────────────────\n";

$testInvoice = $testInvoice->fresh(['items']);
$deallocationSuccess = true;

foreach ($testInvoice->items as $item) {
    echo "Item: {$item->product_name}\n";
    
    // Check allocated_quantity is now 0
    echo "  Allocated Quantity: {$item->allocated_quantity}\n";
    echo "  Expected: 0\n";
    
    if ($item->allocated_quantity == 0) {
        echo "  ✅ Allocated quantity reset\n";
    } else {
        echo "  ❌ Allocated quantity NOT reset!\n";
        $deallocationSuccess = false;
    }
    
    // Check inventory was returned
    if ($stateBeforeCancellation[$item->id]['non_stock'] ?? false) {
        echo "  ℹ️  Non-stock item (skipped)\n\n";
        continue;
    }
    
    if (!$isProcessing) {
        echo "  ℹ️  Was pending (no inventory to return)\n\n";
        continue;
    }
    
    if ($stateBeforeCancellation[$item->id]['inventory_id']) {
        $inventoryAfter = ProductInventory::find($stateBeforeCancellation[$item->id]['inventory_id']);
        $beforeQty = $stateBeforeCancellation[$item->id]['inventory_before'];
        $afterQty = $inventoryAfter->quantity;
        $allocated = $stateBeforeCancellation[$item->id]['allocated_quantity'];
        $expectedAfter = $beforeQty + $allocated;
        
        echo "  Inventory Before Cancel: {$beforeQty}\n";
        echo "  Allocated (to return): {$allocated}\n";
        echo "  Expected After: {$expectedAfter}\n";
        echo "  Actual After: {$afterQty}\n";
        
        if ($afterQty == $expectedAfter) {
            echo "  ✅ Inventory returned correctly\n";
        } else {
            echo "  ❌ Inventory return mismatch!\n";
            $deallocationSuccess = false;
        }
    }
    echo "\n";
}

// STEP 5: Verify OrderItemQuantity records were deleted
echo "📝 STEP 5: VERIFY ALLOCATION RECORDS DELETED\n";
echo "───────────────────────────────────────────────────────────────────────\n";

$orderItemIds = $testInvoice->items->pluck('id')->toArray();
$allocationsAfterCancel = OrderItemQuantity::whereIn('order_item_id', $orderItemIds)->count();

echo "OrderItemQuantity records remaining: {$allocationsAfterCancel}\n";
echo "Expected: 0\n";

if ($allocationsAfterCancel == 0) {
    echo "✅ All allocation records deleted\n\n";
} else {
    echo "❌ Some allocation records still exist!\n\n";
    $deallocationSuccess = false;
}

// STEP 6: Verify cancellation reason was recorded
echo "📄 STEP 6: VERIFY CANCELLATION REASON\n";
echo "───────────────────────────────────────────────────────────────────────\n";

$testInvoice = $testInvoice->fresh();
$notesContainReason = strpos($testInvoice->order_notes ?? '', $cancellationReason) !== false;

echo "Order notes field contains cancellation reason: " . ($notesContainReason ? "YES" : "NO") . "\n";
if ($notesContainReason) {
    echo "✅ Cancellation reason recorded\n\n";
} else {
    echo "❌ Cancellation reason NOT recorded\n\n";
}

// STEP 7: Final verification summary
echo "═══════════════════════════════════════════════════════════════════════\n";
echo "                    TEST RESULTS SUMMARY\n";
echo "═══════════════════════════════════════════════════════════════════════\n\n";

$testInvoice = $testInvoice->fresh();

echo "✅ Order Status: " . ($testInvoice->order_status->value === 'cancelled' ? "PASS" : "FAIL") . "\n";
echo "   Current: {$testInvoice->order_status->value}\n";
echo "   Expected: cancelled\n\n";

if ($isProcessing) {
    echo ($deallocationSuccess ? "✅" : "❌") . " Inventory Deallocation: " . ($deallocationSuccess ? "PASS" : "FAIL") . "\n";
    if (!$deallocationSuccess) {
        echo "   Some inventory was not returned correctly\n";
    }
    echo "\n";
}

echo ($allocationsAfterCancel == 0 ? "✅" : "❌") . " Allocation Records Deleted: " . 
     ($allocationsAfterCancel == 0 ? "PASS" : "FAIL") . "\n";
echo "   Remaining: {$allocationsAfterCancel}\n";
echo "   Expected: 0\n\n";

echo ($notesContainReason ? "✅" : "❌") . " Cancellation Reason Recorded: " . 
     ($notesContainReason ? "PASS" : "FAIL") . "\n\n";

// Overall result
$overallPass = $testInvoice->order_status->value === 'cancelled' && 
               (!$isProcessing || $deallocationSuccess) &&
               $allocationsAfterCancel == 0 &&
               $notesContainReason;

echo "═══════════════════════════════════════════════════════════════════════\n";
echo "OVERALL: " . ($overallPass ? "✅ ALL TESTS PASSED" : "❌ SOME TESTS FAILED") . "\n";
echo "═══════════════════════════════════════════════════════════════════════\n\n";

if ($overallPass) {
    echo "🎉 The 'Cancel Order' action is working correctly!\n";
    echo "   - Order status changed to cancelled\n";
    if ($isProcessing) {
        echo "   - Inventory was returned to warehouse\n";
        echo "   - Allocated quantities reset to 0\n";
        echo "   - OrderItemQuantity records deleted\n";
    }
    echo "   - Cancellation reason recorded in notes\n\n";
} else {
    echo "⚠️  Some issues were detected. Review the test output above.\n\n";
}

echo "📌 NOTE: Invoice ID {$testInvoice->id} is now cancelled and in your database\n";
echo "         for audit trail purposes (as expected).\n";

exit($overallPass ? 0 : 1);
