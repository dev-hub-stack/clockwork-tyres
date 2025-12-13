<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Checking orders table for notes columns...\n\n";

$columns = DB::select('DESCRIBE orders');

foreach($columns as $col) {
    if (strpos($col->Field, 'note') !== false) {
        echo "  {$col->Field} ({$col->Type}) - Null: {$col->Null}\n";
    }
}

echo "\n";
