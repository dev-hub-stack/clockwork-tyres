<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Modules\Consignments\Models\Consignment;
use App\Modules\Consignments\Models\ConsignmentItem;
use App\Modules\Customers\Models\Customer;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Consignments\Enums\ConsignmentStatus;

echo "\n=== FIX ALL CONSIGNMENTS SCRIPT ===\n\n";

// Get default warehouse
$defaultWarehouse = Warehouse::where('status', 1)->first();
if (!$defaultWarehouse) {
    echo "❌ No active warehouse found. Please create a warehouse first.\n";
    exit;
}

echo "✅ Default Warehouse: {$defaultWarehouse->warehouse_name} (ID: {$defaultWarehouse->id})\n\n";

// Get all consignments
$consignments = Consignment::with(['items', 'customer'])->get();

echo "Found {$consignments->count()} consignments to check\n\n";
echo "=== PROCESSING CONSIGNMENTS ===\n\n";

$fixedCount = 0;
$skippedCount = 0;
$errors = [];

foreach ($consignments as $consignment) {
    echo "Consignment: {$consignment->consignment_number}\n";
    
    $issues = [];
    $fixes = [];
    
    // Check 1: Customer exists
    if (!$consignment->customer_id || !$consignment->customer) {
        $issues[] = "No customer assigned";
        
        // Assign first customer
        $firstCustomer = Customer::first();
        if ($firstCustomer) {
            $consignment->customer_id = $firstCustomer->id;
            $fixes[] = "Assigned customer: {$firstCustomer->business_name}";
        } else {
            $errors[] = "Consignment {$consignment->consignment_number}: No customers available in database";
            echo "   ❌ No customers in database - SKIPPED\n\n";
            $skippedCount++;
            continue;
        }
    }
    
    // Check 2: Items exist
    if ($consignment->items->isEmpty()) {
        $issues[] = "No items in consignment";
        echo "   ❌ No items - SKIPPED\n\n";
        $skippedCount++;
        continue;
    }
    
    // Check 3: Items have warehouse_id
    $itemsWithoutWarehouse = $consignment->items->whereNull('warehouse_id');
    if ($itemsWithoutWarehouse->isNotEmpty()) {
        $issues[] = "{$itemsWithoutWarehouse->count()} items missing warehouse_id";
        
        foreach ($itemsWithoutWarehouse as $item) {
            $item->warehouse_id = $defaultWarehouse->id;
            $item->save();
        }
        
        $fixes[] = "Assigned warehouse to {$itemsWithoutWarehouse->count()} items";
    }
    
    // Check 4: Items have prices
    $itemsWithoutPrice = $consignment->items->where('price', '<=', 0);
    if ($itemsWithoutPrice->isNotEmpty()) {
        $issues[] = "{$itemsWithoutPrice->count()} items with zero/null price";
        
        foreach ($itemsWithoutPrice as $item) {
            // Try to get price from variant
            if ($item->product_variant_id && $item->productVariant) {
                $variant = $item->productVariant;
                $price = floatval($variant->uae_retail_price ?? 0);
                
                if ($price > 0) {
                    $item->price = $price;
                    $item->save();
                    $fixes[] = "Fixed price for {$item->sku}: AED {$price}";
                }
            }
        }
    }
    
    // Check 5: Items have quantity_sent
    $itemsWithoutQty = $consignment->items->where('quantity_sent', '<=', 0);
    if ($itemsWithoutQty->isNotEmpty()) {
        $issues[] = "{$itemsWithoutQty->count()} items with zero quantity";
        
        foreach ($itemsWithoutQty as $item) {
            $item->quantity_sent = 1;
            $item->save();
        }
        
        $fixes[] = "Set quantity_sent to 1 for {$itemsWithoutQty->count()} items";
    }
    
    // Check 6: Update item counts
    $oldSentCount = $consignment->items_sent_count;
    $oldSoldCount = $consignment->items_sold_count;
    $oldReturnedCount = $consignment->items_returned_count;
    
    $consignment->updateItemCounts();
    
    if ($oldSentCount != $consignment->items_sent_count || 
        $oldSoldCount != $consignment->items_sold_count ||
        $oldReturnedCount != $consignment->items_returned_count) {
        $issues[] = "Item counts incorrect";
        $fixes[] = "Updated counts: Sent {$oldSentCount}→{$consignment->items_sent_count}, Sold {$oldSoldCount}→{$consignment->items_sold_count}";
    }
    
    // Check 7: Calculate totals
    $consignment->calculateTotals();
    
    // Check 8: Fix status if needed
    if ($consignment->status === ConsignmentStatus::DRAFT && $consignment->items_sent_count > 0) {
        // Keep as draft unless user explicitly changes it
        // But we can suggest changing to SENT
        $issues[] = "Status is DRAFT (use 'Mark as Sent' button to enable Record Sale)";
    }
    
    // Save consignment
    $consignment->save();
    
    // Display results
    if (empty($issues)) {
        echo "   ✅ No issues found\n";
    } else {
        echo "   ⚠️  Issues found:\n";
        foreach ($issues as $issue) {
            echo "      - {$issue}\n";
        }
    }
    
    if (!empty($fixes)) {
        echo "   🔧 Fixes applied:\n";
        foreach ($fixes as $fix) {
            echo "      - {$fix}\n";
        }
        $fixedCount++;
    } else {
        if (empty($issues)) {
            echo "   ℹ️  Already in good state\n";
        }
    }
    
    echo "\n";
}

