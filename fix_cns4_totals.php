<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Recompute totals on CNS-2026-0004 using the corrected formula
$c = \App\Modules\Consignments\Models\Consignment::withTrashed()
    ->where('consignment_number', 'CNS-2026-0004')->first();

echo "BEFORE: subtotal={$c->subtotal} tax={$c->tax} total={$c->total} discount={$c->discount} shipping={$c->shipping_cost}\n";

$c->calculateTotals();
$c->refresh();

echo "AFTER:  subtotal={$c->subtotal} tax={$c->tax} total={$c->total}\n";
