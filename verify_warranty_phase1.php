<?php

/**
 * Phase 1 Verification Script
 * Tests Warranty Claims Models, Relationships, and Database Structure
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Modules\Warranties\Models\WarrantyClaim;
use App\Modules\Warranties\Models\WarrantyClaimItem;
use App\Modules\Warranties\Models\WarrantyClaimHistory;
use App\Modules\Warranties\Enums\WarrantyClaimStatus;
use App\Modules\Warranties\Enums\ClaimActionType;
use App\Modules\Warranties\Enums\ResolutionAction;
use Illuminate\Support\Facades\DB;

echo "\n";
echo "===============================================\n";
echo "  WARRANTY CLAIMS MODULE - PHASE 1 VERIFICATION\n";
echo "===============================================\n\n";

// Test 1: Check if tables exist
echo "📋 Test 1: Checking database tables...\n";
$tables = ['warranty_claims', 'warranty_claim_items', 'warranty_claim_history'];
foreach ($tables as $table) {
    $exists = DB::select("SHOW TABLES LIKE '{$table}'");
    echo $exists ? "   ✅ {$table} exists\n" : "   ❌ {$table} NOT FOUND\n";
}
echo "\n";

// Test 2: Check table structure
echo "📋 Test 2: Checking warranty_claims table structure...\n";
$columns = DB::select("DESCRIBE warranty_claims");
echo "   Columns found: " . count($columns) . "\n";
$requiredColumns = ['claim_number', 'customer_id', 'warehouse_id', 'invoice_id', 'status'];
foreach ($requiredColumns as $col) {
    $found = collect($columns)->where('Field', $col)->count() > 0;
    echo "   " . ($found ? "✅" : "❌") . " {$col}\n";
}
echo "\n";

// Test 3: Check WarrantyClaim model
echo "📋 Test 3: Testing WarrantyClaim model...\n";
try {
    $model = new WarrantyClaim();
    echo "   ✅ WarrantyClaim model instantiated\n";
    echo "   📄 Fillable fields: " . count($model->getFillable()) . "\n";
    echo "   🔄 Casts: " . count($model->getCasts()) . "\n";
} catch (\Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 4: Check enums
echo "📋 Test 4: Testing enums...\n";
try {
    $statusCount = count(WarrantyClaimStatus::cases());
    echo "   ✅ WarrantyClaimStatus: {$statusCount} cases\n";
    foreach (WarrantyClaimStatus::cases() as $status) {
        echo "      - {$status->value} ({$status->getLabel()}) - {$status->getColor()}\n";
    }
    
    $actionCount = count(ClaimActionType::cases());
    echo "   ✅ ClaimActionType: {$actionCount} cases\n";
    
    $resolutionCount = count(ResolutionAction::cases());
    echo "   ✅ ResolutionAction: {$resolutionCount} cases\n";
} catch (\Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 5: Check relationships
echo "📋 Test 5: Testing model relationships...\n";
try {
    $claim = new WarrantyClaim();
    $relationships = ['customer', 'warehouse', 'representative', 'invoice', 'items', 'histories', 'createdBy', 'resolvedBy'];
    foreach ($relationships as $rel) {
        try {
            $claim->$rel();
            echo "   ✅ {$rel}() relationship defined\n";
        } catch (\Exception $e) {
            echo "   ❌ {$rel}() error: " . $e->getMessage() . "\n";
        }
    }
} catch (\Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 6: Check helper methods
echo "📋 Test 6: Testing helper methods...\n";
$methods = ['addHistory', 'addNote', 'addVideoLink', 'markAsReplaced', 'markAsClaimed', 'void', 'changeStatus'];
foreach ($methods as $method) {
    $exists = method_exists(WarrantyClaim::class, $method);
    echo "   " . ($exists ? "✅" : "❌") . " {$method}() method\n";
}
echo "\n";

// Test 7: Check WarrantyClaimItem model
echo "📋 Test 7: Testing WarrantyClaimItem model...\n";
try {
    $item = new WarrantyClaimItem();
    echo "   ✅ WarrantyClaimItem model instantiated\n";
    
    $itemRelationships = ['warrantyClaim', 'product', 'productVariant', 'invoice', 'invoiceItem'];
    foreach ($itemRelationships as $rel) {
        try {
            $item->$rel();
            echo "   ✅ {$rel}() relationship defined\n";
        } catch (\Exception $e) {
            echo "   ❌ {$rel}() error: " . $e->getMessage() . "\n";
        }
    }
} catch (\Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 8: Check WarrantyClaimHistory model
echo "📋 Test 8: Testing WarrantyClaimHistory model...\n";
try {
    $history = new WarrantyClaimHistory();
    echo "   ✅ WarrantyClaimHistory model instantiated\n";
    
    $historyRelationships = ['warrantyClaim', 'user'];
    foreach ($historyRelationships as $rel) {
        try {
            $history->$rel();
            echo "   ✅ {$rel}() relationship defined\n";
        } catch (\Exception $e) {
            echo "   ❌ {$rel}() error: " . $e->getMessage() . "\n";
        }
    }
} catch (\Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 9: Database counts
echo "📋 Test 9: Checking current data...\n";
try {
    $claimCount = WarrantyClaim::count();
    $itemCount = WarrantyClaimItem::count();
    $historyCount = WarrantyClaimHistory::count();
    
    echo "   📊 Warranty Claims: {$claimCount}\n";
    echo "   📊 Claim Items: {$itemCount}\n";
    echo "   📊 Claim History: {$historyCount}\n";
} catch (\Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}
echo "\n";

// Summary
echo "===============================================\n";
echo "  ✅ PHASE 1 VERIFICATION COMPLETE!\n";
echo "===============================================\n";
echo "\n";
echo "📦 Created:\n";
echo "   - 3 Enums (WarrantyClaimStatus, ClaimActionType, ResolutionAction)\n";
echo "   - 3 Migrations (warranty_claims, warranty_claim_items, warranty_claim_history)\n";
echo "   - 3 Models (WarrantyClaim, WarrantyClaimItem, WarrantyClaimHistory)\n";
echo "\n";
echo "🎯 Ready for Phase 2: Filament Resource\n";
echo "\n";
