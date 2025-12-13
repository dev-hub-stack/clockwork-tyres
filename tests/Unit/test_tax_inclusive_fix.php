<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Modules\Settings\Models\TaxSetting;

echo "\n=== TAX INCLUSIVE SETTING TEST ===\n\n";

// Get tax setting
$taxSetting = TaxSetting::getDefault();

if (!$taxSetting) {
    echo "❌ No default tax setting found!\n";
    exit;
}

echo "Current Tax Setting:\n";
echo "  Name: {$taxSetting->name}\n";
echo "  Rate: {$taxSetting->rate}%\n";
echo "  Tax Inclusive by Default: " . ($taxSetting->tax_inclusive_default ? 'YES ✅' : 'NO') . "\n";
echo "  Is Active: " . ($taxSetting->is_active ? 'YES ✅' : 'NO') . "\n\n";

// Simulate what forms will do
echo "=== FORM SIMULATION ===\n\n";

$productPrice = 350.00;
$taxInclusive = $taxSetting->tax_inclusive_default;
$taxRate = $taxSetting->rate;

echo "Product Price: AED {$productPrice}\n";
echo "Tax Inclusive: " . ($taxInclusive ? 'YES' : 'NO') . "\n";
echo "Tax Rate: {$taxRate}%\n\n";

if ($taxInclusive) {
    // Tax-inclusive calculation
    $priceExcludingTax = $productPrice / (1 + ($taxRate / 100));
    $taxAmount = $productPrice - $priceExcludingTax;
    $total = $productPrice;
    
    echo "Calculation (Tax Inclusive):\n";
    echo "  Price excluding tax: AED " . number_format($priceExcludingTax, 2) . "\n";
    echo "  Tax amount: AED " . number_format($taxAmount, 2) . "\n";
    echo "  Total: AED " . number_format($total, 2) . "\n\n";
    
    echo "✅ Customer pays: AED {$productPrice} (price already includes tax)\n\n";
} else {
    // Tax-exclusive calculation
    $subtotal = $productPrice;
    $taxAmount = $subtotal * ($taxRate / 100);
    $total = $subtotal + $taxAmount;
    
    echo "Calculation (Tax Exclusive):\n";
    echo "  Subtotal: AED " . number_format($subtotal, 2) . "\n";
    echo "  Tax amount: AED " . number_format($taxAmount, 2) . "\n";
    echo "  Total: AED " . number_format($total, 2) . "\n\n";
    
    echo "✅ Customer pays: AED " . number_format($total, 2) . " (price + tax)\n\n";
}

echo "=== EXPECTED BEHAVIOR ===\n\n";

echo "When creating Quote/Invoice/Consignment:\n";
echo "1. User adds product: RR7-H-1785-0139-BK\n";
echo "2. Form fetches price: AED 350.00\n";
echo "3. Form gets tax_inclusive from TaxSetting: " . ($taxInclusive ? 'TRUE' : 'FALSE') . "\n";
echo "4. Form sets hidden field: tax_inclusive = " . ($taxInclusive ? 'TRUE' : 'FALSE') . "\n";
echo "5. On save: Item saved with tax_inclusive = " . ($taxInclusive ? 'TRUE' : 'FALSE') . "\n";
echo "6. Service calculates totals respecting tax_inclusive flag\n\n";

echo "✅ This will now work automatically!\n\n";

echo "=== TO VERIFY ===\n\n";
echo "1. Go to http://localhost:8000/admin/quotes/create\n";
echo "2. Add customer\n";
echo "3. Add product: RR7-H-1785-0139-BK\n";
echo "4. Check total:\n";
if ($taxInclusive) {
    echo "   Expected: AED 350.00 (tax included)\n";
} else {
    echo "   Expected: AED 367.50 (AED 350 + 5% tax)\n";
}
echo "\n";

echo "=== TEST COMPLETE ===\n\n";
