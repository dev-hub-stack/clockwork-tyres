<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tyre_damaged_inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tyre_account_offer_id')->constrained('tyre_account_offers')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->integer('quantity');
            $table->string('condition')->comment('damaged, defective');
            $table->text('notes')->nullable();
            $table->foreignId('consignment_id')->nullable()->constrained('consignments')->nullOnDelete();
            $table->timestamps();

            $table->index(['tyre_account_offer_id', 'warehouse_id'], 'idx_tyre_damaged_offer_wh');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tyre_damaged_inventories');
    }
};
