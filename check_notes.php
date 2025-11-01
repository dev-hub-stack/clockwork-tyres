<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$invoice = \App\Modules\Orders\Models\Order::find(28);

echo "Invoice ID: {$invoice->id}\n";
echo "Order Number: {$invoice->order_number}\n";
echo "Status: {$invoice->order_status->value}\n";
echo "\nOrder Notes content:\n";
echo "Length: " . strlen($invoice->order_notes ?? '') . " characters\n";
echo "Value: " . var_export($invoice->order_notes, true) . "\n";
echo "\nSearching for 'Cancellation Reason': ";
echo (strpos($invoice->order_notes ?? '', 'Cancellation Reason') !== false ? "FOUND" : "NOT FOUND") . "\n";
echo "\nSearching for 'TEST': ";
echo (strpos($invoice->order_notes ?? '', 'TEST') !== false ? "FOUND" : "NOT FOUND") . "\n";
