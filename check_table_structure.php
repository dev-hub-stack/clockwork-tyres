<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Checking order_item_quantities table structure...\n\n";

$columns = DB::select('DESCRIBE order_item_quantities');

foreach($columns as $col) {
    echo "  {$col->Field} ({$col->Type})\n";
}

echo "\n";
