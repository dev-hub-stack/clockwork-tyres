<?php

/**
 * Complete Test Data Seeder
 * Seeds all modules with realistic test data
 * 
 * Usage: php seed_all_test_data.php
 */


$app = require_once dirname(__DIR__) . '/bootstrap.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\{
    CustomerProductModelPricing,
    CustomerBrandPricing,
    CustomerAddonCategoryPricing,
    AddonCategory,
    Addon
};
use App\Modules\Settings\Models\{
    TaxSetting,
    CurrencySetting,
    CompanyBranding
};
use App\Modules\Customers\Models\{
    Country,
    Customer,
    CustomerAddress
};
use App\Modules\Products\Models\{
    Brand,
    ProductModel,
    Finish,
    Product,
    ProductVariant,
    ProductImage
};

echo "🚀 Starting Complete Test Data Seeding...\n\n";

// ============================================================================
// 1. SETTINGS MODULE
// ============================================================================
echo "📋 Seeding Settings...\n";

// Tax Settings
$defaultTax = TaxSetting::where('is_default', true)->first();
if (!$defaultTax) {
    $defaultTax = new TaxSetting();
}
$defaultTax->name = 'Sales Tax';
$defaultTax->rate = 7.50;
$defaultTax->tax_inclusive_default = false;
$defaultTax->description = 'Standard sales tax rate';
$defaultTax->is_default = true;
$defaultTax->is_active = true;
$defaultTax->save();

$vat = TaxSetting::where('name', 'VAT')->first();
if (!$vat) {
    $vat = new TaxSetting();
}
$vat->name = 'VAT';
$vat->rate = 20.00;
$vat->tax_inclusive_default = true;
$vat->description = 'Value Added Tax';
$vat->is_default = false;
$vat->is_active = true;
$vat->save();

echo "  ✅ Created 2 tax settings\n";

// Currency Settings
$usd = CurrencySetting::firstOrNew(['currency_code' => 'USD']);
$usd->currency_name = 'US Dollar';
$usd->currency_symbol = '$';
$usd->symbol_position = 'before';
$usd->decimal_places = 2;
$usd->thousands_separator = ',';
$usd->decimal_separator = '.';
$usd->exchange_rate = 1.0000;
$usd->is_base_currency = true;
$usd->is_active = true;
$usd->save();

$cad = CurrencySetting::firstOrNew(['currency_code' => 'CAD']);
$cad->currency_name = 'Canadian Dollar';
$cad->currency_symbol = 'CA$';
$cad->symbol_position = 'before';
$cad->decimal_places = 2;
$cad->thousands_separator = ',';
$cad->decimal_separator = '.';
$cad->exchange_rate = 1.3500;
$cad->is_base_currency = false;
$cad->is_active = true;
$cad->save();

echo "  ✅ Created/Updated 2 currency settings\n";

// Company Branding
$company = CompanyBranding::where('is_active', true)->first();
if (!$company) {
    $company = new CompanyBranding();
}
$company->company_name = 'Tunerstop Wheels Inc.';
$company->company_email = 'info@tunerstop.com';
$company->company_phone = '+1 (555) 123-4567';
$company->company_address = "123 Wheel Street\nLos Angeles, CA 90001";
$company->company_website = 'https://tunerstop.com';
$company->tax_registration_number = 'US-TAX-123456';
$company->primary_color = '#FF6B6B';
$company->secondary_color = '#4ECDC4';
$company->invoice_prefix = 'INV-';
$company->quote_prefix = 'QUO-';
$company->order_prefix = 'ORD-';
$company->consignment_prefix = 'CON-';
$company->is_active = true;
$company->save();

echo "  ✅ Created company branding\n\n";

// ============================================================================
// 2. COUNTRIES
// ============================================================================
echo "🌍 Seeding Countries...\n";

$countries = [
    ['name' => 'United States', 'code' => 'US', 'code3' => 'USA', 'phone_code' => '1', 'is_active' => true],
    ['name' => 'Canada', 'code' => 'CA', 'code3' => 'CAN', 'phone_code' => '1', 'is_active' => true],
    ['name' => 'United Kingdom', 'code' => 'GB', 'code3' => 'GBR', 'phone_code' => '44', 'is_active' => true],
    ['name' => 'Australia', 'code' => 'AU', 'code3' => 'AUS', 'phone_code' => '61', 'is_active' => true],
    ['name' => 'Germany', 'code' => 'DE', 'code3' => 'DEU', 'phone_code' => '49', 'is_active' => true],
    ['name' => 'France', 'code' => 'FR', 'code3' => 'FRA', 'phone_code' => '33', 'is_active' => true],
    ['name' => 'Japan', 'code' => 'JP', 'code3' => 'JPN', 'phone_code' => '81', 'is_active' => true],
    ['name' => 'Mexico', 'code' => 'MX', 'code3' => 'MEX', 'phone_code' => '52', 'is_active' => true],
    ['name' => 'Brazil', 'code' => 'BR', 'code3' => 'BRA', 'phone_code' => '55', 'is_active' => true],
    ['name' => 'China', 'code' => 'CN', 'code3' => 'CHN', 'phone_code' => '86', 'is_active' => true],
];

