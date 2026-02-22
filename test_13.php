<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$addon = App\Models\Addon::find(13);
echo "VAL 13: " . ($addon ? $addon->image_1 : 'not found') . "\n";
