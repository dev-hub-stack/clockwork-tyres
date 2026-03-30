<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tyre_offer_inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tyre_account_offer_id')->constrained('tyre_account_offers')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(0);
            $table->string('eta', 15)->nullable();
            $table->unsignedInteger('eta_qty')->default(0);
            $table->timestamps();

            $table->unique(['tyre_account_offer_id', 'warehouse_id'], 'tyre_offer_inventory_offer_warehouse_unique');
            $table->index(['account_id', 'warehouse_id'], 'tyre_offer_inventory_account_warehouse_idx');
            $table->index('quantity', 'tyre_offer_inventory_quantity_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tyre_offer_inventories');
    }
};