foreach ($countries as $country) {
    Country::updateOrCreate(['code' => $country['code']], $country);
}

echo "  ✅ Created 10 countries\n\n";

// ============================================================================
// 3. PRODUCTS MODULE - BRANDS
// ============================================================================
echo "🏷️  Seeding Brands...\n";

$brands = [
    ['name' => 'Rotiform', 'slug' => 'rotiform', 'description' => 'Premium forged wheels', 'status' => 1],
    ['name' => 'BBS', 'slug' => 'bbs', 'description' => 'Legendary racing wheels', 'status' => 1],
    ['name' => 'Vossen', 'slug' => 'vossen', 'description' => 'Luxury performance wheels', 'status' => 1],
    ['name' => 'HRE', 'slug' => 'hre', 'description' => 'Custom forged wheels', 'status' => 1],
    ['name' => 'Enkei', 'slug' => 'enkei', 'description' => 'Lightweight performance wheels', 'status' => 1],
];

$brandModels = [];
foreach ($brands as $brandData) {
    $brand = Brand::updateOrCreate(['slug' => $brandData['slug']], $brandData);
    $brandModels[] = $brand;
}

echo "  ✅ Created 5 brands\n\n";

// ============================================================================
// 4. PRODUCTS MODULE - FINISHES
// ============================================================================
echo "🎨 Seeding Finishes...\n";

$finishes = [
    'Gloss Black',
    'Matte Black',
    'Silver',
    'Gunmetal',
    'Bronze',
    'Chrome',
    'Brushed Titanium',
];

$finishModels = [];
foreach ($finishes as $finishName) {
    $finish = Finish::firstOrCreate(['finish' => $finishName], [
        'status' => 1
    ]);
    $finishModels[] = $finish;
}

echo "  ✅ Created 7 finishes\n\n";

// ============================================================================
// 5. PRODUCTS MODULE - MODELS
// ============================================================================
echo "🔧 Seeding Product Models...\n";

$models = [
    'RSE', 'BLQ', 'KPS', 'CH-R', 'LM', 'FI-R', 
    'CVT', 'HF-3', 'VFS-6', 'P101', 'FF01', 'RPF1', 'NT03'
];

$productModelModels = [];
foreach ($models as $modelName) {
    $model = ProductModel::firstOrCreate(['name' => $modelName], [
        'status' => 1
    ]);
    $productModelModels[] = $model;
}

echo "  ✅ Created 13 product models\n\n";

// ============================================================================
// 6. PRODUCTS MODULE - PRODUCTS & VARIANTS
// ============================================================================
echo "🛞 Seeding Products...\n";

$productCount = 0;

// Create some sample products
$sampleProducts = [
    ['name' => 'RSE 18x8.5', 'sku' => 'RSE-18X8.5', 'price' => 450.00],
    ['name' => 'BLQ 19x9.0', 'sku' => 'BLQ-19X9.0', 'price' => 520.00],
    ['name' => 'KPS 20x9.5', 'sku' => 'KPS-20X9.5', 'price' => 650.00],
    ['name' => 'CH-R 18x8.5', 'sku' => 'CHR-18X8.5', 'price' => 480.00],
    ['name' => 'LM 19x9.0', 'sku' => 'LM-19X9.0', 'price' => 550.00],
];

foreach ($sampleProducts as $productData) {
    Product::firstOrCreate(['sku' => $productData['sku']], [
        'name' => $productData['name'],
        'price' => $productData['price'],
        'brand_id' => $brandModels[0]->id,
        'model_id' => $productModelModels[0]->id,
        'finish_id' => $finishModels[0]->id,
        'status' => 1,
    ]);
    $productCount++;
}

echo "  ✅ Created $productCount products\n\n";

// ============================================================================
// 7. ADDON CATEGORIES & ADDONS
// ============================================================================
echo "🧩 Seeding AddOns...\n";

// First, ensure we have addon categories
$lugNutsCategory = AddonCategory::firstOrCreate(['slug' => 'lug-nuts'], [
    'name' => 'Lug Nuts',
    'order' => 1,
    'is_active' => true
]);

$hubRingsCategory = AddonCategory::firstOrCreate(['slug' => 'hub-rings'], [
    'name' => 'Hub Rings',
    'order' => 2,
    'is_active' => true
]);

$spacersCategory = AddonCategory::firstOrCreate(['slug' => 'spacers'], [
    'name' => 'Spacers',
    'order' => 3,
    'is_active' => true
]);

$tpmsCategory = AddonCategory::firstOrCreate(['slug' => 'tpms'], [
    'name' => 'TPMS',
    'order' => 4,
    'is_active' => true
]);

// Create sample addons
$addonCount = 0;

