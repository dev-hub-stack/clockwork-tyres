<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$addon = App\Models\Addon::find(13);
$state = $addon->image_1;

echo "RAW DB State: " . $state . "\n";

// Apply formatStateUsing logic
if ($state && str_starts_with($state, 'http')) {
    $path = parse_url($state, PHP_URL_PATH);
    $state = ltrim($path, '/');
}

echo "Formatted State: " . $state . "\n";

// Check if Storage exists
try {
    $disk = \Illuminate\Support\Facades\Storage::disk('s3');
    $exists = $disk->exists($state);
    echo "Disk s3 -> exists: " . ($exists ? "TRUE" : "FALSE") . "\n";
    if ($exists) {
        echo "Disk URL: " . $disk->url($state) . "\n";
    }
} catch (\Exception $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
}
