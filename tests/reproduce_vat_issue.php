<?php

use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;
use App\Modules\Settings\Models\TaxSetting;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Checking Default Tax Setting...\n";
$taxSetting = TaxSetting::getDefault();
if ($taxSetting) {
    echo "Default Tax Setting Found: ID {$taxSetting->id}, Rate: {$taxSetting->rate}%\n";
} else {
    echo "No Default Tax Setting Found!\n";
}

echo "\nCreating Test Order...\n";
$order = Order::create([
    'document_type' => 'invoice',
    'order_number' => 'VAT-TEST-' . time(),
    'customer_id' => 1, // Assuming customer 1 exists
    'order_status' => 'pending',
    'currency' => 'AED',
    'issue_date' => now(),
    'due_date' => now()->addDays(30),
]);

echo "Order Created: {$order->order_number}\n";

echo "Adding Item...\n";
OrderItem::create([
    'order_id' => $order->id,
    'product_name' => 'Test Product',
    'sku' => 'TEST-SKU',
    'quantity' => 1,
    'unit_price' => 100.00,
    'line_total' => 100.00,
]);

echo "Calculating Totals...\n";
$order->calculateTotals();

echo "Subtotal: {$order->sub_total}\n";
echo "VAT: {$order->vat}\n";
echo "Total: {$order->total}\n";

if ($order->vat == 0) {
    echo "\nERROR: VAT is 0.00!\n";
} else {
    echo "\nSUCCESS: VAT Calculated.\n";
}
