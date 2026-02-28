<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$customerTypes = DB::table('orders')
    ->join('customers', 'orders.customer_id', '=', 'customers.id')
    ->select('customers.customer_type', DB::raw('count(*) as count'))
    ->where('orders.document_type', 'quote')
    ->whereNull('orders.deleted_at')
    ->groupBy('customers.customer_type')
    ->get();

echo json_encode($customerTypes, JSON_PRETTY_PRINT);
