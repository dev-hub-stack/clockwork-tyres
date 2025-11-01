<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Modules\Consignments\Models\Consignment;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\Payment;

echo "=== TESTING PAYMENT RECORDING FIX ===\n\n";

// Find recent invoices (not quotes)
echo "Looking for recent invoices...\n";
$invoices = Order::where('document_type', 'invoice')
    ->with(['payments', 'customer'])
    ->latest()
    ->take(5)
    ->get();

if ($invoices->isEmpty()) {
    echo "❌ No invoices found yet.\n";
    echo "Create an invoice or consignment sale first, then run this test.\n";
    exit;
}

echo "✅ Found " . $invoices->count() . " consignment invoice(s)\n\n";

foreach ($invoices as $invoice) {
    echo "=== Invoice: {$invoice->invoice_number} ===\n";
    echo "Customer: " . ($invoice->customer->business_name ?? $invoice->customer->full_name) . "\n";
    echo "Total: AED " . number_format($invoice->total, 2) . "\n";
    echo "Order Status: {$invoice->order_status->value}\n";
    echo "Payment Status: {$invoice->payment_status->value}\n";
    echo "Amount Paid: AED " . number_format($invoice->amount_paid ?? 0, 2) . "\n";
    echo "Balance Due: AED " . number_format($invoice->balance_due ?? 0, 2) . "\n";
    
    // Check if there are payment records
    $paymentCount = $invoice->payments->count();
    echo "\nPayment Records: {$paymentCount}\n";
    
    if ($paymentCount > 0) {
        echo "✅ PAYMENTS FOUND:\n";
        foreach ($invoice->payments as $payment) {
            echo "  - Payment #{$payment->payment_number}\n";
            echo "    Amount: AED " . number_format($payment->amount, 2) . "\n";
            echo "    Method: {$payment->payment_method}\n";
            echo "    Date: {$payment->payment_date->format('Y-m-d')}\n";
            echo "    Status: {$payment->status}\n";
        }
    } else {
        echo "⚠️  NO PAYMENT RECORDS - This indicates the old bug\n";
        echo "   (Payment info was stored directly in order, not as Payment record)\n";
    }
    
    // Check payment status logic
    if ($invoice->payment_status === 'paid' && $paymentCount > 0) {
        echo "\n✅ Status: CORRECT - Payment status is 'paid' with Payment record\n";
    } elseif ($invoice->payment_status === 'pending' && $paymentCount === 0) {
        echo "\n⚠️  Status: OLD BUG - Payment status is 'pending' but no Payment records\n";
    } elseif ($invoice->payment_status === 'pending' && $paymentCount > 0) {
        echo "\n❌ Status: INCONSISTENT - Has Payment records but status is 'pending'\n";
    }
    
    echo "\n" . str_repeat('-', 60) . "\n\n";
}

echo "=== SUMMARY ===\n";
echo "Total invoices checked: " . $invoices->count() . "\n";
$withPayments = $invoices->filter(fn($inv) => $inv->payments->count() > 0)->count();
$withoutPayments = $invoices->count() - $withPayments;

echo "With Payment records: {$withPayments} ✅\n";
echo "Without Payment records: {$withoutPayments} " . ($withoutPayments > 0 ? "⚠️" : "✅") . "\n\n";

if ($withoutPayments > 0) {
    echo "⚠️  Some invoices don't have Payment records.\n";
    echo "   These were created before the fix.\n";
    echo "   New invoices should have Payment records.\n";
} else {
    echo "✅ All invoices have proper Payment records!\n";
}

echo "\n=== TEST COMPLETE ===\n";
