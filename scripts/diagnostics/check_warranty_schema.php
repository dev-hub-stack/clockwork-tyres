<?php


$app = require_once dirname(__DIR__) . '/bootstrap.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Columns in warranty_claims table:\n";
echo str_repeat("=", 80) . "\n";

$columns = DB::select('SHOW COLUMNS FROM warranty_claims');

foreach ($columns as $col) {
    echo sprintf(
        "%-30s | %-20s | Null: %-3s | Default: %s\n",
        $col->Field,
        $col->Type,
        $col->Null,
        $col->Default ?? 'NULL'
    );
}

echo str_repeat("=", 80) . "\n";
