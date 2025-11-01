<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('consignment_items', function (Blueprint $table) {
            // Add warehouse_id column to track which warehouse each item is sent from
            $table->foreignId('warehouse_id')
                ->nullable()
                ->after('product_variant_id')
                ->constrained('warehouses')
                ->onDelete('set null');
            
            // Add index for better query performance
            $table->index('warehouse_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('consignment_items', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->dropIndex(['warehouse_id']);
            $table->dropColumn('warehouse_id');
        });
    }
};
