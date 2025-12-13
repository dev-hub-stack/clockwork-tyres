<?php

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Testing Addon Sync Service...\n\n";

try {
    $service = app(\App\Services\AddonSyncService::class);
    
    $result = $service->syncAddon([
        'external_addon_id' => 999,
        'external_source' => 'tunerstop',
        'title' => 'Test Addon',
        'part_number' => 'TEST-999',
        'price' => 100,
        'stock_status' => 'in_stock',
        'category' => [
            'external_id' => 5,
            'name' => 'Spacers',
            'slug' => 'spacers',
            'display_name' => 'Wheel Spacers',
            'external_source' => 'tunerstop',
        ]
    ]);
    
    echo "✅ Success! Addon ID: " . $result->id . "\n";
    echo "   Title: " . $result->title . "\n";
    echo "   Part Number: " . $result->part_number . "\n";
    echo "   Category ID: " . $result->addon_category_id . "\n";
    
    // Clean up test data
    $result->delete();
    echo "\n✅ Test addon deleted.\n";
    
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
}
