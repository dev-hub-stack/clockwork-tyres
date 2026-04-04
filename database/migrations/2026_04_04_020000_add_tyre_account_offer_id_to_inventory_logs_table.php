<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_logs', function (Blueprint $table) {
            $table->foreignId('tyre_account_offer_id')
                ->nullable()
                ->after('product_variant_id')
                ->constrained('tyre_account_offers')
                ->nullOnDelete();

            $table->index('tyre_account_offer_id', 'idx_inventory_logs_tyre_offer');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_logs', function (Blueprint $table) {
            $table->dropIndex('idx_inventory_logs_tyre_offer');
            $table->dropConstrainedForeignId('tyre_account_offer_id');
        });
    }
};
