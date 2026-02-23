<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Modules\Consignments\Models\Consignment;

echo "Debugging CNS-2026-0013...\n\n";

$consignment = Consignment::where('consignment_number', 'CNS-2026-0013')->first();

if (!$consignment) {
    echo "Consignment not found!\n";
    exit;
}

echo "Consignment ID: {$consignment->id}\n";
echo "Total: {$consignment->total}\n";
echo "Total Value: {$consignment->total_value}\n";
echo "Subtotal: {$consignment->subtotal}\n";
echo "Tax: {$consignment->tax}\n";
echo "Discount: {$consignment->discount}\n";
echo "Shipping Cost: {$consignment->shipping_cost}\n\n";

echo "Items count: {$consignment->items->count()}\n\n";

foreach ($consignment->items as $item) {
    echo "Item ID: {$item->id}\n";
    echo "  Product: {$item->product_name}\n";
    echo "  Quantity Sent: {$item->quantity_sent}\n";
    echo "  Price: {$item->price}\n";
    echo "  Tax Inclusive: " . ($item->tax_inclusive ? 'Yes' : 'No') . "\n\n";
}

echo "\nRecalculating totals...\n";
$consignment->calculateTotals();
$consignment->refresh();

echo "After recalculation:\n";
echo "Total: {$consignment->total}\n";
echo "Total Value: {$consignment->total_value}\n";
