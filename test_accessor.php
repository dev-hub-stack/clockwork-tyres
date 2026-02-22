<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$addon = App\Models\Addon::find(13);

echo "ORIGINAL image_1 raw: " . $addon->getRawOriginal('image_1') . "\n";
echo "ORIGINAL image_1: " . $addon->image_1 . "\n";
echo "ORIGINAL image_1_url: " . $addon->image_1_url . "\n\n";

// Simulate Accessor
$val = $addon->getRawOriginal('image_1');
if ($val && str_starts_with($val, 'http')) {
    $val = ltrim(parse_url($val, PHP_URL_PATH), '/');
}

echo "SIMULATED image_1 (from Accessor): " . $val . "\n";

// Simulated image_1_url
$cdnUrl = config('filesystems.disks.s3.url', env('AWS_CLOUDFRONT_URL'));
$simUrl = rtrim($cdnUrl, '/') . '/' . ltrim($val, '/');
echo "SIMULATED image_1_url: " . $simUrl . "\n";
