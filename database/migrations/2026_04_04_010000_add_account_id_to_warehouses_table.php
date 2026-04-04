<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->foreignId('account_id')
                ->nullable()
                ->after('id')
                ->constrained('accounts')
                ->nullOnDelete();

            $table->index(['account_id', 'status'], 'idx_warehouses_account_status');
            $table->index(['account_id', 'is_primary'], 'idx_warehouses_account_primary');
        });

        $this->backfillAccountOwnership();
    }

    public function down(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropIndex('idx_warehouses_account_status');
            $table->dropIndex('idx_warehouses_account_primary');
            $table->dropConstrainedForeignId('account_id');
        });
    }

    private function backfillAccountOwnership(): void
    {
        $this->assignWarehousesFromTyreOfferInventories();
        $this->assignDemoWarehousesByKnownCodes();
        $this->assignRemainingWarehousesWhenSingleAccountExists();
    }

    private function assignWarehousesFromTyreOfferInventories(): void
    {
        $warehouseMappings = DB::table('tyre_offer_inventories')
            ->select(
                'warehouse_id',
                DB::raw('MIN(account_id) as account_id'),
                DB::raw('COUNT(DISTINCT account_id) as account_count')
            )
            ->whereNotNull('warehouse_id')
            ->whereNotNull('account_id')
            ->groupBy('warehouse_id')
            ->get();

        foreach ($warehouseMappings as $mapping) {
            if ((int) $mapping->account_count !== 1) {
                continue;
            }

            DB::table('warehouses')
                ->where('id', $mapping->warehouse_id)
                ->whereNull('account_id')
                ->update(['account_id' => $mapping->account_id]);
        }
    }

    private function assignDemoWarehousesByKnownCodes(): void
    {
        $demoAccountPatterns = [
            'clockwork-retail-demo' => [
                'codes' => ['DDT-%'],
                'names' => ['%Desert Drift%'],
            ],
            'clockwork-supply-demo' => [
                'codes' => ['NRT-%'],
                'names' => ['%Northern Rubber%'],
            ],
            'clockwork-shared-demo' => [
                'codes' => ['UFW-%'],
                'names' => ['%Urban Fleet%'],
            ],
        ];

        foreach ($demoAccountPatterns as $slug => $patterns) {
            $accountId = DB::table('accounts')->where('slug', $slug)->value('id');

            if (! $accountId) {
                continue;
            }

            DB::table('warehouses')
                ->whereNull('account_id')
                ->where(function ($query) use ($patterns) {
                    foreach ($patterns['codes'] as $codePattern) {
                        $query->orWhere('code', 'like', $codePattern);
                    }

                    foreach ($patterns['names'] as $namePattern) {
                        $query->orWhere('warehouse_name', 'like', $namePattern);
                    }
                })
                ->update(['account_id' => $accountId]);
        }
    }

    private function assignRemainingWarehousesWhenSingleAccountExists(): void
    {
        $accountIds = DB::table('accounts')->pluck('id');

        if ($accountIds->count() !== 1) {
            return;
        }

        DB::table('warehouses')
            ->whereNull('account_id')
            ->update(['account_id' => $accountIds->first()]);
    }
};
