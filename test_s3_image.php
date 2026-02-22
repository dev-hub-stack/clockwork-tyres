<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$addon = App\Models\Addon::whereNotNull('image_1')->first();
if ($addon) {
    echo "Image 1 path: " . $addon->image_1 . "\n";
    try {
        $exists = \Illuminate\Support\Facades\Storage::disk('s3')->exists($addon->image_1);
        echo "Exists on S3 disk: " . ($exists ? 'Yes' : 'No') . "\n";
        $url = \Illuminate\Support\Facades\Storage::disk('s3')->url($addon->image_1);
        echo "URL: " . $url . "\n";
    } catch (\Exception $e) {
        echo "Exception: " . $e->getMessage() . "\n";
    }
} else {
    echo "No addon with image_1 found.\n";
}
