<?php

use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;
use App\Modules\Orders\Services\QuoteConversionService;
use App\Modules\Orders\Enums\DocumentType;
use App\Modules\Orders\Enums\QuoteStatus;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Creating Test Quote...\n";
$quote = Order::create([
    'document_type' => DocumentType::QUOTE,
    'order_number' => 'QT-TEST-' . time(),
    'customer_id' => 1,
    'quote_status' => QuoteStatus::APPROVED, // Must be approved to convert
    'currency' => 'AED',
    'issue_date' => now(),
    'due_date' => now()->addDays(30),
]);

echo "Quote Created: {$quote->order_number}\n";

echo "Creating Test Product & Variant...\n";
$product = \App\Modules\Products\Models\Product::create([
    'name' => 'Test Product',
    'slug' => 'test-product-' . time(),
    'sku' => 'PROD-SKU-' . time(),
    'price' => 100.00,
    'is_active' => true,
]);

$variant = \App\Modules\Products\Models\ProductVariant::create([
    'product_id' => $product->id,
    'sku' => 'TEST-SKU-' . time(),
    'price' => 100.00,
    'uae_retail_price' => 100.00,
]);

echo "Creating Inventory...\n";
\App\Modules\Inventory\Models\ProductInventory::create([
    'product_variant_id' => $variant->id,
    'warehouse_id' => 1, // Assuming warehouse 1 exists
    'quantity' => 100,
]);

echo "Adding Item...\n";
OrderItem::create([
    'order_id' => $quote->id,
    'product_name' => 'Test Product',
    'sku' => $variant->sku,
    'product_variant_id' => $variant->id,
    'quantity' => 1,
    'unit_price' => 100.00,
    'line_total' => 100.00,
]);

$quote->calculateTotals();
echo "Quote Totals: Subtotal={$quote->sub_total}, VAT={$quote->vat}, Total={$quote->total}\n";

echo "Converting to Invoice...\n";
try {
    $service = app(QuoteConversionService::class);
    $invoice = $service->convertQuoteToInvoice($quote);
    
    echo "Conversion Successful!\n";
    echo "Invoice Number: {$invoice->order_number}\n";
    echo "Invoice Totals: Subtotal={$invoice->sub_total}, VAT={$invoice->vat}, Total={$invoice->total}\n";
    
    if ($invoice->vat == 0) {
        echo "\nERROR: Invoice VAT is 0.00!\n";
    } else {
        echo "\nSUCCESS: Invoice VAT Calculated.\n";
    }
    
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
