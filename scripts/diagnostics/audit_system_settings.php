<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Modules\Settings\Models\TaxSetting;
use App\Modules\Settings\Models\CurrencySetting;
use App\Modules\Settings\Models\CompanyBranding;

echo "\n=== SYSTEM SETTINGS AUDIT ===\n\n";

// 1. Tax Settings
echo "=== TAX SETTINGS ===\n";
$taxSetting = TaxSetting::getDefault();
if ($taxSetting) {
    echo "Name: {$taxSetting->name}\n";
    echo "Rate: {$taxSetting->rate}%\n";
    echo "Tax Inclusive by Default: " . ($taxSetting->tax_inclusive_default ? 'YES ✅' : 'NO') . "\n";
    echo "Is Active: " . ($taxSetting->is_active ? 'YES ✅' : 'NO') . "\n";
    echo "Description: " . ($taxSetting->description ?? 'N/A') . "\n\n";
} else {
    echo "❌ No default tax setting found!\n\n";
}

// 2. Currency Settings
echo "=== CURRENCY SETTINGS ===\n";
$currencySetting = CurrencySetting::getBase();
if ($currencySetting) {
    echo "Currency Code: {$currencySetting->currency_code}\n";
    echo "Currency Symbol: {$currencySetting->currency_symbol}\n";
    echo "Currency Name: {$currencySetting->currency_name}\n";
    echo "Is Base: " . ($currencySetting->is_base ? 'YES ✅' : 'NO') . "\n\n";
} else {
    echo "❌ No base currency setting found!\n\n";
}

// 3. Company Branding
echo "=== COMPANY BRANDING ===\n";
$companyBranding = CompanyBranding::getActive();
if ($companyBranding) {
    echo "Company Name: {$companyBranding->company_name}\n";
    echo "Company Address: " . ($companyBranding->company_address ?? 'N/A') . "\n";
    echo "Company Phone: " . ($companyBranding->company_phone ?? 'N/A') . "\n";
    echo "Company Email: " . ($companyBranding->company_email ?? 'N/A') . "\n";
    echo "Tax Registration Number: " . ($companyBranding->tax_registration_number ?? 'N/A') . "\n";
    echo "Logo: " . ($companyBranding->logo ? 'YES ✅' : 'NO') . "\n";
    echo "Is Active: " . ($companyBranding->is_active ? 'YES ✅' : 'NO') . "\n\n";
} else {
    echo "❌ No active company branding found!\n\n";
}

echo "=== CHECKING USAGE ACROSS MODULES ===\n\n";

// Check which files use TaxSetting
$filesToCheck = [
    'ConsignmentForm.php' => 'app/Filament/Resources/ConsignmentResource/Schemas/ConsignmentForm.php',
    'QuoteResource.php' => 'app/Filament/Resources/QuoteResource.php',
    'InvoiceResource.php' => 'app/Filament/Resources/InvoiceResource.php',
    'ConsignmentInvoiceService.php' => 'app/Modules/Consignments/Services/ConsignmentInvoiceService.php',
    'ConsignmentsTable.php' => 'app/Filament/Resources/ConsignmentResource/Tables/ConsignmentsTable.php',
];

echo "Tax Setting Usage:\n";
foreach ($filesToCheck as $name => $path) {
    $fullPath = __DIR__ . '/' . $path;
    if (file_exists($fullPath)) {
        $content = file_get_contents($fullPath);
        $usesTaxSetting = strpos($content, 'TaxSetting::getDefault()') !== false;
        $usesTaxInclusive = strpos($content, 'tax_inclusive') !== false;
        
        echo sprintf("  %-35s TaxSetting: %s | tax_inclusive: %s\n", 
            $name, 
            $usesTaxSetting ? '✅' : '❌',
            $usesTaxInclusive ? '✅' : '❌'
        );
    } else {
        echo "  {$name}: File not found\n";
    }
}

