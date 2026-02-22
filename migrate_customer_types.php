<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$dealer    = DB::table('customers')->where('customer_type', 'dealer')->count();
$corporate = DB::table('customers')->where('customer_type', 'corporate')->count();

echo "Customers to migrate:\n";
echo "  dealer    → wholesale: {$dealer}\n";
echo "  corporate → wholesale: {$corporate}\n\n";

DB::table('customers')->where('customer_type', 'dealer')->update(['customer_type' => 'wholesale']);
DB::table('customers')->where('customer_type', 'corporate')->update(['customer_type' => 'wholesale']);

echo "Done. All dealer/corporate customers are now wholesale.\n";
