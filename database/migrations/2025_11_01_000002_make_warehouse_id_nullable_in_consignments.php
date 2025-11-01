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
        Schema::table('consignments', function (Blueprint $table) {
            // Make warehouse_id nullable since each item now has its own warehouse
            // Drop foreign key first
            $table->dropForeign(['warehouse_id']);
            
            // Modify column to be nullable
            $table->foreignId('warehouse_id')
                ->nullable()
                ->change()
                ->constrained('warehouses')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('consignments', function (Blueprint $table) {
            // Drop foreign key
            $table->dropForeign(['warehouse_id']);
            
            // Make warehouse_id required again
            $table->foreignId('warehouse_id')
                ->nullable(false)
                ->change()
                ->constrained('warehouses')
                ->onDelete('cascade');
        });
    }
};
