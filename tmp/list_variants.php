<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Modules\Products\Models\ProductVariant;

$variants = ProductVariant::with('product')->take(5)->get();

foreach ($variants as $v) {
    echo "SKU: " . $v->sku . " | Finish: " . ($v->finish ?? 'NULL') . "\n";
    if ($v->product) {
        echo "  Product: " . $v->product->name . " | Full Name: " . ($v->product->product_full_name ?? 'NULL') . "\n";
    }
    echo "------------------\n";
}
