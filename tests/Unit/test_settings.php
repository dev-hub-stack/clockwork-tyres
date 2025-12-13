<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Modules\Settings\Models\CompanyBranding;
use App\Modules\Settings\Models\CurrencySetting;
use App\Modules\Settings\Models\TaxSetting;

echo "=== Testing Settings Add/Edit Operations ===\n\n";

// Test 1: Edit Company Information
echo "1. Editing Company Information:\n";
echo "--------------------------------\n";

try {
    $company = CompanyBranding::getActive() ?? new CompanyBranding();
    
    $company->company_name = "TunerStop Saudi Arabia";
    $company->company_email = "info@tunerstop.sa";
    $company->company_phone = "+966 11 234 5678";
    $company->company_address = "Riyadh, Saudi Arabia\nKing Fahd Road, Building 123";
    $company->tax_registration_number = "300123456700003";
    $company->commercial_registration = "1010123456";
    $company->company_website = "https://tunerstop.sa";
    $company->primary_color = "#ff6600";
    $company->secondary_color = "#333333";
    $company->invoice_prefix = "INV-";
    $company->quote_prefix = "QUO-";
    $company->order_prefix = "ORD-";
    $company->consignment_prefix = "CON-";
    $company->invoice_footer = "Thank you for your business! Payment terms: 30 days";
    $company->quote_footer = "This quote is valid for 15 days";
    $company->is_active = true;
    
    $company->save();
    
    echo "✓ Company updated successfully\n";
    echo "  - Name: {$company->company_name}\n";
    echo "  - Email: {$company->company_email}\n";
    echo "  - Phone: {$company->company_phone}\n";
    echo "  - Tax Reg: {$company->tax_registration_number}\n";
} catch (\Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

// Test 2: Edit Currency Settings
echo "\n2. Editing Currency Settings:\n";
echo "------------------------------\n";

try {
    $currency = CurrencySetting::getBase() ?? new CurrencySetting();
    
    $currency->currency_code = 'SAR';
    $currency->currency_name = 'Saudi Riyal';
    $currency->currency_symbol = 'SR';
    $currency->symbol_position = 'before';
    $currency->decimal_places = 2;
    $currency->thousands_separator = ',';
    $currency->decimal_separator = '.';
    $currency->exchange_rate = 1.0000;
    $currency->is_base_currency = true;
    $currency->is_active = true;
    
    $currency->save();
    
    echo "✓ Currency updated successfully\n";
    echo "  - Code: {$currency->currency_code}\n";
    echo "  - Name: {$currency->currency_name}\n";
    echo "  - Symbol: {$currency->currency_symbol}\n";
    echo "  - Position: {$currency->symbol_position}\n";
} catch (\Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

// Test 3: Edit Tax Settings
echo "\n3. Editing Tax Settings:\n";
echo "-------------------------\n";

try {
    $tax = TaxSetting::getDefault() ?? new TaxSetting();
    
    $tax->name = 'VAT (Saudi Arabia)';
    $tax->rate = 15.00;
    $tax->tax_inclusive_default = true;
    $tax->description = 'Standard VAT rate for Saudi Arabia';
    $tax->is_default = true;
    $tax->is_active = true;
    
    $tax->save();
    
    echo "✓ Tax updated successfully\n";
    echo "  - Name: {$tax->name}\n";
    echo "  - Rate: {$tax->rate}%\n";
    echo "  - Inclusive: " . ($tax->tax_inclusive_default ? 'Yes' : 'No') . "\n";
} catch (\Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

// Test 4: Add Additional Currency
echo "\n4. Adding Additional Currency (USD):\n";
echo "-------------------------------------\n";

try {
    $usd = CurrencySetting::where('currency_code', 'USD')->first();
    
    if (!$usd) {
        $usd = new CurrencySetting();
    }
    
    $usd->currency_code = 'USD';
    $usd->currency_name = 'US Dollar';
    $usd->currency_symbol = '$';
    $usd->symbol_position = 'before';
    $usd->decimal_places = 2;
    $usd->thousands_separator = ',';
    $usd->decimal_separator = '.';
    $usd->exchange_rate = 3.75; // SAR to USD
    $usd->is_base_currency = false;
    $usd->is_active = true;
    
    $usd->save();
    
    echo "✓ USD currency added successfully\n";
    echo "  - Exchange Rate: 1 SAR = {$usd->exchange_rate} USD\n";
} catch (\Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

// Test 5: Add Additional Tax Rate
echo "\n5. Adding Additional Tax Rate (Zero-rated):\n";
echo "--------------------------------------------\n";

try {
    $zeroTax = TaxSetting::where('name', 'Zero-rated')->first();
    
    if (!$zeroTax) {
        $zeroTax = new TaxSetting();
    }
    
    $zeroTax->name = 'Zero-rated';
    $zeroTax->rate = 0.00;
    $zeroTax->tax_inclusive_default = false;
    $zeroTax->description = 'For zero-rated goods and services';
    $zeroTax->is_default = false;
    $zeroTax->is_active = true;
    
    $zeroTax->save();
    
    echo "✓ Zero-rated tax added successfully\n";
} catch (\Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

// Test 6: Verify All Settings
echo "\n6. Verifying All Settings:\n";
echo "---------------------------\n";

$company = CompanyBranding::getActive();
echo "Company: {$company->company_name}\n";
echo "Email: {$company->company_email}\n";
echo "Phone: {$company->company_phone}\n";
echo "Address: " . str_replace("\n", " | ", $company->company_address) . "\n";

echo "\nCurrencies:\n";
$currencies = CurrencySetting::where('is_active', true)->get();
foreach ($currencies as $curr) {
    echo "  - {$curr->currency_code}: {$curr->currency_name} ({$curr->currency_symbol})";
    if ($curr->is_base_currency) {
        echo " [BASE]";
    } else {
        echo " [Rate: {$curr->exchange_rate}]";
    }
    echo "\n";
}

echo "\nTax Rates:\n";
$taxes = TaxSetting::where('is_active', true)->get();
foreach ($taxes as $t) {
    echo "  - {$t->name}: {$t->rate}%";
    if ($t->is_default) {
        echo " [DEFAULT]";
    }
    echo "\n";
}

// Test 7: Test Settings Service
echo "\n7. Testing Settings Service:\n";
echo "-----------------------------\n";

try {
    $settingsService = app(\App\Modules\Settings\Services\SettingsService::class);
    
    echo "Company Name: " . $settingsService->getCompanyName() . "\n";
    echo "Currency: " . $settingsService->getCurrencyCode() . " (" . $settingsService->getCurrencySymbol() . ")\n";
    echo "Tax Rate: " . $settingsService->getTaxRate() . "%\n";
    
    $testAmount = 1000;
    echo "\nTest Calculations (Amount: {$testAmount}):\n";
    echo "  - Formatted: " . $settingsService->formatCurrency($testAmount) . "\n";
    echo "  - With Tax: " . $settingsService->calculateAmountWithTax($testAmount) . "\n";
    echo "  - Tax Only: " . $settingsService->calculateTax($testAmount) . "\n";
    
    echo "\n✓ Settings Service working correctly\n";
} catch (\Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

echo "\n=== All Tests Complete ===\n";
echo "\nNow go to: http://localhost:8000/admin/manage-settings\n";
echo "You should see all the updated data in the form!\n";

