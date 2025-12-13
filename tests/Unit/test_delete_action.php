<?php

/**
 * Test: DELETE Action
 * 
 * This script validates the "Delete" action by:
 * 1. Creating a test invoice (or using existing)
 * 2. Recording all related data
 * 3. Deleting the invoice
 * 4. Verifying all related records are removed
 * 5. Warning about inventory handling
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;
use App\Modules\Orders\Models\OrderItemQuantity;
use App\Modules\Orders\Models\Payment;
use App\Modules\Customers\Models\Customer;
use App\Modules\Warehouses\Models\Warehouse;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;

echo "═══════════════════════════════════════════════════════════════════════\n";
echo "                TEST: DELETE ACTION\n";
echo "═══════════════════════════════════════════════════════════════════════\n\n";

echo "⚠️  WARNING: This test uses the DELETE action.\n";
echo "   Only use DELETE for test data!\n";
echo "   For real orders, use 'Cancel Order' instead.\n\n";

// Try to find a test invoice (one marked as test or oldest)
$testInvoice = Order::invoices()
    ->where('order_number', 'LIKE', '%TEST%')
    ->with(['customer', 'items', 'payments'])
    ->first();

if (!$testInvoice) {
    echo "ℹ️  No test invoices found (order_number contains 'TEST').\n";
    echo "   Will create a new test invoice for deletion...\n\n";
    
    // Create a test invoice
    $customer = Customer::first();
    $warehouse = Warehouse::first();
    
    if (!$customer || !$warehouse) {
        echo "❌ ERROR: Need at least one customer and warehouse in database.\n";
        exit(1);
    }
    
    $testInvoice = Order::create([
        'document_type' => 'invoice',
        'order_number' => 'INV-DELETE-TEST-' . time(),
        'customer_id' => $customer->id,
        'warehouse_id' => $warehouse->id,
        'order_status' => OrderStatus::PENDING,
        'payment_status' => PaymentStatus::PENDING,
        'issue_date' => now(),
        'valid_until' => now()->addDays(30),
        'subtotal' => 100,
        'tax_amount' => 5,
        'total_amount' => 105,
        'notes' => 'TEST INVOICE FOR DELETION - Created by test script',
    ]);
    
    // Add a test item
    OrderItem::create([
        'order_id' => $testInvoice->id,
        'product_name' => 'Test Product for Deletion',
        'quantity' => 1,
        'unit_price' => 100,
        'tax_amount' => 5,
        'total_price' => 105,
    ]);
    
    $testInvoice = $testInvoice->fresh(['items', 'payments']);
    echo "✅ Created test invoice: {$testInvoice->order_number}\n\n";
}

echo "📋 TEST INVOICE SELECTED FOR DELETION\n";
echo "───────────────────────────────────────────────────────────────────────\n";
echo "Invoice Number: {$testInvoice->order_number}\n";
echo "Customer: " . ($testInvoice->customer->business_name ?? $testInvoice->customer->name) . "\n";
echo "Status: {$testInvoice->order_status->value}\n";
echo "Total Amount: AED " . number_format($testInvoice->total_amount, 2) . "\n";
echo "Items: {$testInvoice->items->count()}\n";
echo "Payments: {$testInvoice->payments->count()}\n\n";

if ($testInvoice->order_status->value !== 'cancelled' && 
    !str_contains($testInvoice->order_number, 'TEST')) {
    echo "⚠️  WARNING: This doesn't look like a test invoice!\n";
    echo "   Order number: {$testInvoice->order_number}\n";
    echo "   Status: {$testInvoice->order_status->value}\n";
    echo "   For safety, this test will abort.\n\n";
    echo "   To test DELETE:\n";
    echo "   1. Create a test invoice with 'TEST' in the order number, OR\n";
    echo "   2. Modify this script to accept the invoice you want to delete\n\n";
    exit(1);
}

// STEP 1: Record all related data BEFORE deletion
echo "📊 STEP 1: RECORD RELATED DATA\n";
echo "───────────────────────────────────────────────────────────────────────\n";

$invoiceId = $testInvoice->id;
$orderItemIds = $testInvoice->items->pluck('id')->toArray();
$paymentIds = $testInvoice->payments->pluck('id')->toArray();

echo "Invoice ID: {$invoiceId}\n";
echo "Order Items: " . count($orderItemIds) . " (" . implode(', ', $orderItemIds) . ")\n";
echo "Payments: " . count($paymentIds) . " (" . implode(', ', $paymentIds) . ")\n";

// Check for OrderItemQuantity records
$allocationIds = OrderItemQuantity::whereIn('order_item_id', $orderItemIds)
    ->pluck('id')
    ->toArray();

echo "OrderItemQuantity records: " . count($allocationIds);
if (count($allocationIds) > 0) {
    echo " (" . implode(', ', $allocationIds) . ")";
}
echo "\n\n";

if ($testInvoice->order_status->value === 'processing' && count($allocationIds) > 0) {
    echo "⚠️  WARNING: This invoice has allocated inventory!\n";
    echo "   DELETE will NOT return inventory to stock!\n";
    echo "   You should use 'Cancel Order' instead.\n\n";
}

// STEP 2: Verify records exist BEFORE deletion
echo "🔍 STEP 2: VERIFY RECORDS EXIST (BEFORE DELETE)\n";
echo "───────────────────────────────────────────────────────────────────────\n";

$invoiceExists = Order::find($invoiceId) !== null;
$itemsExist = OrderItem::whereIn('id', $orderItemIds)->count();
$paymentsExist = Payment::whereIn('id', $paymentIds)->count();
$allocationsExist = OrderItemQuantity::whereIn('id', $allocationIds)->count();

echo "Invoice exists: " . ($invoiceExists ? "YES" : "NO") . "\n";
echo "Order items exist: {$itemsExist} / " . count($orderItemIds) . "\n";
echo "Payments exist: {$paymentsExist} / " . count($paymentIds) . "\n";
echo "Allocations exist: {$allocationsExist} / " . count($allocationIds) . "\n\n";

// STEP 3: Execute DELETE action
echo "🗑️  STEP 3: EXECUTE DELETE ACTION\n";
echo "───────────────────────────────────────────────────────────────────────\n";
echo "Deleting invoice ID {$invoiceId}...\n";

try {
    // Laravel will cascade delete related records based on model relationships
    $deleted = $testInvoice->delete();
    
    if ($deleted) {
        echo "✅ Invoice deleted successfully\n\n";
    } else {
        echo "❌ Failed to delete invoice\n\n";
        exit(1);
    }
    
} catch (\Exception $e) {
    echo "❌ ERROR: Exception during deletion\n";
    echo "   Error: {$e->getMessage()}\n";
    exit(1);
}

// Give database a moment to cascade delete
sleep(1);

// STEP 4: Verify records were deleted
echo "🔍 STEP 4: VERIFY RECORDS DELETED (AFTER DELETE)\n";
echo "───────────────────────────────────────────────────────────────────────\n";

$invoiceExistsAfter = Order::find($invoiceId) !== null;
$itemsExistAfter = OrderItem::whereIn('id', $orderItemIds)->count();
$paymentsExistAfter = Payment::whereIn('id', $paymentIds)->count();
$allocationsExistAfter = OrderItemQuantity::whereIn('id', $allocationIds)->count();

echo "Invoice exists: " . ($invoiceExistsAfter ? "YES (FAILED)" : "NO (SUCCESS)") . "\n";
echo "Order items exist: {$itemsExistAfter} / " . count($orderItemIds);
echo " (" . ($itemsExistAfter == 0 ? "SUCCESS" : "FAILED") . ")\n";

if (count($paymentIds) > 0) {
    echo "Payments exist: {$paymentsExistAfter} / " . count($paymentIds);
    echo " (" . ($paymentsExistAfter == 0 ? "SUCCESS" : "FAILED") . ")\n";
}

if (count($allocationIds) > 0) {
    echo "Allocations exist: {$allocationsExistAfter} / " . count($allocationIds);
    echo " (" . ($allocationsExistAfter == 0 ? "SUCCESS" : "FAILED") . ")\n";
}

echo "\n";

// STEP 5: Verify data is gone
echo "📝 STEP 5: ATTEMPT TO RETRIEVE DELETED RECORDS\n";
echo "───────────────────────────────────────────────────────────────────────\n";

$retrievedInvoice = Order::find($invoiceId);

if ($retrievedInvoice === null) {
    echo "✅ Invoice cannot be retrieved (successfully deleted)\n";
} else {
    echo "❌ Invoice can still be retrieved! (deletion failed)\n";
}

$retrievedItems = OrderItem::whereIn('id', $orderItemIds)->get();
if ($retrievedItems->count() == 0) {
    echo "✅ Order items cannot be retrieved (successfully deleted)\n";
} else {
    echo "❌ {$retrievedItems->count()} order items can still be retrieved!\n";
}

if (count($paymentIds) > 0) {
    $retrievedPayments = Payment::whereIn('id', $paymentIds)->get();
    if ($retrievedPayments->count() == 0) {
        echo "✅ Payments cannot be retrieved (successfully deleted)\n";
    } else {
        echo "❌ {$retrievedPayments->count()} payments can still be retrieved!\n";
    }
}

if (count($allocationIds) > 0) {
    $retrievedAllocations = OrderItemQuantity::whereIn('id', $allocationIds)->get();
    if ($retrievedAllocations->count() == 0) {
        echo "✅ Allocations cannot be retrieved (successfully deleted)\n";
    } else {
        echo "❌ {$retrievedAllocations->count()} allocations can still be retrieved!\n";
    }
}

echo "\n";

// STEP 6: Final verification summary
echo "═══════════════════════════════════════════════════════════════════════\n";
echo "                    TEST RESULTS SUMMARY\n";
echo "═══════════════════════════════════════════════════════════════════════\n\n";

echo ($invoiceExistsAfter ? "❌" : "✅") . " Invoice Deleted: " . 
     ($invoiceExistsAfter ? "FAIL" : "PASS") . "\n";

echo ($itemsExistAfter == 0 ? "✅" : "❌") . " Order Items Deleted: " . 
     ($itemsExistAfter == 0 ? "PASS" : "FAIL") . "\n";

if (count($paymentIds) > 0) {
    echo ($paymentsExistAfter == 0 ? "✅" : "❌") . " Payments Deleted: " . 
         ($paymentsExistAfter == 0 ? "PASS" : "FAIL") . "\n";
}

if (count($allocationIds) > 0) {
    echo ($allocationsExistAfter == 0 ? "✅" : "❌") . " Allocations Deleted: " . 
         ($allocationsExistAfter == 0 ? "PASS" : "FAIL") . "\n";
}

echo "\n";

// Overall result
$overallPass = !$invoiceExistsAfter && 
               $itemsExistAfter == 0 &&
               (count($paymentIds) == 0 || $paymentsExistAfter == 0) &&
               (count($allocationIds) == 0 || $allocationsExistAfter == 0);

echo "═══════════════════════════════════════════════════════════════════════\n";
echo "OVERALL: " . ($overallPass ? "✅ ALL TESTS PASSED" : "❌ SOME TESTS FAILED") . "\n";
echo "═══════════════════════════════════════════════════════════════════════\n\n";

if ($overallPass) {
    echo "🎉 The 'Delete' action is working correctly!\n";
    echo "   - Invoice completely removed\n";
    echo "   - All related records deleted\n";
    echo "   - Data cannot be recovered\n\n";
    
    echo "⚠️  IMPORTANT REMINDER:\n";
    echo "   - DELETE should ONLY be used for test data\n";
    echo "   - For real orders, use 'Cancel Order' instead\n";
    echo "   - Cancel Order maintains audit trail\n";
    echo "   - Cancel Order handles inventory correctly\n\n";
} else {
    echo "⚠️  Some issues were detected. Review the test output above.\n\n";
}

echo "📌 NOTE: The deleted invoice (ID: {$invoiceId}) is permanently gone.\n";
echo "         This demonstrates why DELETE should only be used for test data!\n";

exit($overallPass ? 0 : 1);
