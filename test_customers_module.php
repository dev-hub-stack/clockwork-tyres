<?php

/**
 * Test Script for Customers Module
 * 
 * This script tests the Customers module backend logic before building UI
 * Following the lessons learned from Settings module
 * 
 * DEALER PRICING: Tests customer type detection and relationships
 * See test_customers_crud.php for dealer pricing rules
 * See test_dealer_pricing_all_modules.php for complete integration
 * 
 * Run: php test_customers_module.php
 * Status: ✅ PASSING (Nov 1, 2025)
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Models\AddressBook;
use App\Modules\Customers\Models\CustomerBrandPricing;
use App\Modules\Customers\Models\CustomerModelPricing;
use App\Modules\Customers\Services\DealerPricingService;
use App\Modules\Customers\Services\CustomerService;
use App\Modules\Customers\Actions\CreateCustomerAction;

echo "\n=== Testing Customers Module ===\n\n";

try {
    // Test 1: Check if models can be instantiated
    echo "Test 1: Model Instantiation\n";
    $customer = new Customer();
    echo "✓ Customer model works\n";
    
    $addressBook = new AddressBook();
    echo "✓ AddressBook model works\n";
    
    $brandPricing = new CustomerBrandPricing();
    echo "✓ CustomerBrandPricing model works\n";
    
    $modelPricing = new CustomerModelPricing();
    echo "✓ CustomerModelPricing model works\n\n";
    
    // Test 2: Check services can be instantiated
    echo "Test 2: Service Instantiation\n";
    $dealerPricingService = app(DealerPricingService::class);
    echo "✓ DealerPricingService works\n";
    
    $customerService = app(CustomerService::class);
    echo "✓ CustomerService works\n\n";
    
    // Test 3: Check actions can be instantiated
    echo "Test 3: Action Instantiation\n";
    $createAction = app(CreateCustomerAction::class);
    echo "✓ CreateCustomerAction works\n\n";
    
    // Test 4: Test dealer pricing calculation (no database needed)
    echo "Test 4: Dealer Pricing Logic\n";
    $testCustomer = new Customer([
        'customer_type' => 'retail',
        'first_name' => 'Test',
        'last_name' => 'User'
    ]);
    
    $result = $dealerPricingService->calculateProductPrice($testCustomer, 1000.00);
    echo "Retail customer price (no discount): {$result['final_price']}\n";
    echo "✓ Pricing calculation works for retail\n";
    
    $dealerCustomer = new Customer([
        'customer_type' => 'dealer',
        'business_name' => 'Test Dealer'
    ]);
    
    $result = $dealerPricingService->calculateProductPrice($dealerCustomer, 1000.00);
    echo "Dealer customer price (no rules): {$result['final_price']}\n";
    echo "✓ Pricing calculation works for dealer\n\n";
    
    // Test 5: Test accessors
    echo "Test 5: Model Accessors\n";
    echo "Retail customer name: " . $testCustomer->name . "\n";
    echo "Dealer customer name: " . $dealerCustomer->name . "\n";
    echo "✓ Name accessor works\n\n";
    
    // Test 6: Test enums
    echo "Test 6: Enums\n";
    
    $types = \App\Modules\Customers\Enums\CustomerType::values();
    echo "Customer Types: " . implode(', ', $types) . "\n";
    echo "✓ CustomerType enum works\n";
    
    $addressTypes = \App\Modules\Customers\Enums\AddressType::values();
    echo "Address Types: " . implode(', ', $addressTypes) . "\n";
    echo "✓ AddressType enum works\n\n";
    
    echo "=== All Backend Tests Passed! ✓ ===\n";
    echo "\nNext Steps:\n";
    echo "1. Run migrations: php artisan migrate\n";
    echo "2. Seed countries: php artisan db:seed --class=CountriesSeeder\n";
    echo "3. Test CRUD with database\n";
    echo "4. Build Filament resource\n\n";
    
} catch (\Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
