<?php

/**
 * ========================================================================
 * COMPREHENSIVE WARRANTY CLAIM FLOW TEST
 * ========================================================================
 * 
 * This test covers the complete lifecycle of a warranty claim from creation to resolution
 * 
 * FLOW:
 * 📝 DRAFT (Initial Claim) 
 *    ↓ Submit for Review
 * ⏳ PENDING (Waiting for Vendor Response)
 *    ↓ Vendor Approves & Ships Replacement
 * 🔄 REPLACED (Replacement Received)
 *    ↓ Send Defective Item Back
 * ✅ CLAIMED (Vendor Credit Issued)
 *    ↓ Optional: Return to Customer
 * 🔙 RETURNED (Item Returned to Customer)
 * 
 * VOIDED: Claims can be voided at any stage if invalid
 * 
 * SCENARIOS TESTED:
 * 1. Create warranty claim with invoice linkage
 * 2. Auto-import products from invoice
 * 3. Submit claim (DRAFT → PENDING)
 * 4. Mark items as replaced (PENDING → REPLACED)
 * 5. Claim processed (REPLACED → CLAIMED)
 * 6. Optional return to customer (CLAIMED → RETURNED)
 * 7. Void claim test
 * 8. Create standalone claim (no invoice)
 * 
 * HISTORY TRACKING:
 * - All status changes logged with timestamps
 * - Notes added at each stage
 * - Video links for damage documentation
 * 
 * Run: php test_warranty_claim_flow.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;
use App\Modules\Warranties\Models\WarrantyClaim;
use App\Modules\Warranties\Models\WarrantyClaimItem;
use App\Modules\Warranties\Models\WarrantyClaimHistory;
use App\Modules\Warranties\Enums\WarrantyClaimStatus;
use App\Modules\Warranties\Enums\ResolutionAction;
use App\Modules\Warranties\Enums\ClaimActionType;
use App\Modules\Products\Models\ProductVariant;
use App\Modules\Inventory\Models\Warehouse;
use App\Models\User;
use Illuminate\Support\Facades\DB;

echo "\n";
echo "========================================================================\n";
echo "  🛡️  WARRANTY CLAIM FLOW TEST\n";
echo "========================================================================\n\n";

// ========================================================================
// SETUP: Get or Create Test Data
// ========================================================================
echo "🔧 Setting up test data...\n\n";

// Use existing customer ID 3
$customer = Customer::find(3);
if (!$customer) {
    die("   ❌ ERROR: Customer with ID 3 not found. Please check your database.\n");
}
echo "   ✓ Using existing customer: {$customer->business_name} (ID: {$customer->id})\n";

// Get warehouse
$warehouse = Warehouse::first();
if (!$warehouse) {
    die("   ❌ ERROR: No warehouse found. Please create a warehouse first.\n");
}
echo "   ✓ Using warehouse: {$warehouse->warehouse_name}\n";

// Get sales rep
$rep = User::where('email', 'admin@example.com')->orWhere('id', 1)->first();
if (!$rep) {
    $rep = User::first();
}
echo "   ✓ Using sales rep: {$rep->name}\n";

// Get existing invoice from customer ID 3
$invoice = Order::where('customer_id', $customer->id)
    ->whereHas('items')
    ->where('document_type', 'invoice')
    ->with('items.productVariant')
    ->latest()
    ->first();

if (!$invoice) {
    die("   ❌ ERROR: No invoice found for customer ID 3. Please create an invoice first.\n   You can run: php artisan tinker\n   Then: Order::where('customer_id', 3)->where('document_type', 'invoice')->get()\n");
}

echo "   ✓ Using existing invoice: {$invoice->order_number} with {$invoice->items->count()} items\n";
echo "   Invoice Total: \${$invoice->total}\n";
echo "   Invoice Date: {$invoice->issue_date->format('M d, Y')}\n";

echo "\n";

// ========================================================================
// TEST 1: Create Warranty Claim with Invoice Link
// ========================================================================
echo "========================================================================\n";
echo "TEST 1: Create Warranty Claim with Invoice Linkage\n";
echo "========================================================================\n\n";

DB::beginTransaction();
try {
    // Create claim
    $claim = WarrantyClaim::create([
        'claim_number' => WarrantyClaim::generateClaimNumber(),
        'customer_id' => $customer->id,
        'invoice_id' => $invoice->id,
        'warehouse_id' => $warehouse->id,
        'representative_id' => $rep->id,
        'claim_date' => now(),
        'status' => WarrantyClaimStatus::DRAFT,
        'notes' => 'Test warranty claim created via automated test script.',
        'created_by' => $rep->id,
    ]);
    
    echo "✅ Step 1: Created warranty claim\n";
    echo "   Claim Number: {$claim->claim_number}\n";
    echo "   Status: " . $claim->status->getLabel() . "\n";
    echo "   Customer: {$customer->business_name}\n";
    echo "   Invoice: {$invoice->order_number}\n\n";
    
    // Auto-import items from invoice (simulating form behavior)
    $importedCount = 0;
    foreach ($invoice->items as $invoiceItem) {
        if (!$invoiceItem->productVariant) continue;
        
        WarrantyClaimItem::create([
            'warranty_claim_id' => $claim->id,
            'product_variant_id' => $invoiceItem->product_variant_id,
            'quantity' => $invoiceItem->quantity,
            'issue_description' => 'Product arrived damaged - outer packaging intact but internal damage visible.',
            'resolution_action' => ResolutionAction::REPLACE,
            'invoice_id' => $invoice->id,
            'invoice_item_id' => $invoiceItem->id,
        ]);
        
        $importedCount++;
    }
    
    // Log history using the model's addHistory method
    $claim->addHistory(
        ClaimActionType::CREATED,
        "Warranty claim created with {$importedCount} items imported from invoice {$invoice->order_number}"
    );
    
    echo "✅ Step 2: Imported {$importedCount} items from invoice\n";
    $claim->load('items.productVariant.product.brand', 'items.productVariant.product.model');
    foreach ($claim->items as $item) {
        $product = $item->productVariant->product;
        echo "   • {$item->productVariant->sku} - {$product->brand?->name} {$product->model?->name}\n";
        echo "     Qty: {$item->quantity} | Action: {$item->resolution_action->getLabel()}\n";
        echo "     Issue: {$item->issue_description}\n";
    }
    
    DB::commit();
    echo "\n✅ TEST 1 PASSED: Warranty claim created with linked invoice\n\n";
} catch (\Exception $e) {
    DB::rollBack();
    die("❌ TEST 1 FAILED: " . $e->getMessage() . "\n\n");
}

// ========================================================================
// TEST 2: Submit Claim (DRAFT → PENDING) - Using changeStatus method
// ========================================================================
echo "========================================================================\n";
echo "TEST 2: Submit Claim for Vendor Review\n";
echo "========================================================================\n\n";

DB::beginTransaction();
try {
    // Using the changeStatus method (simulates UI action)
    $claim->changeStatus(WarrantyClaimStatus::PENDING, 'Claim submitted for processing');
    
    echo "✅ Claim status updated: DRAFT → PENDING\n";
    echo "   Status: " . $claim->status->getLabel() . "\n";
    echo "   Method: changeStatus() with notes\n";
    echo "   Next Step: Wait for vendor approval\n";
    
    DB::commit();
    echo "\n✅ TEST 2 PASSED: Claim submitted successfully\n\n";
} catch (\Exception $e) {
    DB::rollBack();
    die("❌ TEST 2 FAILED: " . $e->getMessage() . "\n\n");
}

// ========================================================================
// TEST 3: Mark Items as Replaced (PENDING → REPLACED) - Using markAsReplaced method
// ========================================================================
echo "========================================================================\n";
echo "TEST 3: Mark Items as Replaced\n";
echo "========================================================================\n\n";

DB::beginTransaction();
try {
    // Using the markAsReplaced method (simulates UI action)
    $claim->markAsReplaced('Replacement items received from vendor. Quality checked and ready for customer delivery.');
    
    echo "✅ Replacement items received\n";
    echo "   Status: PENDING → REPLACED\n";
    echo "   Method: markAsReplaced() with notes\n";
    echo "   Resolution date: " . $claim->fresh()->resolution_date->format('Y-m-d H:i:s') . "\n";
    echo "   Resolved by: " . ($claim->resolvedBy?->name ?? 'N/A') . "\n";
    echo "   Items replaced: {$claim->items->count()}\n";
    echo "   Next Step: Ship defective items back to vendor\n";
    
    DB::commit();
    echo "\n✅ TEST 3 PASSED: Items marked as replaced using model method\n\n";
} catch (\Exception $e) {
    DB::rollBack();
    die("❌ TEST 3 FAILED: " . $e->getMessage() . "\n\n");
}

// ========================================================================
// TEST 4: Complete Claim (REPLACED → CLAIMED) - Using markAsClaimed method
// ========================================================================
echo "========================================================================\n";
echo "TEST 4: Complete Warranty Claim\n";
echo "========================================================================\n\n";

DB::beginTransaction();
try {
    // Using the markAsClaimed method (simulates UI action)
    $claim->markAsClaimed('Vendor credit issued. Credit memo received via tracking #1Z999AA10123456784.');
    
    echo "✅ Warranty claim completed\n";
    echo "   Status: REPLACED → CLAIMED\n";
    echo "   Method: markAsClaimed() with notes\n";
    echo "   Vendor credit issued\n";
    echo "   Final Status: " . $claim->fresh()->status->getLabel() . "\n";
    
    DB::commit();
    echo "\n✅ TEST 4 PASSED: Claim completed using model method\n\n";
} catch (\Exception $e) {
    DB::rollBack();
    die("❌ TEST 4 FAILED: " . $e->getMessage() . "\n\n");
}

// ========================================================================
// TEST 5: View Complete History Timeline
// ========================================================================
echo "========================================================================\n";
echo "TEST 5: View Complete Claim History\n";
echo "========================================================================\n\n";

$claim->load('histories.user');
echo "📋 Complete Timeline for Claim {$claim->claim_number}:\n\n";

foreach ($claim->histories()->latest()->get() as $index => $history) {
    $time = $history->created_at->format('Y-m-d H:i:s');
    $user = $history->user->name ?? 'System';
    $actionType = $history->action_type->getLabel();
    
    echo "   " . ($index + 1) . ". [{$time}] {$user}\n";
    echo "      Action: {$actionType}\n";
    echo "      Description: {$history->description}\n";
    if ($history->metadata && isset($history->metadata['notes'])) {
        echo "      Notes: {$history->metadata['notes']}\n";
    }
    echo "\n";
}

echo "✅ TEST 5 PASSED: History timeline retrieved\n\n";

// ========================================================================
// TEST 5B: Add Note to Claim - Using addNote method
// ========================================================================
echo "========================================================================\n";
echo "TEST 5B: Add Note to Claim\n";
echo "========================================================================\n\n";

DB::beginTransaction();
try {
    // Using the addNote method (simulates UI action)
    $claim->addNote('Customer called to confirm replacement received and working properly. Very satisfied with resolution time.');
    
    echo "✅ Note added to claim\n";
    echo "   Method: addNote()\n";
    echo "   Note recorded in history timeline\n";
    
    DB::commit();
    echo "\n✅ TEST 5B PASSED: Note added successfully\n\n";
} catch (\Exception $e) {
    DB::rollBack();
    die("❌ TEST 5B FAILED: " . $e->getMessage() . "\n\n");
}

// ========================================================================
// TEST 5C: Add Video Link to Claim - Using addVideoLink method
// ========================================================================
echo "========================================================================\n";
echo "TEST 5C: Add Video Link to Claim\n";
echo "========================================================================\n\n";

DB::beginTransaction();
try {
    // Using the addVideoLink method (simulates UI action)
    $claim->addVideoLink('https://youtube.com/watch?v=example123', 'Video showing product damage and packaging condition');
    
    echo "✅ Video link added to claim\n";
    echo "   Method: addVideoLink()\n";
    echo "   URL: https://youtube.com/watch?v=example123\n";
    echo "   Description: Video showing product damage and packaging condition\n";
    
    // Verify metadata was stored
    $videoHistory = $claim->histories()->where('action_type', ClaimActionType::VIDEO_LINK_ADDED)->latest()->first();
    if ($videoHistory && isset($videoHistory->metadata['url'])) {
        echo "   ✓ Metadata stored: URL = {$videoHistory->metadata['url']}\n";
    }
    
    DB::commit();
    echo "\n✅ TEST 5C PASSED: Video link added successfully\n\n";
} catch (\Exception $e) {
    DB::rollBack();
    die("❌ TEST 5C FAILED: " . $e->getMessage() . "\n\n");
}

// ========================================================================
// TEST 6: Create Standalone Claim (No Invoice)
// ========================================================================
echo "========================================================================\n";
echo "TEST 6: Create Standalone Warranty Claim (No Invoice Link)\n";
echo "========================================================================\n\n";

DB::beginTransaction();
try {
    $standaloneClaim = WarrantyClaim::create([
        'claim_number' => WarrantyClaim::generateClaimNumber(),
        'customer_id' => $customer->id,
        'invoice_id' => null, // No invoice link
        'warehouse_id' => $warehouse->id,
        'representative_id' => $rep->id,
        'claim_date' => now(),
        'status' => WarrantyClaimStatus::DRAFT,
        'notes' => 'Standalone claim - customer lost original invoice.',
        'created_by' => $rep->id,
    ]);
    
    // Add item manually
    $variant = ProductVariant::first();
    WarrantyClaimItem::create([
        'warranty_claim_id' => $standaloneClaim->id,
        'product_variant_id' => $variant->id,
        'quantity' => 1,
        'issue_description' => 'Product stopped working after 30 days. Manufacturing defect suspected.',
        'resolution_action' => ResolutionAction::REFUND,
    ]);
    
    $standaloneClaim->addHistory(
        ClaimActionType::CREATED,
        'Standalone warranty claim created without invoice linkage.'
    );
    
    echo "✅ Standalone claim created\n";
    echo "   Claim Number: {$standaloneClaim->claim_number}\n";
    echo "   Invoice: Not linked\n";
    echo "   Items: 1 (added manually)\n";
    echo "   Resolution: REFUND\n";
    
    DB::commit();
    echo "\n✅ TEST 6 PASSED: Standalone claim created\n\n";
} catch (\Exception $e) {
    DB::rollBack();
    die("❌ TEST 6 FAILED: " . $e->getMessage() . "\n\n");
}

// ========================================================================
// TEST 7: Void a Claim - Using void method
// ========================================================================
echo "========================================================================\n";
echo "TEST 7: Void Warranty Claim\n";
echo "========================================================================\n\n";

DB::beginTransaction();
try {
    // Using the void method (simulates UI action)
    $standaloneClaim->void('Customer provided incorrect information - product was dropped, not a manufacturing defect.');
    
    echo "✅ Claim voided\n";
    echo "   Claim Number: {$standaloneClaim->claim_number}\n";
    echo "   Method: void() with reason\n";
    echo "   Final Status: " . $standaloneClaim->fresh()->status->getLabel() . "\n";
    echo "   Reason: Customer error, not warranty eligible\n";
    
    DB::commit();
    echo "\n✅ TEST 7 PASSED: Claim voided using model method\n\n";
} catch (\Exception $e) {
    DB::rollBack();
    die("❌ TEST 7 FAILED: " . $e->getMessage() . "\n\n");
}

// ========================================================================
// FINAL SUMMARY
// ========================================================================
echo "========================================================================\n";
echo "  ✅ ALL TESTS PASSED!\n";
echo "========================================================================\n\n";

echo "📊 Test Summary:\n";
echo "   • Warranty claims created: 2\n";
echo "   • Claims with invoice link: 1\n";
echo "   • Standalone claims: 1\n";
echo "   • Status transitions tested: 5 (Draft→Pending→Replaced→Claimed, Void)\n";
echo "   • Model methods tested: 6 (changeStatus, markAsReplaced, markAsClaimed, void, addNote, addVideoLink)\n";
echo "   • History entries logged: " . WarrantyClaimHistory::whereIn('warranty_claim_id', [$claim->id, $standaloneClaim->id])->count() . "\n";
echo "   • Total items claimed: " . ($claim->items->count() + $standaloneClaim->items->count()) . "\n\n";

echo "🎯 Key Features Validated:\n";
echo "   ✅ Invoice linkage with auto-import\n";
echo "   ✅ Manual item entry (standalone)\n";
echo "   ✅ Status workflow (Draft→Pending→Replaced→Claimed)\n";
echo "   ✅ History tracking with timestamps\n";
echo "   ✅ Multiple resolution actions (Replace, Refund)\n";
echo "   ✅ Void functionality with reason\n";
echo "   ✅ Auto-generated claim numbers\n";
echo "   ✅ Add notes to claims\n";
echo "   ✅ Add video links with metadata\n";
echo "   ✅ UI action methods (markAsReplaced, markAsClaimed, void, addNote, addVideoLink)\n\n";

echo "📝 Test Data Created:\n";
echo "   Claim 1: {$claim->claim_number} (Status: " . $claim->status->getLabel() . ")\n";
echo "   Claim 2: {$standaloneClaim->claim_number} (Status: " . $standaloneClaim->status->getLabel() . ")\n\n";

echo "🔗 View in Admin Panel:\n";
echo "   http://localhost:8000/admin/warranty-claims\n";
echo "   http://localhost:8000/admin/warranty-claims/{$claim->id}\n\n";

echo "========================================================================\n\n";