echo "\nCurrency Setting Usage:\n";
foreach ($filesToCheck as $name => $path) {
    $fullPath = __DIR__ . '/' . $path;
    if (file_exists($fullPath)) {
        $content = file_get_contents($fullPath);
        $usesCurrency = strpos($content, 'CurrencySetting::getBase()') !== false;
        
        echo sprintf("  %-35s Currency: %s\n", 
            $name, 
            $usesCurrency ? '✅' : '❌'
        );
    }
}

echo "\nCompany Branding Usage:\n";
foreach ($filesToCheck as $name => $path) {
    $fullPath = __DIR__ . '/' . $path;
    if (file_exists($fullPath)) {
        $content = file_get_contents($fullPath);
        $usesBranding = strpos($content, 'CompanyBranding::getActive()') !== false;
        
        echo sprintf("  %-35s Company Branding: %s\n", 
            $name, 
            $usesBranding ? '✅' : '❌'
        );
    }
}

echo "\n=== MISSING IMPLEMENTATIONS ===\n\n";

$issues = [];

// Check if all forms use tax_inclusive
if ($taxSetting && $taxSetting->tax_inclusive_default) {
    // These should all have tax_inclusive
    $formsToCheck = [
        'ConsignmentForm.php',
        'QuoteResource.php',
        'InvoiceResource.php',
    ];
    
    foreach ($formsToCheck as $form) {
        $path = null;
        foreach ($filesToCheck as $fname => $fpath) {
            if ($fname === $form) {
                $path = __DIR__ . '/' . $fpath;
                break;
            }
        }
        
        if ($path && file_exists($path)) {
            $content = file_get_contents($path);
            if (strpos($content, "tax_inclusive_default") === false && 
                strpos($content, "tax_inclusive") === false) {
                $issues[] = "{$form}: Does NOT use tax_inclusive setting";
            }
        }
    }
}

// Check if currency is used consistently
$currencyUsage = [];
foreach ($filesToCheck as $name => $path) {
    $fullPath = __DIR__ . '/' . $path;
    if (file_exists($fullPath)) {
        $content = file_get_contents($fullPath);
        
        // Check for hardcoded currency
        if (preg_match_all('/(AED|USD|EUR|GBP)[\'"]/', $content, $matches)) {
            $currencyUsage[$name] = $matches[0];
        }
    }
}

if (!empty($currencyUsage)) {
    echo "⚠️  Files with hardcoded currency:\n";
    foreach ($currencyUsage as $file => $currencies) {
        echo "  {$file}: " . implode(', ', array_unique($currencies)) . "\n";
    }
    echo "\n";
}

if (empty($issues) && empty($currencyUsage)) {
    echo "✅ No issues found! All settings are being used correctly.\n\n";
} else {
    if (!empty($issues)) {
        foreach ($issues as $issue) {
            echo "❌ {$issue}\n";
        }
    }
    echo "\n";
}

echo "=== RECOMMENDATIONS ===\n\n";

$recommendations = [];

// Check tax setting usage
if ($taxSetting) {
    if ($taxSetting->tax_inclusive_default) {
        $recommendations[] = "Tax Inclusive is ENABLED - ensure all forms set tax_inclusive=true by default";
    } else {
        $recommendations[] = "Tax Inclusive is DISABLED - ensure all forms set tax_inclusive=false by default";
    }
}

// Check currency setting
if ($currencySetting) {
    $recommendations[] = "Use CurrencySetting::getBase()->currency_symbol instead of hardcoded 'AED'";
}

// Check company branding
if ($companyBranding) {
    $recommendations[] = "Company branding is configured - use it in invoices, quotes, and consignments";
}

if (!empty($recommendations)) {
    foreach ($recommendations as $i => $rec) {
        echo ($i + 1) . ". {$rec}\n";
    }
} else {
    echo "✅ All good!\n";
}

echo "\n=== AUDIT COMPLETE ===\n\n";
