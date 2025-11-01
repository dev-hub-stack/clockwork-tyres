<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Modules\Consignments\Models\Consignment;

echo "\n=== CONSIGNMENT RECORD SALE DIAGNOSTIC ===\n\n";

// Get the latest consignment
$consignment = Consignment::with(['items', 'customer'])
    ->orderBy('id', 'desc')
    ->first();

if (!$consignment) {
    echo "❌ No consignments found\n";
    exit;
}

echo "Checking Consignment: {$consignment->consignment_number}\n";
echo "Customer: " . ($consignment->customer->business_name ?? 'N/A') . "\n";
echo "Status: {$consignment->status->value}\n\n";

echo "=== BUTTON VISIBILITY CONDITIONS ===\n\n";

// Check status
echo "1. Status Check:\n";
echo "   Current Status: {$consignment->status->value}\n";
echo "   Status allows Record Sale: " . ($consignment->status->canRecordSale() ? '✅ YES' : '❌ NO') . "\n";
echo "   Allowed statuses: SENT, DELIVERED, PARTIALLY_SOLD, PARTIALLY_RETURNED\n\n";

// Check items count
echo "2. Items Count Check:\n";
echo "   Items Sent Count (from DB): {$consignment->items_sent_count}\n";
echo "   Items Sold Count (from DB): {$consignment->items_sold_count}\n";
echo "   Has Unsold Items: " . ($consignment->items_sold_count < $consignment->items_sent_count ? '✅ YES' : '❌ NO') . "\n\n";

// Calculate from actual items
$actualItemsSent = $consignment->items->sum('quantity_sent');
$actualItemsSold = $consignment->items->sum('quantity_sold');
$actualItemsReturned = $consignment->items->sum('quantity_returned');
$availableToSell = $actualItemsSent - $actualItemsSold - $actualItemsReturned;

echo "3. Actual Items (from consignment_items table):\n";
echo "   Total Items Sent: {$actualItemsSent}\n";
echo "   Total Items Sold: {$actualItemsSold}\n";
echo "   Total Items Returned: {$actualItemsReturned}\n";
echo "   Available to Sell: {$availableToSell}\n\n";

// Check canRecordSale method
echo "4. canRecordSale() Method Result:\n";
echo "   Result: " . ($consignment->canRecordSale() ? '✅ YES (Button should show)' : '❌ NO (Button hidden)') . "\n";
echo "   Formula: status->canRecordSale() && (items_sold_count < items_sent_count)\n";
echo "   Calculation: " . ($consignment->status->canRecordSale() ? 'true' : 'false') . 
     " && ({$consignment->items_sold_count} < {$consignment->items_sent_count}) = " . 
     ($consignment->canRecordSale() ? 'true' : 'false') . "\n\n";

// List items
echo "=== CONSIGNMENT ITEMS ===\n\n";
if ($consignment->items->isEmpty()) {
    echo "❌ NO ITEMS FOUND IN THIS CONSIGNMENT!\n";
    echo "   This is the problem - consignment has no items.\n\n";
} else {
    foreach ($consignment->items as $index => $item) {
        echo "Item #" . ($index + 1) . ":\n";
        echo "   SKU: {$item->sku}\n";
        echo "   Product: {$item->product_name}\n";
        echo "   Quantity Sent: {$item->quantity_sent}\n";
        echo "   Quantity Sold: {$item->quantity_sold}\n";
        echo "   Quantity Returned: {$item->quantity_returned}\n";
        echo "   Available to Sell: " . ($item->quantity_sent - $item->quantity_sold - $item->quantity_returned) . "\n";
        echo "   Price: AED " . number_format($item->price, 2) . "\n";
        echo "   Warehouse ID: " . ($item->warehouse_id ?? 'NULL') . "\n\n";
    }
}

echo "=== DIAGNOSIS ===\n\n";

if (!$consignment->canRecordSale()) {
    echo "🔴 PROBLEM IDENTIFIED:\n\n";
    
    if (!$consignment->status->canRecordSale()) {
        echo "   Issue: Status '{$consignment->status->value}' does not allow recording sales\n";
        echo "   Solution: Change status to: SENT or DELIVERED\n\n";
    }
    
    if ($consignment->items_sent_count == 0) {
        echo "   Issue: items_sent_count is 0\n";
        echo "   Solution: Run updateItemCounts() on the consignment\n\n";
        
        // Try to fix it
        echo "Attempting to fix counts...\n";
        $consignment->updateItemCounts();
        $consignment->save();
        echo "✅ Counts updated!\n";
        echo "   New items_sent_count: {$consignment->items_sent_count}\n";
        echo "   New items_sold_count: {$consignment->items_sold_count}\n\n";
        
        // Re-check
        if ($consignment->canRecordSale()) {
            echo "✅ FIXED! Record Sale button should now appear!\n";
        } else {
            echo "❌ Still not working. Check if items exist in the consignment.\n";
        }
    } elseif ($consignment->items_sold_count >= $consignment->items_sent_count) {
        echo "   Issue: All items have been sold (sold: {$consignment->items_sold_count}, sent: {$consignment->items_sent_count})\n";
        echo "   Solution: All items are already sold. Use 'Record Return' instead.\n\n";
    }
    
    if ($consignment->items->isEmpty()) {
        echo "   Issue: Consignment has no items\n";
        echo "   Solution: Add items to the consignment first\n\n";
    }
} else {
    echo "✅ ALL CHECKS PASSED!\n";
    echo "   The 'Record Sale' button SHOULD be visible.\n";
    echo "   If you don't see it, try refreshing the page.\n\n";
}

echo "=== TEST COMPLETE ===\n\n";
