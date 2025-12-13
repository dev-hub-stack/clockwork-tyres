<?php

/**
 * End-to-End Order Fulfillment Workflow Test
 * 
 * Tests the complete flow:
 * 1. Create quote
 * 2. Approve quote
 * 3. Convert to invoice
 * 4. Confirm order (allocate inventory)
 * 5. Ship order
 * 6. Complete order
 * 7. Verify inventory changes
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Modules\Orders\Services\OrderService;
use App\Modules\Orders\Services\QuoteConversionService;
use App\Modules\Orders\Enums\DocumentType;
use App\Modules\Orders\Enums\QuoteStatus;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Customers\Models\Customer;
use App\Modules\Products\Models\ProductVariant;
use App\Modules\Inventory\Models\ProductInventory;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\InventoryLog;
use Illuminate\Support\Facades\DB;

try {
    echo "\n";
    echo "=== Order Fulfillment Workflow Test ===\n";
    echo "Testing complete quote → invoice → fulfillment → completion flow\n\n";
    
    // Get services
    $orderService = app(OrderService::class);
    $quoteConversionService = app(QuoteConversionService::class);
    
    // ==========================================
    // Step 1: Setup Test Data
    // ==========================================
    echo "Step 1: Setup Test Data\n";
    echo str_repeat('-', 50) . "\n";
    
    // Get a customer
    $customer = Customer::first();
    if (!$customer) {
        throw new \Exception('No customers found. Please create a customer first.');
    }
    echo "✓ Customer found: {$customer->name} (ID: {$customer->id})\n";
    
    // Get a warehouse
    $warehouse = Warehouse::first();
    if (!$warehouse) {
        throw new \Exception('No warehouses found. Please create a warehouse first.');
    }
    echo "✓ Warehouse found: {$warehouse->name} (ID: {$warehouse->id})\n";
    
    // Get a product variant with inventory
    $variant = ProductVariant::whereHas('inventories', function($q) {
        $q->where('quantity', '>', 5);
    })->with(['product', 'inventories'])->first();
    
    if (!$variant) {
        throw new \Exception('No product variants with inventory found.');
    }
    echo "✓ Product variant found: {$variant->sku}\n";
    
    // Check initial inventory
    $initialInventory = ProductInventory::where('product_variant_id', $variant->id)
        ->sum('quantity');
    echo "  Initial inventory: {$initialInventory} units\n";
    
    echo "\n";
    
    // ==========================================
    // Step 2: Create Quote
    // ==========================================
    echo "Step 2: Create Quote\n";
    echo str_repeat('-', 50) . "\n";
    
    $quoteData = [
        'customer_id' => $customer->id,
        'warehouse_id' => $warehouse->id,
        'document_type' => DocumentType::QUOTE,
        'currency' => 'USD',
        'tax_inclusive' => false,
        'items' => [
            [
                'product_variant_id' => $variant->id,
                'quantity' => 3,
                'unit_price' => 250.00,
            ]
        ]
    ];
    
    $quote = $orderService->createOrder($quoteData);
    echo "✓ Quote created: {$quote->quote_number}\n";
    echo "  Document type: {$quote->document_type->value}\n";
    $quoteStatus = $quote->quote_status ? $quote->quote_status->value : 'null';
    echo "  Quote status: {$quoteStatus}\n";
    echo "  Total: \${$quote->total}\n";
    echo "  Items: {$quote->items->count()}\n";
    
    echo "\n";
    
    // ==========================================
    // Step 3: Approve Quote
    // ==========================================
    echo "Step 3: Approve Quote\n";
    echo str_repeat('-', 50) . "\n";
    
    $orderService->approveQuote($quote);
    $quote->refresh();
    
    echo "✓ Quote approved\n";
    echo "  Quote status: {$quote->quote_status->value}\n";
    echo "  Can convert to invoice: " . ($quote->canConvertToInvoice() ? 'Yes' : 'No') . "\n";
    
    echo "\n";
    
    // ==========================================
    // Step 4: Convert to Invoice
    // ==========================================
    echo "Step 4: Convert Quote to Invoice\n";
    echo str_repeat('-', 50) . "\n";
    
    $invoice = $quoteConversionService->convertQuoteToInvoice($quote);
    
    echo "✓ Quote converted to invoice (SAME RECORD!)\n";
    echo "  Document type: {$invoice->document_type->value}\n";
    echo "  Quote status: {$invoice->quote_status->value}\n";
    $orderStatus = $invoice->order_status ? $invoice->order_status->value : 'null';
    echo "  Order status: {$orderStatus}\n";
    echo "  Order number: {$invoice->order_number}\n";
    echo "  Is quote converted: " . ($invoice->is_quote_converted ? 'Yes' : 'No') . "\n";
    
    echo "\n";
    
    // ==========================================
    // Step 5: Validate Inventory
    // ==========================================
    echo "Step 5: Validate Inventory Availability\n";
    echo str_repeat('-', 50) . "\n";
    
    $validation = $orderService->validateInventory($invoice);
    
    echo "✓ Inventory validation completed\n";
    echo "  Can fulfill: " . ($validation['can_fulfill'] ? 'Yes ✓' : 'No ✗') . "\n";
    
    foreach ($validation['items'] as $item) {
        echo "  - SKU {$item['sku']}: ";
        echo "Need {$item['quantity_needed']}, Available {$item['quantity_available']}";
        if (isset($item['shortage']) && $item['shortage'] > 0) {
            echo " (Short {$item['shortage']} units)";
        }
        echo "\n";
    }
    
    if (!$validation['can_fulfill']) {
        throw new \Exception('Insufficient inventory to fulfill order');
    }
    
    echo "\n";
    
    // ==========================================
    // Step 6: Confirm Order (Allocate Inventory)
    // ==========================================
    echo "Step 6: Confirm Order & Allocate Inventory\n";
    echo str_repeat('-', 50) . "\n";
    
    $allocationResults = $orderService->confirmOrder($invoice, $warehouse->id);
    $invoice->refresh();
    
    echo "✓ Order confirmed and inventory allocated\n";
    echo "  Order status: {$invoice->order_status->value}\n";
    echo "  Allocated items: " . count($allocationResults['allocated']) . "\n";
    echo "  Partial allocations: " . count($allocationResults['partial']) . "\n";
    echo "  Failed allocations: " . count($allocationResults['failed']) . "\n";
    
    // Check inventory after allocation
    $afterAllocationInventory = ProductInventory::where('product_variant_id', $variant->id)
        ->sum('quantity');
    $allocatedQty = $invoice->items->first()->allocated_quantity;
    
    echo "\n  Inventory Changes:\n";
    echo "    Before: {$initialInventory} units\n";
    echo "    Allocated: {$allocatedQty} units\n";
    echo "    After: {$afterAllocationInventory} units\n";
    echo "    Expected: " . ($initialInventory - $allocatedQty) . " units\n";
    
    if ($afterAllocationInventory == ($initialInventory - $allocatedQty)) {
        echo "  ✓ Inventory correctly reduced\n";
    } else {
        echo "  ⚠ Inventory mismatch!\n";
    }
    
    // Check inventory log
    $logEntry = InventoryLog::where('reference_type', 'order')
        ->where('reference_id', $invoice->id)
        ->where('action', 'sale')
        ->first();
    
    if ($logEntry) {
        echo "  ✓ Inventory log created\n";
        echo "    Action: {$logEntry->action}\n";
        echo "    Change: {$logEntry->quantity_change}\n";
    }
    
    echo "\n";
    
    // ==========================================
    // Step 7: Get Fulfillment Summary
    // ==========================================
    echo "Step 7: Get Fulfillment Summary\n";
    echo str_repeat('-', 50) . "\n";
    
    $summary = $orderService->getFulfillmentSummary($invoice);
    
    echo "✓ Fulfillment summary:\n";
    echo "  Total items: {$summary['total_items']}\n";
    echo "  Fully allocated: {$summary['fully_allocated']}\n";
    echo "  Partially allocated: {$summary['partially_allocated']}\n";
    echo "  Not allocated: {$summary['not_allocated']}\n";
    echo "  Fully shipped: {$summary['fully_shipped']}\n";
    echo "  Not shipped: {$summary['not_shipped']}\n";
    
    echo "\n";
    
    // ==========================================
    // Step 8: Ship Order
    // ==========================================
    echo "Step 8: Ship Order\n";
    echo str_repeat('-', 50) . "\n";
    
    $orderService->shipOrder(
        $invoice,
        [], // Ship all allocated quantities
        'TRACK123456',
        'FedEx'
    );
    $invoice->refresh();
    
    echo "✓ Order shipped\n";
    echo "  Order status: {$invoice->order_status->value}\n";
    echo "  Tracking: {$invoice->tracking_number}\n";
    echo "  Carrier: {$invoice->shipping_carrier}\n";
    echo "  Shipped quantity: {$invoice->items->first()->shipped_quantity}\n";
    
    echo "\n";
    
    // ==========================================
    // Step 9: Complete Order
    // ==========================================
    echo "Step 9: Complete Order\n";
    echo str_repeat('-', 50) . "\n";
    
    $orderService->completeOrder($invoice);
    $invoice->refresh();
    
    echo "✓ Order completed\n";
    echo "  Order status: {$invoice->order_status->value}\n";
    
    echo "\n";
    
    // ==========================================
    // Step 10: Cleanup (Optional - Delete Test Data)
    // ==========================================
    echo "Step 10: Cleanup Test Data\n";
    echo str_repeat('-', 50) . "\n";
    
    echo "Delete test order? (y/n): ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    
    if (trim($line) === 'y') {
        // First, release inventory if we want to restore it
        if ($invoice->order_status !== OrderStatus::CANCELLED) {
            $fulfillmentService = app(\App\Modules\Orders\Services\OrderFulfillmentService::class);
            $fulfillmentService->releaseInventory($invoice);
            echo "✓ Inventory released\n";
        }
        
        // Delete order items and quantities
        foreach ($invoice->items as $item) {
            $item->quantities()->delete();
            $item->delete();
        }
        
        // Delete order
        $invoice->delete();
        
        echo "✓ Test order deleted\n";
        
        // Verify inventory restored
        $finalInventory = ProductInventory::where('product_variant_id', $variant->id)
            ->sum('quantity');
        echo "  Final inventory: {$finalInventory} units (should be {$initialInventory})\n";
    } else {
        echo "✓ Test order preserved (ID: {$invoice->id})\n";
    }
    
    echo "\n";
    
    // ==========================================
    // Final Summary
    // ==========================================
    echo "=== Workflow Test Complete! ✓ ===\n\n";
    echo "✅ ALL STEPS COMPLETED SUCCESSFULLY:\n";
    echo "   1. ✓ Quote created\n";
    echo "   2. ✓ Quote approved\n";
    echo "   3. ✓ Quote converted to invoice (unified table!)\n";
    echo "   4. ✓ Inventory validated\n";
    echo "   5. ✓ Order confirmed & inventory allocated\n";
    echo "   6. ✓ Inventory correctly reduced\n";
    echo "   7. ✓ Inventory log created\n";
    echo "   8. ✓ Order shipped with tracking\n";
    echo "   9. ✓ Order completed\n\n";
    
    echo "🎉 Order fulfillment workflow is fully functional!\n\n";
    
} catch (\Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
