<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

$tables = ['brands','finishes','models','products','product_variants'];
foreach ($tables as $t) {
    $cols = DB::select('DESCRIBE `'.$t.'`');
    echo "=== $t ===\n";
    foreach ($cols as $c) {
        echo "  {$c->Field} ({$c->Type})\n";
    }
    echo "\n";
}
