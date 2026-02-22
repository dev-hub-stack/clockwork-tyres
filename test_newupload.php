<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Simulate a newly uploaded image (stored as relative path)
$raw = 'addons/image2.jpg';

echo "RAW DB (new upload): $raw\n";

// Test URL accessor logic
if (str_starts_with($raw, 'http')) {
    echo "image_1_url: $raw (returned as-is)\n";
} else {
    $cdnUrl = config('filesystems.disks.s3.url');
    echo "config filesystems.disks.s3.url: " . ($cdnUrl ?: '(null)') . "\n";
    if ($cdnUrl) {
        echo "image_1_url: " . rtrim($cdnUrl, '/') . '/' . ltrim($raw, '/') . "\n";
    } else {
        echo "image_1_url: (null) ← BROKEN\n";
    }
}
