<?php

use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Enums\DocumentType;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$orders = Order::latest()->take(5)->get();
foreach($orders as $o) {
    echo $o->id . " - " . $o->order_number . " - " . ($o->document_type?->value ?? 'NULL') . "\n";
}

$order = Order::where('order_number', 'INV-2025-0041')->first();

if (!$order) {
    echo "Order INV-2025-0041 not found! Checking by ID from screenshot (if visible)...\n";
    // Try to find any invoice with items
    $order = Order::where('document_type', 'invoice')->has('items')->first();
    if ($order) {
        echo "Found alternative invoice: " . $order->order_number . "\n";
    } else {
        exit;
    }
}

echo "Order ID: " . $order->id . "\n";
echo "Items Count: " . $order->items->count() . "\n";

foreach ($order->items as $item) {
    echo "Item ID: " . $item->id . "\n";
    echo "Product Name: " . $item->product_name . "\n";
    echo "Brand Name: " . $item->brand_name . "\n";
    echo "SKU: " . $item->sku . "\n";
    echo "Quantity: " . $item->quantity . "\n";
    echo "Unit Price: " . $item->unit_price . "\n";
    echo "Line Total: " . $item->line_total . "\n";
    echo "--------------------------------\n";
}

echo "\nJSON Output:\n";
echo json_encode($order->items->toArray(), JSON_PRETTY_PRINT);
