<?php
$app = require_once dirname(__DIR__) . '/bootstrap.php';
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
