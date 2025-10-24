<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * ORDER ITEM QUANTITIES - Tracks which warehouse provides inventory for each order item
     * Supports multi-warehouse fulfillment
     */
    public function up(): void
    {
        Schema::create('order_item_quantities', function (Blueprint $table) {
            $table->id();
            
            // Parent order item reference
            $table->foreignId('order_item_id')->constrained('order_items')->onDelete('cascade');
            
            // Warehouse allocation
            $table->foreignId('warehouse_id')->constrained('warehouses')->onDelete('cascade');
            
            // Quantity allocated from this warehouse
            $table->integer('quantity')->default(0);
            
            // Timestamps
            $table->timestamps();
            
            // Indexes
            $table->index('order_item_id');
            $table->index('warehouse_id');
            
            // Unique constraint: One allocation per item per warehouse
            $table->unique(['order_item_id', 'warehouse_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_item_quantities');
    }
};
