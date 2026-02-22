<?php

// Run: sudo php artisan tinker --execute="require base_path('check_consignment_number.php');"
// Or:  sudo php check_consignment_number.php (from /var/www/reporting)

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Consignment Number Diagnostics ===\n\n";

// 1. Show all existing consignments
$consignments = \App\Modules\Consignments\Models\Consignment::orderBy('id')->get(['id', 'consignment_number', 'created_at']);
echo "Existing consignments (" . $consignments->count() . "):\n";
foreach ($consignments as $c) {
    echo "  ID={$c->id}  number={$c->consignment_number}  created={$c->created_at}\n";
}

echo "\n";

// 2. Find the MAX number
$prefix = 'CNS';
$year = date('Y');
$maxNum = \App\Modules\Consignments\Models\Consignment::where('consignment_number', 'like', $prefix . '-' . $year . '-%')
    ->selectRaw('MAX(CAST(SUBSTRING_INDEX(consignment_number, \'-\', -1) AS UNSIGNED)) as max_num')
    ->value('max_num');

echo "MAX sequential number found: " . ($maxNum ?? 'none') . "\n";
echo "Next number would be: " . ($prefix . '-' . $year . '-' . str_pad((string)(($maxNum ?? 0) + 1), 4, '0', STR_PAD_LEFT)) . "\n\n";

// 3. Call generateConsignmentNumber() directly
$generated = \App\Modules\Consignments\Models\Consignment::generateConsignmentNumber();
echo "generateConsignmentNumber() returns: {$generated}\n\n";

// 4. Check if CNS-2026-0002 already exists
$exists = \App\Modules\Consignments\Models\Consignment::where('consignment_number', 'CNS-2026-0002')->exists();
echo "CNS-2026-0002 exists in DB: " . ($exists ? 'YES' : 'NO') . "\n";

echo "\n=== Done ===\n";
