<?php


$app = require_once dirname(__DIR__) . '/bootstrap.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Modules\Settings\Models\CurrencySetting;

echo "=== Currency Base Setting Fix ===\n\n";

// Get AED currency
$aed = CurrencySetting::where('currency_code', 'AED')->first();

if (!$aed) {
    echo "❌ AED currency not found in database!\n";
    exit(1);
}

echo "Current AED Currency Setting:\n";
echo "- Currency Code: {$aed->currency_code}\n";
echo "- Currency Symbol: {$aed->currency_symbol}\n";
echo "- Is Base Currency: " . ($aed->is_base_currency ? 'YES ✅' : 'NO ❌') . "\n";
echo "- Is Active: " . ($aed->is_active ? 'YES ✅' : 'NO ❌') . "\n\n";

if (!$aed->is_base_currency) {
    echo "Updating AED to be the base currency...\n";
    
    // Set all other currencies to not be base
    CurrencySetting::where('id', '!=', $aed->id)->update(['is_base_currency' => false]);
    
    // Set AED as base
    $aed->is_base_currency = true;
    $aed->save();
    
    echo "✅ AED is now set as the base currency!\n\n";
    
    // Verify
    $aed = CurrencySetting::where('currency_code', 'AED')->first();
    echo "Updated AED Currency Setting:\n";
    echo "- Currency Code: {$aed->currency_code}\n";
    echo "- Currency Symbol: {$aed->currency_symbol}\n";
    echo "- Is Base Currency: " . ($aed->is_base_currency ? 'YES ✅' : 'NO ❌') . "\n";
    echo "- Is Active: " . ($aed->is_active ? 'YES ✅' : 'NO ❌') . "\n";
} else {
    echo "✅ AED is already set as the base currency. No changes needed.\n";
}

// Test getBase() method
echo "\n=== Testing CurrencySetting::getBase() ===\n";
$baseCurrency = CurrencySetting::getBase();
if ($baseCurrency) {
    echo "✅ getBase() returns: {$baseCurrency->currency_code} ({$baseCurrency->currency_symbol})\n";
} else {
    echo "❌ getBase() returned NULL!\n";
}

echo "\n✅ Currency base setting fix complete!\n";
