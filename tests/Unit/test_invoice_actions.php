<?php

/**
 * Test Invoice Actions: Cancel Order, Start Processing, Delete
 * 
 * This script validates the three main invoice actions and shows
 * which records exist in the database for testing.
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItemQuantity;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Products\Models\ProductInventory;

echo "═══════════════════════════════════════════════════════════════════════\n";
echo "               INVOICE ACTIONS VALIDATION TEST\n";
echo "═══════════════════════════════════════════════════════════════════════\n\n";

// 1. DATABASE RECORDS SUMMARY
echo "📊 DATABASE RECORDS SUMMARY\n";
echo "───────────────────────────────────────────────────────────────────────\n\n";

$invoices = Order::invoices()->with(['customer', 'items', 'warehouse'])->get();
$totalInvoices = $invoices->count();

echo "Total Invoices: {$totalInvoices}\n\n";

// Group by order status
echo "By Order Status:\n";
$statusGroups = $invoices->groupBy('order_status');
foreach ($statusGroups as $status => $group) {
    $count = $group->count();
    $percentage = $totalInvoices > 0 ? round(($count / $totalInvoices) * 100, 1) : 0;
    echo sprintf("  %-15s: %3d invoices (%5.1f%%)\n", $status, $count, $percentage);
}
echo "\n";

// Group by payment status
echo "By Payment Status:\n";
$paymentGroups = $invoices->groupBy('payment_status');
foreach ($paymentGroups as $status => $group) {
    $count = $group->count();
    $percentage = $totalInvoices > 0 ? round(($count / $totalInvoices) * 100, 1) : 0;
    echo sprintf("  %-15s: %3d invoices (%5.1f%%)\n", $status, $count, $percentage);
}
echo "\n\n";

// 2. ACTION AVAILABILITY TEST
echo "🔍 ACTION AVAILABILITY TEST\n";
echo "───────────────────────────────────────────────────────────────────────\n\n";

$pendingCount = 0;
$processingCount = 0;
$cancelableCount = 0;
$canStartProcessing = 0;

foreach ($invoices as $invoice) {
    if ($invoice->order_status->value === 'pending') {
        $pendingCount++;
        $canStartProcessing++;
        $cancelableCount++;
    } elseif ($invoice->order_status->value === 'processing') {
        $processingCount++;
        $cancelableCount++;
    }
}

echo "✅ START PROCESSING Available:\n";
echo "   - Invoices with 'pending' status: {$canStartProcessing}\n";
echo "   - Action visible: " . ($canStartProcessing > 0 ? "YES" : "NO") . "\n\n";

echo "❌ CANCEL ORDER Available:\n";
echo "   - Invoices with 'pending' or 'processing': {$cancelableCount}\n";
echo "   - Action visible: " . ($cancelableCount > 0 ? "YES" : "NO") . "\n\n";

echo "🗑️  DELETE Available:\n";
echo "   - All invoices can be deleted: {$totalInvoices}\n";
echo "   - Warning: Should only delete test data!\n\n";

// 3. SAMPLE INVOICES FOR TESTING
echo "📋 SAMPLE INVOICES FOR TESTING\n";
echo "───────────────────────────────────────────────────────────────────────\n\n";

$sampleInvoices = Order::invoices()
    ->with(['customer', 'items', 'warehouse', 'payments'])
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->get();

foreach ($sampleInvoices as $invoice) {
    $customerName = $invoice->customer->business_name ?? $invoice->customer->name ?? 'Unknown';
    $itemsCount = $invoice->items->count();
    $totalAmount = number_format($invoice->total_amount, 2);
    
    echo "Invoice: {$invoice->order_number}\n";
    echo "  ID: {$invoice->id}\n";
    echo "  Customer: {$customerName}\n";
    echo "  Order Status: {$invoice->order_status->value}\n";
    echo "  Payment Status: {$invoice->payment_status->value}\n";
    echo "  Items: {$itemsCount} | Total: AED {$totalAmount}\n";
    echo "  Warehouse: " . ($invoice->warehouse->name ?? 'Not Set') . "\n";
    
    // Check which actions are available
    $actions = [];
    if ($invoice->order_status->value === 'pending') {
        $actions[] = "✅ Start Processing";
        $actions[] = "❌ Cancel Order";
    } elseif ($invoice->order_status->value === 'processing') {
        $actions[] = "❌ Cancel Order";
    }
    $actions[] = "🗑️ Delete";
    
    echo "  Available Actions: " . implode(', ', $actions) . "\n";
    echo "\n";
}

// 4. INVENTORY ALLOCATION CHECK
echo "📦 INVENTORY ALLOCATION CHECK\n";
echo "───────────────────────────────────────────────────────────────────────\n\n";

$processingInvoices = Order::invoices()
    ->where('order_status', OrderStatus::PROCESSING)
    ->with(['items'])
    ->get();

if ($processingInvoices->count() > 0) {
    echo "Processing invoices with allocated inventory:\n\n";
    
    foreach ($processingInvoices as $invoice) {
        $hasAllocations = false;
        foreach ($invoice->items as $item) {
            if ($item->allocated_quantity > 0) {
                $hasAllocations = true;
                break;
            }
        }
        
        if ($hasAllocations) {
            echo "Invoice: {$invoice->order_number}\n";
            foreach ($invoice->items as $item) {
                if ($item->allocated_quantity > 0) {
                    echo "  - {$item->product_name}: {$item->allocated_quantity} allocated\n";
                }
            }
            echo "  ⚠️ Canceling this will deallocate inventory!\n\n";
        }
    }
} else {
    echo "No invoices currently in 'processing' status with allocations.\n\n";
}

// 5. ACTION BEHAVIOR SIMULATION
echo "🔬 ACTION BEHAVIOR SIMULATION\n";
echo "───────────────────────────────────────────────────────────────────────\n\n";

echo "1. START PROCESSING:\n";
echo "   - Changes status: pending → processing\n";
echo "   - Allocates inventory from warehouse\n";
echo "   - Creates OrderItemQuantity records\n";
echo "   - Shows stock availability check\n";
echo "   - Tooltip: 'Begin order fulfillment and reserve inventory'\n\n";

echo "2. CANCEL ORDER:\n";
echo "   - Changes status: any → cancelled\n";
echo "   - Deallocates inventory (if processing)\n";
echo "   - Deletes OrderItemQuantity records\n";
echo "   - Requires cancellation reason\n";
echo "   - Keeps record for audit trail\n";
echo "   - Tooltip: 'Cancel order and return inventory to stock'\n\n";

echo "3. DELETE:\n";
echo "   - Permanently removes record\n";
echo "   - Deletes all related data\n";
echo "   - Cannot be undone\n";
echo "   - No automatic inventory handling\n";
echo "   - Tooltip: 'Permanently delete this record (use with caution!)'\n\n";

// 6. RECOMMENDATIONS
echo "💡 RECOMMENDATIONS\n";
echo "───────────────────────────────────────────────────────────────────────\n\n";

if ($canStartProcessing > 0) {
    echo "✅ You can test START PROCESSING with {$canStartProcessing} pending invoice(s)\n";
}

if ($processingCount > 0) {
    echo "✅ You can test CANCEL ORDER on {$processingCount} processing invoice(s)\n";
    echo "   (This will test inventory deallocation)\n";
}

if ($pendingCount > 0) {
    echo "✅ You can test CANCEL ORDER on {$pendingCount} pending invoice(s)\n";
    echo "   (No inventory to deallocate)\n";
}

if ($totalInvoices > 10) {
    echo "⚠️  You have {$totalInvoices} invoices - be careful with DELETE!\n";
}

echo "\n";
echo "═══════════════════════════════════════════════════════════════════════\n";
echo "                        TEST COMPLETE\n";
echo "═══════════════════════════════════════════════════════════════════════\n";
