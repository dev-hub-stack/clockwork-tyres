<?php


$app = require_once dirname(__DIR__) . '/bootstrap.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== ACTUAL DATABASE STATE ===\n\n";

// Check each module
$checks = [
    'Orders' => '\App\Modules\Orders\Models\Order',
    'Customers' => '\App\Modules\Customers\Models\Customer',
    'Products' => '\App\Modules\Products\Models\Product',
    'Warehouses' => '\App\Modules\Inventory\Models\Warehouse',
    'Consignments' => '\App\Modules\Consignments\Models\Consignment',
    'Brands' => '\App\Modules\Products\Models\Brand',
    'AddOns' => '\App\Models\Addon',
];

foreach ($checks as $name => $class) {
    try {
        $count = $class::count();
        echo "✅ {$name}: {$count} records\n";
    } catch (\Exception $e) {
        echo "❌ {$name}: Error - " . $e->getMessage() . "\n";
    }
}

echo "\n=== FILAMENT RESOURCES ===\n\n";

$resourcesPath = __DIR__ . '/app/Filament/Resources';
$resources = glob($resourcesPath . '/*Resource.php');

foreach ($resources as $resource) {
    $basename = basename($resource, '.php');
    echo "✅ {$basename}\n";
}

echo "\n=== ORDER/INVOICE STATUS ===\n\n";

try {
    $orderStatuses = \App\Modules\Orders\Models\Order::select('status', \DB::raw('count(*) as count'))
        ->groupBy('status')
        ->get();
    
    foreach ($orderStatuses as $status) {
        echo "  {$status->status}: {$status->count}\n";
    }
} catch (\Exception $e) {
    echo "Error checking order statuses: " . $e->getMessage() . "\n";
}

echo "\n=== WAREHOUSE STATUS ===\n\n";

try {
    $warehouses = \App\Modules\Inventory\Models\Warehouse::all();
    if ($warehouses->count() > 0) {
        foreach ($warehouses as $wh) {
            echo "  {$wh->name}: {$wh->city}, {$wh->state}\n";
        }
    } else {
        echo "  No warehouses found\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== CONSIGNMENTS STATUS ===\n\n";

try {
    $consignments = \App\Modules\Consignments\Models\Consignment::select('status', \DB::raw('count(*) as count'))
        ->groupBy('status')
        ->get();
    
    if ($consignments->count() > 0) {
        foreach ($consignments as $status) {
            echo "  {$status->status}: {$status->count}\n";
        }
    } else {
        echo "  No consignments found\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
