<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Modules\Consignments\Models\Consignment;
use App\Modules\Orders\Models\Order;
use App\Modules\Settings\Models\TaxSetting;

echo "\n=== TAX INCLUSIVE & VEHICLE INFO TEST ===\n\n";

// Check tax setting
$taxSetting = TaxSetting::getDefault();

echo "=== TAX SETTINGS ===\n";
if ($taxSetting) {
    echo "Tax Name: {$taxSetting->name}\n";
    echo "Tax Rate: {$taxSetting->rate}%\n";
    echo "Tax Inclusive by Default: " . ($taxSetting->tax_inclusive_default ? '✅ YES' : '❌ NO') . "\n";
    echo "Is Active: " . ($taxSetting->is_active ? '✅ YES' : '❌ NO') . "\n\n";
} else {
    echo "❌ No default tax setting found!\n\n";
}

// Check a consignment with vehicle info
echo "=== CONSIGNMENT WITH VEHICLE INFO ===\n";
$consignmentWithVehicle = Consignment::whereNotNull('year')->first();

if ($consignmentWithVehicle) {
    echo "Consignment: {$consignmentWithVehicle->consignment_number}\n";
    echo "Vehicle Year: {$consignmentWithVehicle->year}\n";
    echo "Vehicle Make: {$consignmentWithVehicle->make}\n";
    echo "Vehicle Model: {$consignmentWithVehicle->model}\n";
    echo "Vehicle Sub Model: {$consignmentWithVehicle->sub_model}\n";
    echo "Using accessor vehicle_year: {$consignmentWithVehicle->vehicle_year}\n";
    echo "Using accessor vehicle_make: {$consignmentWithVehicle->vehicle_make}\n";
    echo "Using accessor vehicle_model: {$consignmentWithVehicle->vehicle_model}\n";
    echo "Using accessor vehicle_sub_model: {$consignmentWithVehicle->vehicle_sub_model}\n\n";
} else {
    echo "No consignment with vehicle info found\n\n";
}

// Check recent invoices for vehicle info
echo "=== RECENT INVOICES (Last 5) ===\n";
$invoices = Order::orderBy('id', 'desc')->take(5)->get();

if ($invoices->isEmpty()) {
    echo "No invoices found\n\n";
} else {
    foreach ($invoices as $invoice) {
        echo "Invoice: {$invoice->invoice_number}\n";
        echo "  Vehicle Year: " . ($invoice->vehicle_year ?? 'NULL') . "\n";
        echo "  Vehicle Make: " . ($invoice->vehicle_make ?? 'NULL') . "\n";
        echo "  Vehicle Model: " . ($invoice->vehicle_model ?? 'NULL') . "\n";
        echo "  Vehicle Sub Model: " . ($invoice->vehicle_sub_model ?? 'NULL') . "\n";
        echo "  Source: " . ($invoice->source ?? 'N/A') . "\n";
        
        // If from consignment, check original consignment
        if ($invoice->source === 'consignment' && $invoice->external_invoice_references) {
            preg_match('/CONSIGNMENT:(.+)/', $invoice->external_invoice_references, $matches);
            if (!empty($matches[1])) {
                $consNumber = $matches[1];
                $cons = Consignment::where('consignment_number', $consNumber)->first();
                if ($cons) {
                    echo "  Source Consignment Vehicle: {$cons->year} {$cons->make} {$cons->model}\n";
                }
            }
        }
        echo "\n";
    }
}

echo "=== TEST VEHICLE INFO PASSING ===\n\n";

// Find the dealer test consignment
$testCons = Consignment::where('consignment_number', 'CON-TEST-DEALER-1762002000')->first();

if ($testCons) {
    echo "Test Consignment: {$testCons->consignment_number}\n";
    echo "Status: {$testCons->status->value}\n";
    echo "Vehicle Info:\n";
    echo "  year: " . ($testCons->year ?? 'NULL') . "\n";
    echo "  make: " . ($testCons->make ?? 'NULL') . "\n";
    echo "  model: " . ($testCons->model ?? 'NULL') . "\n";
    echo "  sub_model: " . ($testCons->sub_model ?? 'NULL') . "\n";
    echo "  vehicle_year accessor: " . ($testCons->vehicle_year ?? 'NULL') . "\n";
    echo "  vehicle_make accessor: " . ($testCons->vehicle_make ?? 'NULL') . "\n";
    echo "  vehicle_model accessor: " . ($testCons->vehicle_model ?? 'NULL') . "\n";
    echo "  vehicle_sub_model accessor: " . ($testCons->vehicle_sub_model ?? 'NULL') . "\n\n";
} else {
    echo "Test consignment not found\n\n";
}

echo "=== ISSUES FOUND ===\n\n";

$issues = [];

// Check if tax_inclusive is being set on items
$sampleConsignment = Consignment::with('items')->first();
if ($sampleConsignment && $sampleConsignment->items->isNotEmpty()) {
    $firstItem = $sampleConsignment->items->first();
    if (!isset($firstItem->tax_inclusive)) {
        $issues[] = "ConsignmentItem doesn't have tax_inclusive field";
    } else {
        echo "✅ ConsignmentItem has tax_inclusive field: " . ($firstItem->tax_inclusive ? 'true' : 'false') . "\n";
    }
}

// Check if tax_inclusive_default is being used
if ($taxSetting && $taxSetting->tax_inclusive_default) {
    $issues[] = "Tax setting has 'Tax Inclusive by Default' enabled, but forms may not be using it";
}

if (!empty($issues)) {
    foreach ($issues as $issue) {
        echo "⚠️  {$issue}\n";
    }
} else {
    echo "✅ No issues found\n";
}

echo "\n=== TEST COMPLETE ===\n\n";
