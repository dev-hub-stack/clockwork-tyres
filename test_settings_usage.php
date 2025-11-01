<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Modules\Settings\Models\TaxSetting;
use App\Modules\Settings\Models\CurrencySetting;
use App\Modules\Settings\Models\CompanyBranding;

echo "=== SYSTEM SETTINGS USAGE VERIFICATION ===\n\n";

// Test Tax Setting
echo "1. Testing TaxSetting::getDefault()...\n";
$taxSetting = TaxSetting::getDefault();
if ($taxSetting) {
    echo "   ✅ Found: {$taxSetting->name} ({$taxSetting->rate}%)\n";
    echo "   ✅ Tax Inclusive Default: " . ($taxSetting->tax_inclusive_default ? 'YES' : 'NO') . "\n";
} else {
    echo "   ❌ No tax setting found!\n";
}

// Test Currency Setting
echo "\n2. Testing CurrencySetting::getBase()...\n";
$currency = CurrencySetting::getBase();
if ($currency) {
    echo "   ✅ Found: {$currency->currency_code} ({$currency->currency_symbol})\n";
    echo "   ✅ Is Base Currency: " . ($currency->is_base_currency ? 'YES' : 'NO') . "\n";
    echo "   ✅ Is Active: " . ($currency->is_active ? 'YES' : 'NO') . "\n";
} else {
    echo "   ❌ No base currency found!\n";
}

// Test Company Branding
echo "\n3. Testing CompanyBranding::getActive()...\n";
$branding = CompanyBranding::getActive();
if ($branding) {
    echo "   ✅ Found: {$branding->company_name}\n";
    echo "   ✅ Is Active: " . ($branding->is_active ? 'YES' : 'NO') . "\n";
    echo "   ✅ Tax Registration: " . ($branding->tax_registration_number ?? 'Not Set') . "\n";
} else {
    echo "   ❌ No active company branding found!\n";
}

// Test files that should be using these settings
echo "\n=== CHECKING FILE CONTENTS FOR HARDCODED VALUES ===\n\n";

$filesToCheck = [
    'app/Filament/Resources/QuoteResource.php' => 'QuoteResource',
    'app/Filament/Resources/InvoiceResource.php' => 'InvoiceResource',
    'app/Modules/Consignments/Services/ConsignmentInvoiceService.php' => 'ConsignmentInvoiceService',
];

$hardcodedPatterns = [
    "'AED'" => "Hardcoded 'AED' currency",
    '"AED"' => 'Hardcoded "AED" currency',
    "->prefix('AED')" => "Hardcoded prefix('AED')",
    "->money('AED')" => "Hardcoded money('AED')",
];

$issuesFound = 0;

foreach ($filesToCheck as $file => $name) {
    echo "Checking {$name}...\n";
    $fullPath = __DIR__ . '/' . $file;
    
    if (!file_exists($fullPath)) {
        echo "   ⚠️  File not found: {$file}\n";
        continue;
    }
    
    $content = file_get_contents($fullPath);
    $hasIssues = false;
    
    foreach ($hardcodedPatterns as $pattern => $description) {
        // Count occurrences
        $count = substr_count($content, $pattern);
        if ($count > 0) {
            echo "   ❌ Found {$count} instance(s) of {$description}\n";
            $hasIssues = true;
            $issuesFound += $count;
        }
    }
    
    // Check for CurrencySetting usage
    if (strpos($content, 'CurrencySetting') !== false) {
        echo "   ✅ Uses CurrencySetting\n";
    } else {
        echo "   ⚠️  Does NOT use CurrencySetting\n";
        $hasIssues = true;
    }
    
    // Check for TaxSetting usage
    if (strpos($content, 'TaxSetting') !== false) {
        echo "   ✅ Uses TaxSetting\n";
    } else {
        echo "   ⚠️  Does NOT use TaxSetting\n";
        $hasIssues = true;
    }
    
    if (!$hasIssues) {
        echo "   ✅ All good!\n";
    }
    echo "\n";
}

// Summary
echo "=== SUMMARY ===\n";
if ($issuesFound > 0) {
    echo "❌ Found {$issuesFound} hardcoded currency instances.\n";
    echo "⚠️  Some files may still need updates.\n";
} else {
    echo "✅ All files are using system settings correctly!\n";
    echo "✅ No hardcoded currency values found.\n";
}

echo "\n=== TEST COMPLETE ===\n";
