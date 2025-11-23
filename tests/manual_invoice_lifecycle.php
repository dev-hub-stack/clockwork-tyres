<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;
use App\Modules\Orders\Models\Payment;
use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Enums\DocumentType;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;

echo "Starting Invoice Lifecycle Test...\n";

// 1. Create a Test Customer
$customer = Customer::firstOrCreate(
    ['email' => 'test.lifecycle@example.com'],
    ['name' => 'Lifecycle Test Customer', 'phone' => '555-0199']
);
echo "Customer ID: {$customer->id}\n";

// 2. Create an Invoice (Order)
$order = Order::create([
    'document_type' => DocumentType::INVOICE,
    'order_number' => 'INV-TEST-' . time(),
    'customer_id' => $customer->id,
    'order_status' => OrderStatus::PENDING,
    'payment_status' => PaymentStatus::PENDING,
    'currency' => 'AED',
    'issue_date' => now(),
    'due_date' => now()->addDays(30),
]);
echo "Invoice Created: {$order->order_number} (ID: {$order->id})\n";

// 3. Add Items
$item1 = OrderItem::create([
    'order_id' => $order->id,
    'product_name' => 'Test Product A',
    'sku' => 'TP-A',
    'quantity' => 2,
    'unit_price' => 100.00,
    'line_total' => 200.00, // Manual set for now, will recalc
]);

$item2 = OrderItem::create([
    'order_id' => $order->id,
    'product_name' => 'Test Product B',
    'sku' => 'TP-B',
    'quantity' => 1,
    'unit_price' => 50.00,
    'line_total' => 50.00,
]);

echo "Items Added.\n";

// 4. Calculate Totals
$order->calculateTotals();
$order->refresh();

echo "Totals Calculated:\n";
echo "Subtotal: {$order->sub_total}\n";
echo "VAT: {$order->vat}\n";
echo "Total: {$order->total}\n";

if ($order->total <= 0) {
    echo "ERROR: Total is zero or negative!\n";
    exit(1);
}

// 5. Record Partial Payment
$paymentAmount = 100.00;
echo "Recording Partial Payment of {$paymentAmount}...\n";

$payment1 = Payment::create([
    'order_id' => $order->id,
    'customer_id' => $customer->id,
    'amount' => $paymentAmount,
    'payment_type' => 'partial',
    'payment_date' => now(),
    'payment_method' => 'bank_transfer',
    'status' => 'completed',
    'payment_number' => 'PAY-TEST-1-' . time(),
]);

$order->refresh();
echo "Payment Recorded.\n";
echo "Paid Amount: {$order->paid_amount}\n";
echo "Outstanding Amount: {$order->outstanding_amount}\n";
echo "Payment Status: {$order->payment_status->value}\n";

if ($order->payment_status !== PaymentStatus::PARTIAL) {
    echo "ERROR: Payment status should be PARTIAL!\n";
}

if ($order->outstanding_amount != ($order->total - $paymentAmount)) {
    echo "ERROR: Outstanding amount calculation is wrong!\n";
}

// 6. Record Remaining Payment
$remainingAmount = $order->outstanding_amount;
echo "Recording Remaining Payment of {$remainingAmount}...\n";

$payment2 = Payment::create([
    'order_id' => $order->id,
    'customer_id' => $customer->id,
    'amount' => $remainingAmount,
    'payment_type' => 'full',
    'payment_date' => now(),
    'payment_method' => 'cash',
    'status' => 'completed',
    'payment_number' => 'PAY-TEST-2-' . time(),
]);

$order->refresh();
echo "Final Payment Recorded.\n";
echo "Paid Amount: {$order->paid_amount}\n";
echo "Outstanding Amount: {$order->outstanding_amount}\n";
echo "Payment Status: {$order->payment_status->value}\n";

if ($order->payment_status !== PaymentStatus::PAID) {
    echo "ERROR: Payment status should be PAID!\n";
}

echo "--------------------------------------------------\n";
echo "Starting Cancellation Test...\n";

// 7. Test Cancellation
$cancelOrder = Order::create([
    'document_type' => DocumentType::INVOICE,
    'order_number' => 'INV-CANCEL-' . time(),
    'customer_id' => $customer->id,
    'order_status' => OrderStatus::PENDING,
    'payment_status' => PaymentStatus::PENDING,
    'currency' => 'AED',
    'issue_date' => now(),
    'due_date' => now()->addDays(30),
]);
echo "Cancellation Order Created: {$cancelOrder->order_number}\n";

// Add item to cancel order
OrderItem::create([
    'order_id' => $cancelOrder->id,
    'product_name' => 'Cancel Item',
    'sku' => 'CANCEL-1',
    'quantity' => 5,
    'unit_price' => 10.00,
    'line_total' => 50.00,
]);

$cancelOrder->calculateTotals();
echo "Order Total: {$cancelOrder->total}\n";

echo "Cancelling Order...\n";
$cancelOrder->update([
    'order_status' => OrderStatus::CANCELLED,
    'order_notes' => "Cancelled by test script",
]);

$cancelOrder->refresh();
echo "Order Status: {$cancelOrder->order_status->value}\n";

if ($cancelOrder->order_status !== OrderStatus::CANCELLED) {
    echo "ERROR: Order status should be CANCELLED!\n";
}

echo "Test Completed Successfully!\n";
