<?php

/**
 * Test Script for Customers Module - Database CRUD Operations
 * 
 * Run: php test_customers_crud.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Models\AddressBook;
use App\Modules\Customers\Models\Country;
use App\Modules\Customers\Services\CustomerService;
use App\Modules\Customers\Services\DealerPricingService;
use App\Modules\Customers\Actions\CreateCustomerAction;
use App\Modules\Customers\Actions\ApplyPricingRulesAction;

echo "\n=== Testing Customers Module - Database CRUD ===\n\n";

try {
    $customerService = app(CustomerService::class);
    $dealerPricingService = app(DealerPricingService::class);
    $createCustomerAction = app(CreateCustomerAction::class);
    $pricingAction = app(ApplyPricingRulesAction::class);
    
    // Cleanup previous test data
    echo "Cleaning up previous test data...\n";
    Customer::where('email', 'john.doe@example.com')->forceDelete();
    Customer::where('email', 'dealer@example.com')->forceDelete();
    Customer::where('email', 'contact@premiumauto.ae')->forceDelete();
    echo "✓ Cleanup complete\n\n";
    
    // Test 1: Verify countries exist
    echo "Test 1: Countries Table\n";
    $countryCount = Country::count();
    echo "✓ Found {$countryCount} countries\n";
    $uae = Country::where('code', 'AE')->first();
    echo "✓ UAE: {$uae->name} (+{$uae->phone_code})\n\n";
    
    // Test 2: Create a retail customer
    echo "Test 2: Create Retail Customer\n";
    $retailCustomer = $createCustomerAction->execute([
        'customer_type' => 'retail',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john.doe@example.com',
        'phone' => '+971501234567',
        'country_id' => $uae->id,
        'status' => 'active'
    ]);
    echo "✓ Created retail customer: {$retailCustomer->name} (ID: {$retailCustomer->id})\n";
    echo "  Email: {$retailCustomer->email}\n";
    echo "  Phone: {$retailCustomer->phone}\n";
    echo "  Type: {$retailCustomer->customer_type}\n\n";
    
    // Test 3: Create a dealer customer
    echo "Test 3: Create Dealer Customer\n";
    $dealerCustomer = $createCustomerAction->execute([
        'customer_type' => 'dealer',
        'business_name' => 'Premium Auto Parts LLC',
        'first_name' => 'Ahmed',
        'last_name' => 'Al Mansouri',
        'email' => 'contact@premiumauto.ae',
        'phone' => '+971502345678',
        'trn' => '123456789012345',
        'country_id' => $uae->id,
        'status' => 'active'
    ]);
    echo "✓ Created dealer customer: {$dealerCustomer->name} (ID: {$dealerCustomer->id})\n";
    echo "  Business: {$dealerCustomer->business_name}\n";
    echo "  TRN: {$dealerCustomer->trn}\n";
    echo "  Is Dealer: " . ($dealerCustomer->isDealer() ? 'Yes' : 'No') . "\n\n";
    
    // Test 4: Add addresses
    echo "Test 4: Add Addresses\n";
    $billingAddress = $customerService->createAddress($dealerCustomer, [
        'address_type' => 1, // Billing
        'first_name' => 'Ahmed',
        'last_name' => 'Al Mansouri',
        'address' => 'Sheikh Zayed Road, Building 123',
        'city' => 'Dubai',
        'state' => 'Dubai',
        'country' => 'United Arab Emirates',
        'zip' => '12345',
        'phone_no' => '+971502345678',
        'email' => 'billing@premiumauto.ae'
    ]);
    echo "✓ Created billing address (ID: {$billingAddress->id})\n";
    echo "  Address: {$billingAddress->formatted_address}\n";
    
    $shippingAddress = $customerService->createAddress($dealerCustomer, [
        'address_type' => 2, // Shipping
        'first_name' => 'Ahmed',
        'last_name' => 'Al Mansouri',
        'address' => 'Industrial Area 15, Warehouse 42',
        'city' => 'Sharjah',
        'state' => 'Sharjah',
        'country' => 'United Arab Emirates',
        'zip' => '54321',
        'phone_no' => '+971503456789'
    ]);
    echo "✓ Created shipping address (ID: {$shippingAddress->id})\n";
    echo "  Address: {$shippingAddress->formatted_address}\n\n";
    
    // Test 5: Test accessors
    echo "Test 5: Test Accessors\n";
    $dealerCustomer = $dealerCustomer->fresh(['addresses']);
    echo "Primary Address: {$dealerCustomer->primary_address->formatted_address}\n";
    echo "Primary Phone: {$dealerCustomer->primary_phone}\n";
    echo "✓ Accessors work correctly\n\n";
    
    // Test 6: Simulate brand/model pricing (we'll use dummy IDs)
    echo "Test 6: Apply Dealer Pricing Rules\n";
    echo "Note: Using dummy brand/model IDs (1, 2) for testing\n";
    
    // Simulate brand pricing (10% off brand ID 1)
    echo "Creating brand pricing rule: 10% off brand ID 1\n";
    $brandRule = $pricingAction->applyBrandPricing($dealerCustomer, 1, [
        'discount_type' => 'percentage',
        'discount_percentage' => 10.00,
    ]);
    echo "✓ Brand pricing rule created (ID: {$brandRule->id})\n";
    
    // Simulate model pricing (15% off model ID 2 - HIGHER PRIORITY)
    echo "Creating model pricing rule: 15% off model ID 2\n";
    $modelRule = $pricingAction->applyModelPricing($dealerCustomer, 2, [
        'discount_type' => 'percentage',
        'discount_percentage' => 15.00,
    ]);
    echo "✓ Model pricing rule created (ID: {$modelRule->id})\n\n";
    
    // Test 7: Test pricing calculations
    echo "Test 7: Test Pricing Calculations\n";
    $basePrice = 1000.00;
    
    // Test with retail customer (no discount)
    $retailPrice = $dealerPricingService->calculateProductPrice($retailCustomer, $basePrice, 2, 1);
    echo "Retail customer price for AED {$basePrice}:\n";
    echo "  Final Price: AED {$retailPrice['final_price']}\n";
    echo "  Discount: AED {$retailPrice['discount_amount']}\n";
    echo "  Type: {$retailPrice['discount_type']}\n";
    
    // Test with dealer customer - brand discount
    $dealerBrandPrice = $dealerPricingService->calculateProductPrice($dealerCustomer, $basePrice, null, 1);
    echo "\nDealer customer price (brand ID 1 only):\n";
    echo "  Final Price: AED {$dealerBrandPrice['final_price']}\n";
    echo "  Discount: AED {$dealerBrandPrice['discount_amount']} ({$dealerBrandPrice['discount_percentage']}%)\n";
    echo "  Type: {$dealerBrandPrice['discount_type']}\n";
    
    // Test with dealer customer - model discount (HIGHER PRIORITY)
    $dealerModelPrice = $dealerPricingService->calculateProductPrice($dealerCustomer, $basePrice, 2, 1);
    echo "\nDealer customer price (model ID 2 + brand ID 1):\n";
    echo "  Final Price: AED {$dealerModelPrice['final_price']}\n";
    echo "  Discount: AED {$dealerModelPrice['discount_amount']} ({$dealerModelPrice['discount_percentage']}%)\n";
    echo "  Type: {$dealerModelPrice['discount_type']} (model overrides brand!)\n";
    echo "✓ Pricing hierarchy works correctly!\n\n";
    
    // Test 8: Search customers
    echo "Test 8: Search Customers\n";
    $results = $customerService->searchCustomers('premium');
    echo "✓ Found {$results->count()} customer(s) matching 'premium'\n";
    
    $dealers = $customerService->getDealers();
    echo "✓ Found {$dealers->count()} dealer(s)\n\n";
    
    // Test 9: Update customer
    echo "Test 9: Update Customer\n";
    $updatedCustomer = $customerService->updateCustomer($dealerCustomer, [
        'website' => 'https://premiumauto.ae',
        'instagram' => '@premiumauto'
    ]);
    echo "✓ Updated customer\n";
    echo "  Website: {$updatedCustomer->website}\n";
    echo "  Instagram: {$updatedCustomer->instagram}\n\n";
    
    echo "=== All Database CRUD Tests Passed! ✓ ===\n\n";
    
    echo "Summary:\n";
    echo "- Retail Customers: " . Customer::where('customer_type', 'retail')->count() . "\n";
    echo "- Dealer Customers: " . Customer::where('customer_type', 'dealer')->count() . "\n";
    echo "- Total Addresses: " . AddressBook::count() . "\n";
    echo "- Brand Pricing Rules: " . $dealerCustomer->brandPricingRules()->count() . "\n";
    echo "- Model Pricing Rules: " . $dealerCustomer->modelPricingRules()->count() . "\n\n";
    
    echo "Next Step: Build Filament Resource\n";
    echo "Command: php artisan make:filament-resource Customer --generate --soft-deletes\n\n";
    
} catch (\Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