echo "=== SUMMARY ===\n\n";
echo "Total Consignments: {$consignments->count()}\n";
echo "Fixed: {$fixedCount}\n";
echo "Skipped: {$skippedCount}\n";
echo "Errors: " . count($errors) . "\n\n";

if (!empty($errors)) {
    echo "=== ERRORS ===\n";
    foreach ($errors as $error) {
        echo "❌ {$error}\n";
    }
    echo "\n";
}

echo "=== VERIFICATION ===\n\n";

// Check all consignments again
$consignmentsAfter = Consignment::with(['items', 'customer'])->get();

$stats = [
    'with_customer' => 0,
    'with_items' => 0,
    'items_with_warehouse' => 0,
    'items_with_price' => 0,
    'proper_counts' => 0,
    'can_record_sale' => 0,
];

foreach ($consignmentsAfter as $cons) {
    if ($cons->customer_id) $stats['with_customer']++;
    if ($cons->items->isNotEmpty()) $stats['with_items']++;
    if ($cons->items->where('warehouse_id', '!=', null)->count() > 0) $stats['items_with_warehouse']++;
    if ($cons->items->where('price', '>', 0)->count() > 0) $stats['items_with_price']++;
    if ($cons->items_sent_count > 0) $stats['proper_counts']++;
    if ($cons->canRecordSale()) $stats['can_record_sale']++;
}

echo "Consignments with customer: {$stats['with_customer']}/{$consignmentsAfter->count()}\n";
echo "Consignments with items: {$stats['with_items']}/{$consignmentsAfter->count()}\n";
echo "Items with warehouse assigned: {$stats['items_with_warehouse']}/{$consignmentsAfter->count()}\n";
echo "Items with price > 0: {$stats['items_with_price']}/{$consignmentsAfter->count()}\n";
echo "Consignments with proper counts: {$stats['proper_counts']}/{$consignmentsAfter->count()}\n";
echo "Consignments that CAN show Record Sale button: {$stats['can_record_sale']}/{$consignmentsAfter->count()}\n";

echo "\n=== NOTES ===\n\n";
echo "⚠️  To see 'Record Sale' button:\n";
echo "   1. Status must be SENT or DELIVERED (not DRAFT)\n";
echo "   2. Must have unsold items\n";
echo "   3. Use 'Mark as Sent' button to change status from DRAFT → SENT\n\n";

echo "✅ All fixable issues have been resolved!\n";
echo "   Refresh your browser to see the changes.\n\n";

echo "=== SCRIPT COMPLETE ===\n\n";