// Lug Nuts
$lugNutsData = [
    ['title' => 'Chrome Lug Nuts Set', 'price' => 45.99, 'thread_size' => 'M12x1.5', 'color' => 'Chrome'],
    ['title' => 'Black Lug Nuts Set', 'price' => 42.99, 'thread_size' => 'M14x1.5', 'color' => 'Black'],
    ['title' => 'Red Anodized Lug Nuts', 'price' => 55.99, 'thread_size' => 'M12x1.25', 'color' => 'Red'],
];

foreach ($lugNutsData as $data) {
    Addon::firstOrCreate(['title' => $data['title']], array_merge([
        'addon_category_id' => $lugNutsCategory->id,
        'stock_status' => 1,
        'total_quantity' => rand(50, 200),
    ], $data));
    $addonCount++;
}

// Hub Rings
$hubRingsData = [
    ['title' => 'Hub Centric Rings 72.6-66.1', 'price' => 25.99, 'ext_center_bore' => '72.6', 'center_bore' => '66.1'],
    ['title' => 'Hub Centric Rings 73.1-56.1', 'price' => 25.99, 'ext_center_bore' => '73.1', 'center_bore' => '56.1'],
];

foreach ($hubRingsData as $data) {
    Addon::firstOrCreate(['title' => $data['title']], array_merge([
        'addon_category_id' => $hubRingsCategory->id,
        'stock_status' => 1,
        'total_quantity' => rand(100, 300),
    ], $data));
    $addonCount++;
}

// Spacers
$spacersData = [
    ['title' => '5mm Wheel Spacers 5x120', 'price' => 89.99, 'width' => '5mm', 'bolt_pattern' => '5x120'],
    ['title' => '10mm Wheel Spacers 5x114.3', 'price' => 99.99, 'width' => '10mm', 'bolt_pattern' => '5x114.3'],
];

foreach ($spacersData as $data) {
    Addon::firstOrCreate(['title' => $data['title']], array_merge([
        'addon_category_id' => $spacersCategory->id,
        'stock_status' => 1,
        'total_quantity' => rand(30, 100),
    ], $data));
    $addonCount++;
}

// TPMS
$tpmsData = [
    ['title' => 'OEM TPMS Sensors (Set of 4)', 'price' => 199.99, 'description' => 'Compatible with most vehicles'],
    ['title' => 'Universal TPMS Sensors', 'price' => 159.99, 'description' => 'Programmable for multiple vehicles'],
];

foreach ($tpmsData as $data) {
    Addon::firstOrCreate(['title' => $data['title']], array_merge([
        'addon_category_id' => $tpmsCategory->id,
        'stock_status' => 1,
        'total_quantity' => rand(20, 80),
    ], $data));
    $addonCount++;
}

echo "  ✅ Created 4 addon categories\n";
echo "  ✅ Created $addonCount addons\n\n";

// ============================================================================
// 8. CUSTOMERS MODULE
// ============================================================================
echo "👥 Seeding Customers...\n";

$usCountry = Country::where('code', 'US')->first();

$customers = [
    [
        'customer_type' => 'dealer',
        'business_name' => 'Elite Auto Customization',
        'first_name' => 'John',
        'last_name' => 'Smith',
        'email' => 'john@eliteauto.com',
        'phone' => '555-0101',
        'country_id' => $usCountry->id,
        'status' => 1,
    ],
    [
        'customer_type' => 'dealer',
        'business_name' => 'Premium Wheels & Tires',
        'first_name' => 'Sarah',
        'last_name' => 'Johnson',
        'email' => 'sarah@premiumwheels.com',
        'phone' => '555-0102',
        'country_id' => $usCountry->id,
        'status' => 1,
    ],
    [
        'customer_type' => 'retail',
        'business_name' => null,
        'first_name' => 'Michael',
        'last_name' => 'Chen',
        'email' => 'michael.chen@email.com',
        'phone' => '555-0103',
        'country_id' => $usCountry->id,
        'status' => 1,
    ],
];

$customerCount = 0;
foreach ($customers as $customerData) {
    Customer::firstOrCreate(['email' => $customerData['email']], $customerData);
    $customerCount++;
}

echo "  ✅ Created $customerCount customers\n\n";

// ============================================================================
// SEEDING COMPLETE!
// ============================================================================
echo "✅ All test data seeded successfully!\n\n";
echo "📊 Summary:\n";
echo "   - Settings: Tax, Currency, Company\n";
echo "   - Countries: 10\n";
echo "   - Brands: 5\n";
echo "   - Finishes: 7\n";
echo "   - Product Models: 13\n";
echo "   - Products: 5\n";
echo "   - Addon Categories: 4\n";
echo "   - AddOns: 9\n";
echo "   - Customers: $customerCount\n\n";
echo "🌐 Access your admin panel at: http://localhost/admin\n";
echo "📦 Test the following:\n";
echo "   - Products: http://localhost/admin/products\n";
echo "   - AddOns: http://localhost/admin/addons\n";
echo "   - Customers: http://localhost/admin/customers\n";
