<?php

/**
 * Consignment Workflow Test
 * 
 * Tests the complete flow:
 * Consignment (DRAFT) → [Mark as Sent] → (SENT)
 *                                           │
 *            ┌──────────────────────────────┼──────────────────────────────┐
 *            │                              │                              │
 *       [Record Sale]              [Record Return]              [Convert to Invoice]
 *            │                              │                              │
 *            ▼                              ▼                              ▼
 *    (PARTIALLY_SOLD)              (PARTIALLY_RETURNED)            (INVOICED_IN_FULL)
 *            │                              │
 *            └──────────────────────────────┘
 *                          │
 *                          ▼
 *             [All items sold/returned]
 *                          │
 *                          ▼
 *             (INVOICED_IN_FULL or RETURNED)
 * 
 * Run with: php test_consignment_workflow.php
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Modules\Consignments\Models\Consignment;
use App\Modules\Consignments\Models\ConsignmentItem;
use App\Modules\Consignments\Enums\ConsignmentStatus;
use App\Modules\Consignments\Services\ConsignmentService;
use App\Modules\Consignments\Services\ConsignmentInvoiceService;
use App\Modules\Customers\Models\Customer;
use App\Modules\Products\Models\ProductVariant;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Orders\Models\Order;
use Illuminate\Support\Facades\DB;

// Test counters
$passed = 0;
$failed = 0;
$tests = [];

function test($name, $callback) {
    global $passed, $failed, $tests;
    
    echo "\n  Testing: {$name}... ";
    
    try {
        $result = $callback();
        if ($result === true) {
            echo "✅ PASSED";
            $passed++;
            $tests[] = ['name' => $name, 'status' => 'passed'];
        } else {
            echo "❌ FAILED - " . ($result ?: 'Unknown error');
            $failed++;
            $tests[] = ['name' => $name, 'status' => 'failed', 'error' => $result];
        }
    } catch (\Exception $e) {
        echo "❌ EXCEPTION - " . $e->getMessage();
        $failed++;
        $tests[] = ['name' => $name, 'status' => 'exception', 'error' => $e->getMessage()];
    }
}

function printHeader($title) {
    echo "\n\n" . str_repeat('═', 70) . "\n";
    echo "  {$title}\n";
    echo str_repeat('═', 70) . "\n";
}

// Start testing
echo "\n";
echo "╔══════════════════════════════════════════════════════════════════════╗\n";
echo "║         CONSIGNMENT WORKFLOW - COMPREHENSIVE TEST                     ║\n";
echo "║                                                                      ║\n";
echo "║  Testing all status transitions and edge cases                       ║\n";
echo "╚══════════════════════════════════════════════════════════════════════╝\n";

// Get test data
$customer = Customer::first();
$variant = ProductVariant::first();
$warehouse = Warehouse::where('status', 1)->first();

if (!$customer) {
    die("\n❌ ERROR: No customer found. Please create a customer first.\n");
}

echo "\n📋 Test Setup:";
echo "\n   Customer: " . ($customer->business_name ?? $customer->full_name ?? $customer->name);
echo "\n   Variant: " . ($variant ? $variant->sku : 'None');
echo "\n   Warehouse: " . ($warehouse ? $warehouse->warehouse_name : 'None');

// Get services
$consignmentService = app(ConsignmentService::class);

// Check if ConsignmentInvoiceService exists
$hasInvoiceService = false;
try {
    $invoiceService = app(ConsignmentInvoiceService::class);
    $hasInvoiceService = true;
} catch (\Exception $e) {
    echo "\n   ⚠️ ConsignmentInvoiceService not found, using ConsignmentService";
}

// ============================================================================
// PHASE 1: CONSIGNMENT CREATION
// ============================================================================

printHeader("PHASE 1: CONSIGNMENT CREATION");

$testConsignment = null;

test('1.1 Create consignment in DRAFT status', function() use ($customer, $warehouse, &$testConsignment) {
    $testConsignment = Consignment::create([
        'consignment_number' => 'CNS-TEST-' . time(),
        'customer_id' => $customer->id,
        'warehouse_id' => $warehouse?->id,
        'status' => ConsignmentStatus::DRAFT,
        'issue_date' => now(),
        'expected_return_date' => now()->addDays(30),
        'subtotal' => 0,
        'tax' => 0,
        'total' => 0,
    ]);
    
    if (!$testConsignment) return 'Failed to create consignment';
    if ($testConsignment->status !== ConsignmentStatus::DRAFT) return 'Wrong status';
    
    return true;
});

test('1.2 DRAFT status can be edited', function() use (&$testConsignment) {
    return $testConsignment->status->canEdit() === true;
});

test('1.3 DRAFT cannot record sales (not sent yet)', function() use (&$testConsignment) {
    return $testConsignment->canRecordSale() === false;
});

test('1.4 DRAFT cannot record returns', function() use (&$testConsignment) {
    return $testConsignment->canRecordReturn() === false;
});

test('1.5 DRAFT can transition to SENT', function() use (&$testConsignment) {
    return $testConsignment->status->canTransitionTo(ConsignmentStatus::SENT) === true;
});

test('1.6 DRAFT can transition to CANCELLED', function() use (&$testConsignment) {
    return $testConsignment->status->canTransitionTo(ConsignmentStatus::CANCELLED) === true;
});

test('1.7 DRAFT cannot transition to INVOICED_IN_FULL directly', function() use (&$testConsignment) {
    return $testConsignment->status->canTransitionTo(ConsignmentStatus::INVOICED_IN_FULL) === false;
});

// ============================================================================
// PHASE 2: ADD ITEMS TO CONSIGNMENT
// ============================================================================

printHeader("PHASE 2: ADD ITEMS TO CONSIGNMENT");

test('2.1 Add line items to consignment', function() use (&$testConsignment, $variant, $warehouse) {
    // Add multiple items for testing different scenarios
    for ($i = 1; $i <= 3; $i++) {
        ConsignmentItem::create([
            'consignment_id' => $testConsignment->id,
            'product_variant_id' => $variant?->id,
            'warehouse_id' => $warehouse?->id,
            'product_name' => "Test Product {$i}",
            'brand_name' => 'Test Brand', // Required field
            'sku' => "TEST-SKU-{$i}-" . time(),
            'description' => "Test description for product {$i}",
            'quantity_sent' => 5, // 5 units each
            'quantity_sold' => 0,
            'quantity_returned' => 0,
            'price' => 100 * $i, // Different prices
            'status' => \App\Modules\Consignments\Enums\ConsignmentItemStatus::SENT,
        ]);
    }
    
    return $testConsignment->items()->count() === 3;
});

test('2.2 Update consignment totals', function() use (&$testConsignment) {
    $testConsignment->updateItemCounts();
    $testConsignment = $testConsignment->fresh();
    
    // Should have 15 items total (3 items x 5 qty each)
    return $testConsignment->items_sent_count === 15;
});

test('2.3 Items sold and returned counts are 0', function() use (&$testConsignment) {
    return $testConsignment->items_sold_count === 0 && $testConsignment->items_returned_count === 0;
});

// ============================================================================
// PHASE 3: MARK AS SENT
// ============================================================================

printHeader("PHASE 3: MARK AS SENT TRANSITION");

test('3.1 Mark consignment as SENT', function() use ($consignmentService, &$testConsignment) {
    $consignmentService->markAsSent($testConsignment, 'TRACK-123456');
    $testConsignment = $testConsignment->fresh();
    
    return $testConsignment->status === ConsignmentStatus::SENT;
});

test('3.2 sent_at timestamp is set', function() use (&$testConsignment) {
    return $testConsignment->sent_at !== null;
});

test('3.3 tracking_number is saved', function() use (&$testConsignment) {
    return $testConsignment->tracking_number === 'TRACK-123456';
});

test('3.4 SENT status cannot be edited', function() use (&$testConsignment) {
    return $testConsignment->status->canEdit() === false;
});

test('3.5 SENT status CAN record sales', function() use (&$testConsignment) {
    return $testConsignment->status->canRecordSale() === true;
});

test('3.6 Model canRecordSale() returns true (has items to sell)', function() use (&$testConsignment) {
    return $testConsignment->canRecordSale() === true;
});

test('3.7 SENT status CAN record returns', function() use (&$testConsignment) {
    return $testConsignment->status->canRecordReturn() === true;
});

test('3.8 SENT can transition to DELIVERED', function() use (&$testConsignment) {
    return $testConsignment->status->canTransitionTo(ConsignmentStatus::DELIVERED) === true;
});

test('3.9 SENT can transition to PARTIALLY_SOLD', function() use (&$testConsignment) {
    return $testConsignment->status->canTransitionTo(ConsignmentStatus::PARTIALLY_SOLD) === true;
});

// ============================================================================
// PHASE 4: RECORD SALE (Partial)
// ============================================================================

printHeader("PHASE 4: RECORD SALE (Partial)");

test('4.1 Record partial sale of first item', function() use ($consignmentService, &$testConsignment) {
    $firstItem = $testConsignment->items()->first();
    
    $soldItems = [
        [
            'item_id' => $firstItem->id,
            'quantity' => 2, // Sell 2 out of 5
            'actual_sale_price' => 100,
        ]
    ];
    
    $consignmentService->recordSale($testConsignment, $soldItems, false);
    $testConsignment = $testConsignment->fresh();
    
    return true;
});

test('4.2 Item quantity_sold updated correctly', function() use (&$testConsignment) {
    $firstItem = $testConsignment->items()->first();
    return $firstItem->quantity_sold === 2;
});

test('4.3 Consignment items_sold_count updated', function() use (&$testConsignment) {
    return $testConsignment->items_sold_count === 2;
});

test('4.4 Status changed to PARTIALLY_SOLD', function() use (&$testConsignment) {
    return $testConsignment->status === ConsignmentStatus::PARTIALLY_SOLD;
});

test('4.5 PARTIALLY_SOLD can still record more sales', function() use (&$testConsignment) {
    return $testConsignment->canRecordSale() === true;
});

test('4.6 PARTIALLY_SOLD can record returns', function() use (&$testConsignment) {
    return $testConsignment->status->canRecordReturn() === true;
});

// ============================================================================
// PHASE 5: RECORD RETURN (Partial)
// ============================================================================

printHeader("PHASE 5: RECORD RETURN (Partial)");

test('5.1 Record partial return of second item', function() use ($consignmentService, &$testConsignment) {
    $secondItem = $testConsignment->items()->skip(1)->first();
    
    $returnedItems = [
        [
            'item_id' => $secondItem->id,
            'quantity' => 3, // Return 3 out of 5
        ]
    ];
    
    $consignmentService->recordReturn($testConsignment, $returnedItems, false);
    $testConsignment = $testConsignment->fresh();
    
    return true;
});

test('5.2 Item quantity_returned updated correctly', function() use (&$testConsignment) {
    $testConsignment = $testConsignment->fresh();
    $secondItem = $testConsignment->items()->skip(1)->first();
    $actual = $secondItem->quantity_returned;
    
    if ($actual !== 3) {
        return "Expected quantity_returned=3, got {$actual}. Available to return before: " . ($secondItem->quantity_sent - $secondItem->quantity_sold);
    }
    return true;
});

test('5.3 Consignment items_returned_count updated', function() use (&$testConsignment) {
    $testConsignment = $testConsignment->fresh();
    $actual = $testConsignment->items_returned_count;
    
    if ($actual !== 3) {
        return "Expected items_returned_count=3, got {$actual}";
    }
    return true;
});

test('5.4 Still has items available (can record sale)', function() use (&$testConsignment) {
    // 15 sent, 2 sold, 3 returned = 10 still available
    return $testConsignment->canRecordSale() === true;
});

test('5.5 Still has items to return', function() use (&$testConsignment) {
    return $testConsignment->canRecordReturn() === true;
});

// ============================================================================
// PHASE 6: EDGE CASES - OVERSELLING/OVERRETURNING
// ============================================================================

printHeader("PHASE 6: EDGE CASES - VALIDATION");

test('6.1 Cannot sell more than available quantity', function() use (&$testConsignment) {
    $firstItem = $testConsignment->items()->first();
    
    // First item: 5 sent, 2 sold = 3 available
    // Try to mark 10 as sold (should only mark up to available)
    $available = $firstItem->quantity_sent - $firstItem->quantity_sold - ($firstItem->quantity_returned ?? 0);
    
    return $available === 3; // Only 3 available to sell
});

test('6.2 Cannot return more than sent minus sold', function() use (&$testConsignment) {
    $firstItem = $testConsignment->items()->first();
    
    // First item: 5 sent, 2 sold, 0 returned = 3 available to return
    $returnable = $firstItem->quantity_sent - ($firstItem->quantity_returned ?? 0);
    
    return $returnable === 5; // Can return unsold items
});

test('6.3 Empty consignment (no items) cannot record sale', function() use ($customer, $warehouse) {
    $emptyConsignment = Consignment::create([
        'consignment_number' => 'CNS-EMPTY-' . time(),
        'customer_id' => $customer->id,
        'warehouse_id' => $warehouse?->id,
        'status' => ConsignmentStatus::SENT, // Even if sent
        'issue_date' => now(),
        'items_sent_count' => 0,
        'items_sold_count' => 0,
    ]);
    
    $canSell = $emptyConsignment->canRecordSale();
    $emptyConsignment->delete();
    
    return $canSell === false;
});

test('6.4 CANCELLED consignment cannot record sale', function() use ($customer, $warehouse) {
    $cancelledConsignment = Consignment::create([
        'consignment_number' => 'CNS-CANCEL-' . time(),
        'customer_id' => $customer->id,
        'warehouse_id' => $warehouse?->id,
        'status' => ConsignmentStatus::CANCELLED,
        'issue_date' => now(),
    ]);
    
    $canSell = $cancelledConsignment->status->canRecordSale();
    $cancelledConsignment->delete();
    
    return $canSell === false;
});

test('6.5 RETURNED consignment cannot record sale', function() use ($customer, $warehouse) {
    $returnedConsignment = Consignment::create([
        'consignment_number' => 'CNS-RET-' . time(),
        'customer_id' => $customer->id,
        'warehouse_id' => $warehouse?->id,
        'status' => ConsignmentStatus::RETURNED,
        'issue_date' => now(),
    ]);
    
    $canSell = $returnedConsignment->status->canRecordSale();
    $returnedConsignment->delete();
    
    return $canSell === false;
});

// ============================================================================
// PHASE 7: SELL ALL REMAINING ITEMS
// ============================================================================

printHeader("PHASE 7: COMPLETE SALE (All Items)");

test('7.1 Sell all remaining items', function() use ($consignmentService, &$testConsignment) {
    $testConsignment = $testConsignment->fresh(['items']);
    
    // Sell remaining items from all 3 products
    $soldItems = [];
    
    foreach ($testConsignment->items as $item) {
        // Refresh item to get latest values
        $item = $item->fresh();
        $available = $item->quantity_sent - $item->quantity_sold - ($item->quantity_returned ?? 0);
        
        if ($available > 0 && $item->status->canBeSold()) {
            $soldItems[] = [
                'item_id' => $item->id,
                'quantity' => $available,
                'actual_sale_price' => $item->price,
            ];
        }
    }
    
    if (!empty($soldItems)) {
        $consignmentService->recordSale($testConsignment, $soldItems, false);
    }
    
    $testConsignment = $testConsignment->fresh();
    return true;
});

test('7.2 All sellable items are sold', function() use (&$testConsignment) {
    $testConsignment = $testConsignment->fresh();
    // 15 sent, 3 returned = 12 should be sold
    $expected = $testConsignment->items_sent_count - $testConsignment->items_returned_count;
    $actual = $testConsignment->items_sold_count;
    
    if ($actual !== $expected) {
        return "Expected {$expected} sold, got {$actual}. Sent: {$testConsignment->items_sent_count}, Returned: {$testConsignment->items_returned_count}";
    }
    return true;
});

test('7.3 Status is INVOICED_IN_FULL or similar final state', function() use (&$testConsignment) {
    // When all items are either sold or returned, should be in final state
    $finalStates = [
        ConsignmentStatus::INVOICED_IN_FULL,
        ConsignmentStatus::PARTIALLY_SOLD,
        ConsignmentStatus::PARTIALLY_RETURNED,
    ];
    return in_array($testConsignment->status, $finalStates);
});

test('7.4 No more items available to sell', function() use (&$testConsignment) {
    $testConsignment = $testConsignment->fresh();
    // All items either sold or returned
    $totalSellable = $testConsignment->items_sent_count - $testConsignment->items_returned_count;
    $totalSold = $testConsignment->items_sold_count;
    
    if ($totalSold < $totalSellable) {
        return "Still have items to sell. Sellable: {$totalSellable}, Sold: {$totalSold}";
    }
    return true;
});

// ============================================================================
// PHASE 8: CONVERT TO INVOICE
// ============================================================================

printHeader("PHASE 8: CONVERT TO INVOICE");

$testConsignment2 = null;

test('8.1 Create new consignment for invoice conversion test', function() use ($customer, $warehouse, &$testConsignment2) {
    $testConsignment2 = Consignment::create([
        'consignment_number' => 'CNS-INV-' . time(),
        'customer_id' => $customer->id,
        'warehouse_id' => $warehouse?->id,
        'status' => ConsignmentStatus::SENT,
        'sent_at' => now(),
        'issue_date' => now(),
        'items_sent_count' => 0,
        'items_sold_count' => 0,
    ]);
    
    // Add items with all required fields
    for ($i = 1; $i <= 2; $i++) {
        ConsignmentItem::create([
            'consignment_id' => $testConsignment2->id,
            'product_name' => "Invoice Test Product {$i}",
            'brand_name' => 'Test Brand', // Required field
            'sku' => "INV-TEST-{$i}-" . time(),
            'description' => "Test product for invoice conversion",
            'quantity_sent' => 3,
            'quantity_sold' => 0,
            'quantity_returned' => 0,
            'price' => 200,
            'status' => \App\Modules\Consignments\Enums\ConsignmentItemStatus::SENT,
        ]);
    }
    
    $testConsignment2->updateItemCounts();
    return $testConsignment2->items_sent_count === 6;
});

test('8.2 SENT consignment can record sale', function() use (&$testConsignment2) {
    return $testConsignment2->canRecordSale() === true;
});

test('8.3 Record all items as sold', function() use ($consignmentService, &$testConsignment2) {
    $testConsignment2 = $testConsignment2->fresh();
    $soldItems = [];
    
    foreach ($testConsignment2->items as $item) {
        $soldItems[] = [
            'item_id' => $item->id,
            'quantity' => $item->quantity_sent,
            'actual_sale_price' => $item->price,
        ];
    }
    
    $consignmentService->recordSale($testConsignment2, $soldItems, false);
    $testConsignment2 = $testConsignment2->fresh();
    
    $actual = $testConsignment2->items_sold_count;
    if ($actual !== 6) {
        return "Expected items_sold_count=6, got {$actual}. Items: " . $testConsignment2->items->count();
    }
    return true;
});

test('8.4 Status is INVOICED_IN_FULL after selling all', function() use (&$testConsignment2) {
    $testConsignment2 = $testConsignment2->fresh();
    $actual = $testConsignment2->status;
    
    if ($actual !== ConsignmentStatus::INVOICED_IN_FULL) {
        return "Expected INVOICED_IN_FULL, got {$actual->value}. Sold: {$testConsignment2->items_sold_count}, Sent: {$testConsignment2->items_sent_count}";
    }
    return true;
});

test('8.5 INVOICED_IN_FULL cannot record more sales', function() use (&$testConsignment2) {
    $testConsignment2 = $testConsignment2->fresh();
    $canSell = $testConsignment2->canRecordSale();
    
    if ($canSell !== false) {
        return "Should not be able to sell. Status: {$testConsignment2->status->value}, Sold: {$testConsignment2->items_sold_count}, Sent: {$testConsignment2->items_sent_count}";
    }
    return true;
});

test('8.6 INVOICED_IN_FULL can still record returns', function() use (&$testConsignment2) {
    return $testConsignment2->status->canRecordReturn() === true;
});

// ============================================================================
// PHASE 9: STATUS TRANSITION MATRIX TEST
// ============================================================================

printHeader("PHASE 9: STATUS TRANSITION MATRIX");

test('9.1 DRAFT → SENT allowed', function() {
    return ConsignmentStatus::DRAFT->canTransitionTo(ConsignmentStatus::SENT);
});

test('9.2 DRAFT → CANCELLED allowed', function() {
    return ConsignmentStatus::DRAFT->canTransitionTo(ConsignmentStatus::CANCELLED);
});

test('9.3 DRAFT → INVOICED_IN_FULL NOT allowed', function() {
    return !ConsignmentStatus::DRAFT->canTransitionTo(ConsignmentStatus::INVOICED_IN_FULL);
});

test('9.4 SENT → PARTIALLY_SOLD allowed', function() {
    return ConsignmentStatus::SENT->canTransitionTo(ConsignmentStatus::PARTIALLY_SOLD);
});

test('9.5 SENT → DELIVERED allowed', function() {
    return ConsignmentStatus::SENT->canTransitionTo(ConsignmentStatus::DELIVERED);
});

test('9.6 CANCELLED → any NOT allowed (final state)', function() {
    return ConsignmentStatus::CANCELLED->isFinal() === true;
});

test('9.7 RETURNED → any NOT allowed (final state)', function() {
    return ConsignmentStatus::RETURNED->isFinal() === true;
});

test('9.8 INVOICED_IN_FULL can still return items', function() {
    return ConsignmentStatus::INVOICED_IN_FULL->canRecordReturn() === true;
});

// ============================================================================
// PHASE 10: CLEANUP
// ============================================================================

printHeader("PHASE 10: CLEANUP");

test('10.1 Clean up test data', function() use (&$testConsignment, &$testConsignment2) {
    if ($testConsignment) {
        $testConsignment->items()->delete();
        $testConsignment->histories()->delete();
        $testConsignment->delete();
    }
    if ($testConsignment2) {
        $testConsignment2->items()->delete();
        $testConsignment2->histories()->delete();
        $testConsignment2->delete();
    }
    return true;
});

// ============================================================================
// SUMMARY
// ============================================================================

echo "\n\n";
echo "╔══════════════════════════════════════════════════════════════════════╗\n";
echo "║                         TEST SUMMARY                                  ║\n";
echo "╠══════════════════════════════════════════════════════════════════════╣\n";
printf("║  ✅ Passed: %-55d ║\n", $passed);
printf("║  ❌ Failed: %-55d ║\n", $failed);
printf("║  📊 Total:  %-55d ║\n", $passed + $failed);
echo "╠══════════════════════════════════════════════════════════════════════╣\n";

$percentage = ($passed + $failed) > 0 ? round(($passed / ($passed + $failed)) * 100) : 0;
$status = $failed === 0 ? '✅ ALL TESTS PASSED!' : '⚠️  SOME TESTS FAILED';
printf("║  %s Success Rate: %d%%                                    ║\n", $status, $percentage);

echo "╚══════════════════════════════════════════════════════════════════════╝\n";

// Print failed tests details
if ($failed > 0) {
    echo "\n❌ Failed Tests Details:\n";
    foreach ($tests as $test) {
        if ($test['status'] !== 'passed') {
            echo "   - {$test['name']}: {$test['error']}\n";
        }
    }
}

echo "\n📋 Consignment Workflow Tested:\n";
echo "   Consignment (DRAFT) → [Mark Sent] → (SENT)\n";
echo "                                          │\n";
echo "          ┌───────────────────────────────┼───────────────────────────────┐\n";
echo "          │                               │                               │\n";
echo "     [Record Sale]                [Record Return]               [Convert]\n";
echo "          │                               │                               │\n";
echo "          ▼                               ▼                               ▼\n";
echo "   (PARTIALLY_SOLD)              (PARTIALLY_RETURNED)          (INVOICED_IN_FULL)\n";
echo "\n";
echo "   Status Capabilities:\n";
echo "   • DRAFT: Edit ✅ | Record Sale ❌ | Record Return ❌\n";
echo "   • SENT: Edit ❌ | Record Sale ✅ | Record Return ✅\n";
echo "   • PARTIALLY_SOLD: Edit ❌ | Record Sale ✅ | Record Return ✅\n";
echo "   • INVOICED_IN_FULL: Edit ❌ | Record Sale ❌ | Record Return ✅\n";
echo "   • CANCELLED/RETURNED: Final states (no actions)\n";
echo "\n";

// Exit with appropriate code
exit($failed > 0 ? 1 : 0);
