<?php

namespace App\Console\Commands;

use App\Modules\Consignments\Models\Consignment;
use App\Modules\Consignments\Models\ConsignmentHistory;
use App\Modules\Consignments\Models\ConsignmentItem;
use App\Modules\Inventory\Models\DamagedInventory;
use App\Modules\Orders\Enums\DocumentType;
use App\Modules\Orders\Enums\QuoteStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;
use App\Modules\Orders\Models\Payment;
use App\Modules\Warranties\Models\WarrantyClaim;
use App\Modules\Warranties\Models\WarrantyClaimHistory;
use App\Modules\Warranties\Models\WarrantyClaimItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CleanupHistoricalData extends Command
{
    protected $signature = 'cleanup:historical-data
                            {--dry-run : Show what would be done without making changes}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Data cleanup per George Varkey\'s instructions (2026-03-18):
    - Move 850 tunerstop_historical invoices → Quotes section
    - Delete consignment-sourced test invoices (25)
    - Delete zero-total test quotes
    - Clear all inventory movement logs
    - Clear all damaged inventory entries
    - Clear all consignment records
    - Clear all warranty claim records';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $this->info('');
        $this->info('=== Historical Data Cleanup ===');
        $this->info('Per George Varkey instructions, 2026-03-18');
        $this->info('');

        if ($dryRun) {
            $this->warn('[DRY RUN] No changes will be made.');
            $this->info('');
        }

        // --- Preview counts ---
        $tsHistorical     = Order::withTrashed()->where('document_type', 'invoice')->where('external_source', 'tunerstop_historical')->count();
        $consignmentInvs  = Order::withTrashed()->where('document_type', 'invoice')->where('external_source', 'consignment')->count();
        $testQuotes       = Order::withTrashed()->where('document_type', 'quote')->whereNull('external_source')->where(function ($q) {
            $q->where('total', 0)->orWhereNotNull('deleted_at');
        })->count();
        $invLogs          = DB::table('inventory_logs')->count();
        $damaged          = DamagedInventory::count();
        $consignments     = Consignment::withTrashed()->count();
        $warrantyClaims   = WarrantyClaim::withTrashed()->count();

        $this->table(['Action', 'Count'], [
            ['Move tunerstop_historical invoices → quotes', $tsHistorical],
            ['Delete consignment-sourced test invoices',    $consignmentInvs],
            ['Delete zero-total / soft-deleted test quotes', $testQuotes],
            ['Clear inventory movement logs',               $invLogs],
            ['Clear damaged inventory entries',             $damaged],
            ['Clear consignment records (+ items/history)', $consignments],
            ['Clear warranty claim records (+ items/history)', $warrantyClaims],
        ]);

        $this->info('');

        if (!$dryRun) {
            if (!$this->option('force')) {
                if (!$this->confirm('Proceed with cleanup? THIS CANNOT BE UNDONE.')) {
                    $this->warn('Aborted.');
                    return self::FAILURE;
                }
            }
            $this->performCleanup();
        }

        return self::SUCCESS;
    }

    private function performCleanup(): void
    {
        // ─────────────────────────────────────────────────────────────────
        // Part A: Transactional — order/quote reclassification & deletion
        // ─────────────────────────────────────────────────────────────────
        DB::transaction(function () {

            // ─────────────────────────────────────────────────────────────
            // 1. Move 850 tunerstop_historical invoices → Quotes section
            // ─────────────────────────────────────────────────────────────
            $this->info('[1/7] Converting tunerstop_historical invoices to quotes...');

            $moved = Order::withTrashed()
                ->where('document_type', 'invoice')
                ->where('external_source', 'tunerstop_historical')
                ->update([
                    'document_type' => DocumentType::QUOTE->value,
                    'quote_status'  => QuoteStatus::APPROVED->value,
                ]);

            $this->info("    ✓ Converted $moved records to document_type='quote'");
            Log::info("cleanup:historical-data — Converted $moved tunerstop_historical invoices to quotes");

            // ─────────────────────────────────────────────────────────────
            // 2. Delete warranty claim items/history first (FK references
            //    order_items — must go before we delete consignment invoices)
            // ─────────────────────────────────────────────────────────────
            $this->info('[2/7] Removing warranty claim data (FK pre-clear)...');
            DB::table('warranty_claim_history')->delete();
            DB::table('warranty_claim_items')->delete();
            DB::table('warranty_claims')->delete();
            $this->info('    ✓ Warranty claims cleared');
            Log::info('cleanup:historical-data — Cleared all warranty_claims');

            // ─────────────────────────────────────────────────────────────
            // 3. Delete consignment-sourced test invoices
            // ─────────────────────────────────────────────────────────────
            $this->info('[3/7] Deleting consignment-sourced test invoices...');

            $consignmentOrders = Order::withTrashed()
                ->where('document_type', 'invoice')
                ->where('external_source', 'consignment')
                ->pluck('id');

            if ($consignmentOrders->isNotEmpty()) {
                Payment::withTrashed()->whereIn('order_id', $consignmentOrders)->forceDelete();
                OrderItem::withoutGlobalScopes()->whereIn('order_id', $consignmentOrders)->forceDelete();
                $deleted = Order::withTrashed()->whereIn('id', $consignmentOrders)->forceDelete();
                $this->info("    ✓ Deleted $deleted consignment-sourced invoices (+ items, payments)");
                Log::info("cleanup:historical-data — Deleted {$consignmentOrders->count()} consignment invoices");
            } else {
                $this->info('    ✓ None found');
            }

            // ─────────────────────────────────────────────────────────────
            // 4. Delete zero-total test quotes
            // ─────────────────────────────────────────────────────────────
            $this->info('[4/7] Deleting zero-total / soft-deleted test quotes...');

            $testQuoteIds = Order::withTrashed()
                ->where('document_type', 'quote')
                ->whereNull('external_source')
                ->where(function ($q) {
                    $q->where('total', 0)->orWhereNotNull('deleted_at');
                })
                ->pluck('id');

            if ($testQuoteIds->isNotEmpty()) {
                Payment::withTrashed()->whereIn('order_id', $testQuoteIds)->forceDelete();
                OrderItem::withoutGlobalScopes()->whereIn('order_id', $testQuoteIds)->forceDelete();
                $deleted = Order::withTrashed()->whereIn('id', $testQuoteIds)->forceDelete();
                $this->info("    ✓ Deleted $deleted test quotes (+ items)");
                Log::info("cleanup:historical-data — Deleted {$testQuoteIds->count()} test quotes");
            } else {
                $this->info('    ✓ None found');
            }

            // ─────────────────────────────────────────────────────────────
            // 5. Clear consignment records (via DELETE — transactional)
            // ─────────────────────────────────────────────────────────────
            $this->info('[5/7] Clearing consignment records...');
            DB::table('consignment_histories')->delete();
            DB::table('consignment_items')->delete();
            DB::table('consignments')->delete();
            $this->info('    ✓ Cleared consignment_histories, consignment_items, consignments');
            Log::info('cleanup:historical-data — Cleared all consignments');
        });

        // ─────────────────────────────────────────────────────────────────
        // Part B: Non-transactional truncates (inventory tables, no FKs)
        //         Must run outside the transaction since MySQL auto-commits
        //         TRUNCATE. Use FK checks off for safety.
        // ─────────────────────────────────────────────────────────────────
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            // 6. Clear inventory movement logs
            $this->info('[6/7] Clearing inventory movement logs...');
            $logCount = DB::table('inventory_logs')->count();
            DB::table('inventory_logs')->truncate();
            $this->info("    ✓ Cleared $logCount inventory log entries");
            Log::info("cleanup:historical-data — Cleared $logCount inventory_logs entries");

            // 7. Clear damaged inventory entries
            $this->info('[7/7] Clearing damaged inventory entries...');
            $dmgCount = DamagedInventory::count();
            DB::table('damaged_inventories')->truncate();
            $this->info("    ✓ Cleared $dmgCount damaged_inventories entries");
            Log::info("cleanup:historical-data — Cleared $dmgCount damaged_inventories entries");
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $this->info('');
        $this->info('✓ Cleanup complete.');
        $this->info('');
        $this->info('Summary of what remains:');
        $this->info('  • ' . Order::where('document_type', 'invoice')->count() . ' invoices (manually created / real)');
        $this->info('  • ' . Order::where('document_type', 'quote')->count() . ' quotes (incl. tunerstop historical now visible here)');
        $this->info('  • 0 consignments, 0 warranty claims, 0 inventory logs, 0 damaged stock');
        $this->info('');
        $this->info('Next steps:');
        $this->info('  • Run [php artisan email:suppress off] when George confirms data entry is complete');
        $this->info('  • George can now begin entering real data from Jan 1, 2026');
    }
}
