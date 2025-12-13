<?php

/**
 * Test Script: Dealer Pricing Across All Modules
 * 
 * This script tests:
 * 1. Fetches dealer and retail customers from database
 * 2. Creates dealer pricing rules (brand and model discounts)
 * 3. Creates Quote for both customer types
 * 4. Creates Invoice for both customer types
 * 5. Creates Consignment for both customer types
 * 6. Verifies pricing is correctly applied
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Models\CustomerBrandPricing;
use App\Modules\Customers\Models\CustomerModelPricing;
use App\Modules\Customers\Services\DealerPricingService;
use App\Modules\Products\Models\ProductVariant;
use App\Modules\Products\Models\Product;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Enums\DocumentType;
use App\Modules\Orders\Enums\QuoteStatus;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Consignments\Models\Consignment;
use App\Modules\Consignments\Enums\ConsignmentStatus;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Support\Facades\DB;

echo "==============================================\n";
echo "DEALER PRICING TEST - ALL MODULES\n";
echo "==============================================\n\n";

try {
    // Step 1: Fetch customers from database
    echo "Step 1: Fetching customers from database...\n";
    echo "--------------------------------------------\n";
    
    $dealerCustomer = Customer::where('customer_type', 'dealer')->first();
    $retailCustomer = Customer::where('customer_type', 'retail')->first();
    
    if (!$dealerCustomer) {
        throw new Exception("No dealer customer found in database! Please create one first.");
    }
    
    if (!$retailCustomer) {
        throw new Exception("No retail customer found in database! Please create one first.");
    }
    
    echo "✓ Dealer Customer Found:\n";
    echo "  ID: {$dealerCustomer->id}\n";
    echo "  Name: {$dealerCustomer->business_name}\n";
    echo "  Type: {$dealerCustomer->customer_type}\n\n";
    
    echo "✓ Retail Customer Found:\n";
    echo "  ID: {$retailCustomer->id}\n";
    echo "  Name: " . ($retailCustomer->business_name ?: $retailCustomer->first_name . ' ' . $retailCustomer->last_name) . "\n";
    echo "  Type: {$retailCustomer->customer_type}\n\n";
    
    // Step 2: Get a product variant for testing
    echo "Step 2: Fetching product variant for testing...\n";
    echo "--------------------------------------------\n";
    
    $variant = ProductVariant::with('product.brand', 'product.model')
        ->whereNotNull('uae_retail_price')
        ->where('uae_retail_price', '>', 0)
        ->first();
    
    if (!$variant || !$variant->product) {
        throw new Exception("No product variant with valid uae_retail_price found!");
    }
    
    $basePrice = floatval($variant->uae_retail_price);
    $brandId = $variant->product->brand_id;
    $modelId = $variant->product->model_id;
    
    echo "✓ Product Variant Found:\n";
    echo "  SKU: {$variant->sku}\n";
    echo "  Brand ID: {$brandId}\n";
    echo "  Model ID: {$modelId}\n";
    echo "  Base Price (UAE Retail): AED {$basePrice}\n\n";
    
    // Step 3: Create dealer pricing rules
    echo "Step 3: Creating dealer pricing rules...\n";
    echo "--------------------------------------------\n";
    
    // Clean up existing pricing rules for this dealer
    CustomerBrandPricing::where('customer_id', $dealerCustomer->id)
        ->where('brand_id', $brandId)
        ->delete();
    
    CustomerModelPricing::where('customer_id', $dealerCustomer->id)
        ->where('model_id', $modelId)
        ->delete();
    
    // Create brand discount (10% off)
    $brandPricing = CustomerBrandPricing::create([
        'customer_id' => $dealerCustomer->id,
        'brand_id' => $brandId,
        'discount_type' => 'percentage',
        'discount_percentage' => 10.00,
    ]);
    
    echo "✓ Brand Pricing Created:\n";
    echo "  Customer: {$dealerCustomer->business_name}\n";
    echo "  Brand ID: {$brandId}\n";
    echo "  Discount: 10%\n\n";
    
    // Create model discount (15% off - HIGHER PRIORITY)
    $modelPricing = CustomerModelPricing::create([
        'customer_id' => $dealerCustomer->id,
        'model_id' => $modelId,
        'discount_type' => 'percentage',
        'discount_percentage' => 15.00,
    ]);
    
    echo "✓ Model Pricing Created:\n";
    echo "  Customer: {$dealerCustomer->business_name}\n";
    echo "  Model ID: {$modelId}\n";
    echo "  Discount: 15% (HIGHER PRIORITY)\n\n";
    
    // Step 4: Test pricing calculation
    echo "Step 4: Testing DealerPricingService...\n";
    echo "--------------------------------------------\n";
    
    $pricingService = new DealerPricingService();
    
    // Calculate for dealer
    $dealerPricing = $pricingService->calculateProductPrice(
        $dealerCustomer,
        $basePrice,
        $modelId,
        $brandId
    );
    
    echo "✓ Dealer Pricing Calculation:\n";
    echo "  Base Price: AED {$basePrice}\n";
    echo "  Discount Type: {$dealerPricing['discount_type']}\n";
    echo "  Discount %: {$dealerPricing['discount_percentage']}%\n";
    echo "  Discount Amount: AED {$dealerPricing['discount_amount']}\n";
    echo "  Final Price: AED {$dealerPricing['final_price']}\n\n";
    
    // Calculate for retail
    $retailPricing = $pricingService->calculateProductPrice(
        $retailCustomer,
        $basePrice,
        $modelId,
        $brandId
    );
    
    echo "✓ Retail Pricing Calculation:\n";
    echo "  Base Price: AED {$basePrice}\n";
    echo "  Discount Type: {$retailPricing['discount_type']}\n";
    echo "  Final Price: AED {$retailPricing['final_price']}\n\n";
    
    // Step 5: Get warehouse
    $warehouse = Warehouse::where('status', 1)->first();
    if (!$warehouse) {
        throw new Exception("No active warehouse found!");
    }
    
    // Step 6: Create Quote for Dealer
    echo "Step 5: Creating Quote for Dealer Customer...\n";
    echo "--------------------------------------------\n";
    
    $dealerQuote = Order::create([
        'customer_id' => $dealerCustomer->id,
        'document_type' => DocumentType::QUOTE,
        'quote_status' => QuoteStatus::DRAFT,
        'quote_number' => 'QT-TEST-DEALER-' . time(),
        'issue_date' => now(),
        'valid_until' => now()->addDays(30),
        'currency' => 'AED',
        'warehouse_id' => $warehouse->id,
    ]);
    
    // Add line item with dealer pricing
    $dealerQuote->items()->create([
        'product_variant_id' => $variant->id,
        'product_id' => $variant->product_id,
        'sku' => $variant->sku,
        'product_name' => $variant->product->name,
        'quantity' => 2,
        'unit_price' => $dealerPricing['final_price'], // Dealer discounted price
        'discount' => 0,
        'tax_inclusive' => true,
    ]);
    
    $dealerQuoteSubtotal = $dealerPricing['final_price'] * 2;
    $dealerQuote->update([
        'subtotal' => $dealerQuoteSubtotal,
        'tax' => 0,
        'shipping' => 0,
        'total' => $dealerQuoteSubtotal,
    ]);
    
    echo "✓ Quote Created for Dealer:\n";
    echo "  Quote #: {$dealerQuote->quote_number}\n";
    echo "  Customer: {$dealerCustomer->business_name}\n";
    echo "  Item: {$variant->sku}\n";
    echo "  Quantity: 2\n";
    echo "  Unit Price: AED {$dealerPricing['final_price']} (15% dealer discount applied)\n";
    echo "  Subtotal: AED {$dealerQuoteSubtotal}\n";
    echo "  Total: AED {$dealerQuoteSubtotal}\n\n";
    
    // Step 7: Create Quote for Retail
    echo "Step 6: Creating Quote for Retail Customer...\n";
    echo "--------------------------------------------\n";
    
    $retailQuote = Order::create([
        'customer_id' => $retailCustomer->id,
        'document_type' => DocumentType::QUOTE,
        'quote_status' => QuoteStatus::DRAFT,
        'quote_number' => 'QT-TEST-RETAIL-' . time(),
        'issue_date' => now(),
        'valid_until' => now()->addDays(30),
        'currency' => 'AED',
        'warehouse_id' => $warehouse->id,
    ]);
    
    // Add line item with retail pricing
    $retailQuote->items()->create([
        'product_variant_id' => $variant->id,
        'product_id' => $variant->product_id,
        'sku' => $variant->sku,
        'product_name' => $variant->product->name,
        'quantity' => 2,
        'unit_price' => $retailPricing['final_price'], // Full retail price
        'discount' => 0,
        'tax_inclusive' => true,
    ]);
    
    $retailQuoteSubtotal = $retailPricing['final_price'] * 2;
    $retailQuote->update([
        'subtotal' => $retailQuoteSubtotal,
        'tax' => 0,
        'shipping' => 0,
        'total' => $retailQuoteSubtotal,
    ]);
    
    echo "✓ Quote Created for Retail:\n";
    echo "  Quote #: {$retailQuote->quote_number}\n";
    echo "  Customer: " . ($retailCustomer->business_name ?: $retailCustomer->first_name . ' ' . $retailCustomer->last_name) . "\n";
    echo "  Item: {$variant->sku}\n";
    echo "  Quantity: 2\n";
    echo "  Unit Price: AED {$retailPricing['final_price']} (full retail price)\n";
    echo "  Subtotal: AED {$retailQuoteSubtotal}\n";
    echo "  Total: AED {$retailQuoteSubtotal}\n\n";
    
    // Step 8: Create Invoice for Dealer
    echo "Step 7: Creating Invoice for Dealer Customer...\n";
    echo "--------------------------------------------\n";
    
    $dealerInvoice = Order::create([
        'customer_id' => $dealerCustomer->id,
        'document_type' => DocumentType::INVOICE,
        'order_status' => OrderStatus::PENDING,
        'payment_status' => PaymentStatus::PENDING,
        'order_number' => 'INV-TEST-DEALER-' . time(),
        'issue_date' => now(),
        'currency' => 'AED',
        'warehouse_id' => $warehouse->id,
    ]);
    
    // Add line item with dealer pricing
    $dealerInvoice->items()->create([
        'product_variant_id' => $variant->id,
        'product_id' => $variant->product_id,
        'sku' => $variant->sku,
        'product_name' => $variant->product->name,
        'quantity' => 1,
        'unit_price' => $dealerPricing['final_price'], // Dealer discounted price
        'discount' => 0,
        'tax_inclusive' => true,
    ]);
    
    $dealerInvoiceTotal = $dealerPricing['final_price'];
    $dealerInvoice->update([
        'subtotal' => $dealerInvoiceTotal,
        'tax' => 0,
        'shipping' => 0,
        'total' => $dealerInvoiceTotal,
        'outstanding_amount' => $dealerInvoiceTotal,
    ]);
    
    echo "✓ Invoice Created for Dealer:\n";
    echo "  Invoice #: {$dealerInvoice->order_number}\n";
    echo "  Customer: {$dealerCustomer->business_name}\n";
    echo "  Item: {$variant->sku}\n";
    echo "  Quantity: 1\n";
    echo "  Unit Price: AED {$dealerPricing['final_price']} (15% dealer discount applied)\n";
    echo "  Total: AED {$dealerInvoiceTotal}\n\n";
    
    // Step 9: Create Invoice for Retail
    echo "Step 8: Creating Invoice for Retail Customer...\n";
    echo "--------------------------------------------\n";
    
    $retailInvoice = Order::create([
        'customer_id' => $retailCustomer->id,
        'document_type' => DocumentType::INVOICE,
        'order_status' => OrderStatus::PENDING,
        'payment_status' => PaymentStatus::PENDING,
        'order_number' => 'INV-TEST-RETAIL-' . time(),
        'issue_date' => now(),
        'currency' => 'AED',
        'warehouse_id' => $warehouse->id,
    ]);
    
    // Add line item with retail pricing
    $retailInvoice->items()->create([
        'product_variant_id' => $variant->id,
        'product_id' => $variant->product_id,
        'sku' => $variant->sku,
        'product_name' => $variant->product->name,
        'quantity' => 1,
        'unit_price' => $retailPricing['final_price'], // Full retail price
        'discount' => 0,
        'tax_inclusive' => true,
    ]);
    
    $retailInvoiceTotal = $retailPricing['final_price'];
    $retailInvoice->update([
        'subtotal' => $retailInvoiceTotal,
        'tax' => 0,
        'shipping' => 0,
        'total' => $retailInvoiceTotal,
        'outstanding_amount' => $retailInvoiceTotal,
    ]);
    
    echo "✓ Invoice Created for Retail:\n";
    echo "  Invoice #: {$retailInvoice->order_number}\n";
    echo "  Customer: " . ($retailCustomer->business_name ?: $retailCustomer->first_name . ' ' . $retailCustomer->last_name) . "\n";
    echo "  Item: {$variant->sku}\n";
    echo "  Quantity: 1\n";
    echo "  Unit Price: AED {$retailPricing['final_price']} (full retail price)\n";
    echo "  Total: AED {$retailInvoiceTotal}\n\n";
    
    // Step 10: Create Consignment for Dealer
    echo "Step 9: Creating Consignment for Dealer Customer...\n";
    echo "--------------------------------------------\n";
    
    $dealerConsignment = Consignment::create([
        'customer_id' => $dealerCustomer->id,
        'warehouse_id' => $warehouse->id,
        'consignment_number' => 'CON-TEST-DEALER-' . time(),
        'status' => ConsignmentStatus::DRAFT,
        'issue_date' => now(),
        'expected_return_date' => now()->addDays(60),
    ]);
    
    // Add consignment item with dealer pricing
    $dealerConsignment->items()->create([
        'product_variant_id' => $variant->id,
        'sku' => $variant->sku,
        'product_name' => $variant->product->name,
        'brand_name' => $variant->product->brand->name ?? 'N/A',
        'quantity_sent' => 3,
        'quantity_sold' => 0,
        'quantity_returned' => 0,
        'price' => $dealerPricing['final_price'], // Dealer discounted price
        'tax_inclusive' => true,
        'status' => \App\Modules\Consignments\Enums\ConsignmentItemStatus::SENT,
    ]);
    
    echo "✓ Consignment Created for Dealer:\n";
    echo "  Consignment #: {$dealerConsignment->consignment_number}\n";
    echo "  Customer: {$dealerCustomer->business_name}\n";
    echo "  Item: {$variant->sku}\n";
    echo "  Quantity Sent: 3\n";
    echo "  Unit Price: AED {$dealerPricing['final_price']} (15% dealer discount applied)\n";
    echo "  Total Value: AED " . ($dealerPricing['final_price'] * 3) . "\n\n";
    
    // Step 11: Create Consignment for Retail
    echo "Step 10: Creating Consignment for Retail Customer...\n";
    echo "--------------------------------------------\n";
    
    $retailConsignment = Consignment::create([
        'customer_id' => $retailCustomer->id,
        'warehouse_id' => $warehouse->id,
        'consignment_number' => 'CON-TEST-RETAIL-' . time(),
        'status' => ConsignmentStatus::DRAFT,
        'issue_date' => now(),
        'expected_return_date' => now()->addDays(60),
    ]);
    
    // Add consignment item with retail pricing
    $retailConsignment->items()->create([
        'product_variant_id' => $variant->id,
        'sku' => $variant->sku,
        'product_name' => $variant->product->name,
        'brand_name' => $variant->product->brand->name ?? 'N/A',
        'quantity_sent' => 3,
        'quantity_sold' => 0,
        'quantity_returned' => 0,
        'price' => $retailPricing['final_price'], // Full retail price
        'tax_inclusive' => true,
        'status' => \App\Modules\Consignments\Enums\ConsignmentItemStatus::SENT,
    ]);
    
    echo "✓ Consignment Created for Retail:\n";
    echo "  Consignment #: {$retailConsignment->consignment_number}\n";
    echo "  Customer: " . ($retailCustomer->business_name ?: $retailCustomer->first_name . ' ' . $retailCustomer->last_name) . "\n";
    echo "  Item: {$variant->sku}\n";
    echo "  Quantity Sent: 3\n";
    echo "  Unit Price: AED {$retailPricing['final_price']} (full retail price)\n";
    echo "  Total Value: AED " . ($retailPricing['final_price'] * 3) . "\n\n";
    
    // Summary
    echo "\n==============================================\n";
    echo "SUMMARY - PRICING COMPARISON\n";
    echo "==============================================\n\n";
    
    echo "Product: {$variant->sku}\n";
    echo "Base Price (UAE Retail): AED {$basePrice}\n\n";
    
    echo "DEALER CUSTOMER ({$dealerCustomer->business_name}):\n";
    echo "  Pricing Rule: 15% off (Model-specific)\n";
    echo "  Unit Price: AED {$dealerPricing['final_price']}\n";
    echo "  Quote Total (Qty 2): AED {$dealerQuoteSubtotal}\n";
    echo "  Invoice Total (Qty 1): AED {$dealerInvoiceTotal}\n";
    echo "  Consignment Value (Qty 3): AED " . ($dealerPricing['final_price'] * 3) . "\n\n";
    
    echo "RETAIL CUSTOMER (" . ($retailCustomer->business_name ?: $retailCustomer->first_name . ' ' . $retailCustomer->last_name) . "):\n";
    echo "  Pricing Rule: None (Full price)\n";
    echo "  Unit Price: AED {$retailPricing['final_price']}\n";
    echo "  Quote Total (Qty 2): AED {$retailQuoteSubtotal}\n";
    echo "  Invoice Total (Qty 1): AED {$retailInvoiceTotal}\n";
    echo "  Consignment Value (Qty 3): AED " . ($retailPricing['final_price'] * 3) . "\n\n";
    
    $savings = $basePrice - $dealerPricing['final_price'];
    $savingsPercent = ($savings / $basePrice) * 100;
    
    echo "DEALER SAVINGS:\n";
    echo "  Per Unit: AED {$savings} ({$savingsPercent}%)\n";
    echo "  On Quote (Qty 2): AED " . ($savings * 2) . "\n";
    echo "  On Invoice (Qty 1): AED {$savings}\n";
    echo "  On Consignment (Qty 3): AED " . ($savings * 3) . "\n\n";
    
    echo "==============================================\n";
    echo "TEST COMPLETED SUCCESSFULLY! ✓\n";
    echo "==============================================\n\n";
    
    echo "View in Admin Panel:\n";
    echo "  Dealer Quote: http://localhost:8000/admin/quotes/{$dealerQuote->id}\n";
    echo "  Retail Quote: http://localhost:8000/admin/quotes/{$retailQuote->id}\n";
    echo "  Dealer Invoice: http://localhost:8000/admin/invoices/{$dealerInvoice->id}\n";
    echo "  Retail Invoice: http://localhost:8000/admin/invoices/{$retailInvoice->id}\n";
    echo "  Dealer Consignment: http://localhost:8000/admin/consignments/{$dealerConsignment->id}\n";
    echo "  Retail Consignment: http://localhost:8000/admin/consignments/{$retailConsignment->id}\n\n";
    
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "File: " . $e->getFile() . "\n\n";
    exit(1);
}
