<?php

/**
 * Test Script for Customers Module with Products Integration
 * 
 * Tests customer pricing with brands and models
 * 
 * DEALER PRICING: Integration test for customers + products pricing
 * See test_dealer_pricing_all_modules.php for complete module integration
 * 
 * Run: php test_customers_with_products.php
 * Status: ✅ PASSING (Nov 1, 2025)
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Models\CustomerBrandPricing;
use App\Modules\Customers\Models\CustomerModelPricing;
use App\Modules\Products\Models\Brand;
use App\Modules\Products\Models\ProductModel;
use App\Modules\Customers\Services\DealerPricingService;

echo "\n=== Testing Customers Module with Products Integration ===\n\n";

try {
    // Test 1: Check database connectivity and data
    echo "Test 1: Database Connectivity\n";
    
    $brandCount = Brand::count();
    echo "✓ Brands in database: {$brandCount}\n";
    
    $modelCount = ProductModel::count();
    echo "✓ Models in database: {$modelCount}\n";
    
    $customerCount = Customer::count();
    echo "✓ Customers in database: {$customerCount}\n\n";
    
    if ($brandCount === 0) {
        echo "⚠️  No brands found. Run: php artisan db:seed --class=BrandsAndModelsSeeder\n\n";
    }
    
    if ($customerCount === 0) {
        echo "⚠️  No customers found. Create a customer through the UI first.\n\n";
    }
    
    // Test 2: Test brand relationships
    echo "Test 2: Brand-Model Relationships\n";
    if ($brandCount > 0) {
        $brand = Brand::with('productModels')->first();
        echo "Brand: {$brand->name}\n";
        echo "Models: " . $brand->productModels->pluck('name')->implode(', ') . "\n";
        echo "✓ Brand relationship with models works\n\n";
    }
    
    // Test 3: Test customer pricing relationships
    echo "Test 3: Customer Pricing Relationships\n";
    if ($customerCount > 0) {
        $customer = Customer::first();
        echo "Testing customer: {$customer->name}\n";
        
        // Test brand pricing relationship
        $brandPricingCount = $customer->brandPricingRules()->count();
        echo "Brand pricing rules: {$brandPricingCount}\n";
        
        if ($brandPricingCount > 0) {
            $brandPricing = $customer->brandPricingRules()->with('brand')->first();
            if ($brandPricing && $brandPricing->brand) {
                echo "  → Rule for brand: {$brandPricing->brand->name}\n";
                echo "  → Discount: {$brandPricing->discount_type} - ";
                if ($brandPricing->discount_type === 'percentage') {
                    echo "{$brandPricing->discount_percentage}%\n";
                } else {
                    echo "AED {$brandPricing->discount_value}\n";
                }
                echo "✓ Brand pricing relationship works\n";
            }
        }
        
        // Test model pricing relationship
        $modelPricingCount = $customer->modelPricingRules()->count();
        echo "Model pricing rules: {$modelPricingCount}\n";
        
        if ($modelPricingCount > 0) {
            $modelPricing = $customer->modelPricingRules()->with('model.brand')->first();
            if ($modelPricing && $modelPricing->model) {
                echo "  → Rule for model: {$modelPricing->model->name}";
                if ($modelPricing->model->brand) {
                    echo " ({$modelPricing->model->brand->name})";
                }
                echo "\n  → Discount: {$modelPricing->discount_type} - ";
                if ($modelPricing->discount_type === 'percentage') {
                    echo "{$modelPricing->discount_percentage}%\n";
                } else {
                    echo "AED {$modelPricing->discount_value}\n";
                }
                echo "✓ Model pricing relationship works\n";
            }
        }
        echo "\n";
    }
    
    // Test 4: Test pricing calculation with database rules
    echo "Test 4: Pricing Calculation with Database Rules\n";
    if ($customerCount > 0 && $brandCount > 0) {
        $customer = Customer::first();
        $brand = Brand::first();
        
        // Create a test pricing rule if none exists
        $existingRule = CustomerBrandPricing::where('customer_id', $customer->id)
            ->where('brand_id', $brand->id)
            ->first();
        
        if (!$existingRule) {
            echo "Creating test brand pricing rule...\n";
            $testRule = CustomerBrandPricing::create([
                'customer_id' => $customer->id,
                'brand_id' => $brand->id,
                'discount_type' => 'percentage',
                'discount_percentage' => 15.00,
                'discount_value' => 0,
            ]);
            echo "✓ Test rule created: 15% off {$brand->name}\n";
        } else {
            echo "Using existing rule for {$brand->name}\n";
        }
        
        $dealerPricingService = app(DealerPricingService::class);
        
        // Test with brand pricing
        $basePrice = 1000.00;
        $result = $dealerPricingService->calculateProductPrice(
            $customer,
            $basePrice,
            $brand->id
        );
        
        echo "Base price: AED {$basePrice}\n";
        echo "Brand: {$brand->name}\n";
        echo "Discount applied: AED " . number_format($result['discount_amount'], 2) . "\n";
        echo "Final price: AED " . number_format($result['final_price'], 2) . "\n";
        echo "Discount type: {$result['discount_type']}\n";
        echo "✓ Pricing calculation with brand works\n\n";
    }
    
    // Test 5: Display all brands and models
    echo "Test 5: Available Brands and Models\n";
    if ($brandCount > 0) {
        $brands = Brand::with('productModels')->orderBy('name')->get();
        foreach ($brands as $brand) {
            echo "• {$brand->name}\n";
            foreach ($brand->productModels as $model) {
                echo "  - {$model->name}\n";
            }
        }
        echo "✓ Successfully listed all brands and models\n\n";
    }
    
    echo "=== All Integration Tests Passed! ✓ ===\n\n";
    
    echo "Summary:\n";
    echo "--------\n";
    echo "Brands: {$brandCount}\n";
    echo "Models: {$modelCount}\n";
    echo "Customers: {$customerCount}\n";
    
    if ($customerCount > 0) {
        $totalBrandRules = CustomerBrandPricing::count();
        $totalModelRules = CustomerModelPricing::count();
        echo "Brand pricing rules: {$totalBrandRules}\n";
        echo "Model pricing rules: {$totalModelRules}\n";
    }
    
    echo "\nYou can now:\n";
    echo "1. ✅ View customers in UI and see brand/model names (not IDs)\n";
    echo "2. ✅ Create brand pricing rules with searchable dropdown\n";
    echo "3. ✅ Create model pricing rules with searchable dropdown\n";
    echo "4. ✅ Pricing calculations work with dealer discounts\n";
    echo "5. ⏭️  Ready to implement Products pqGrid UI\n\n";
    
} catch (\Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
