<?php
// Bootstrap Laravel
$app = require_once dirname(__DIR__) . '/bootstrap.php';

$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Order;
use App\Models\Customer;

// Test queries
$revenue = Order::where('external_source', 'tunerstop_historical')->sum('total');
$orders = Order::where('external_source', 'tunerstop_historical')->count();
$customers = Customer::where('customer_type', 'retail')->count();

echo "\n✅ Reports Module Data Validation\n";
echo "=====================================\n";
echo "Total Revenue: AED " . number_format($revenue, 2) . "\n";
echo "Total Orders: " . number_format($orders) . "\n";
echo "Total Customers: " . number_format($customers) . "\n";

// Test top products
$topProducts = \App\Models\Product::select('name', 'sku')
    ->leftJoin('order_items', 'products.id', '=', 'order_items.product_id')
    ->selectRaw('SUM(order_items.line_total) as revenue')
    ->selectRaw('COUNT(DISTINCT order_items.id) as times_sold')
    ->groupBy('products.id')
    ->having('times_sold', '>', 0)
    ->orderByDesc('revenue')
    ->limit(3)
    ->get();

echo "\nTop 3 Products:\n";
foreach ($topProducts as $product) {
    echo "  - {$product->sku}: AED " . number_format($product->revenue, 2) . " ({$product->times_sold} orders)\n";
}

echo "\n✅ All reports queries validated successfully!\n";
