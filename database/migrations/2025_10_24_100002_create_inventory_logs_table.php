<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Inventory Logs - Audit trail for all inventory changes
     */
    public function up(): void
    {
        Schema::create('inventory_logs', function (Blueprint $table) {
            $table->id();
            
            // Warehouse & Item References
            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->unsignedBigInteger('add_on_id')->nullable();
            
            // Action & Changes
            $table->string('action', 50)->comment('adjustment, transfer_in, transfer_out, sale, return, import, consignment_return');
            $table->integer('quantity_before')->nullable()->comment('Quantity before the action');
            $table->integer('quantity_after')->nullable()->comment('Quantity after the action');
            $table->integer('quantity_change')->comment('Positive or negative change amount');
            
            // ETA changes (optional)
            $table->string('eta_before', 15)->nullable();
            $table->string('eta_after', 15)->nullable();
            $table->integer('eta_qty_before')->nullable();
            $table->integer('eta_qty_after')->nullable();
            
            // Reference Data
            $table->string('reference_type', 100)->nullable()->comment('order, manual, transfer, import, consignment');
            $table->unsignedBigInteger('reference_id')->nullable()->comment('ID of related record (order_id, transfer_id, etc)');
            $table->text('notes')->nullable()->comment('Additional notes or reason for change');
            
            // User tracking
            $table->unsignedBigInteger('user_id')->nullable();
            
            $table->timestamp('created_at')->useCurrent();
            
            // Foreign Keys
            $table->foreign('warehouse_id')
                ->references('id')
                ->on('warehouses')
                ->onDelete('cascade');
            
            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->onDelete('cascade');
            
            $table->foreign('product_variant_id')
                ->references('id')
                ->on('product_variants')
                ->onDelete('cascade');
            
            $table->foreign('add_on_id')
                ->references('id')
                ->on('addons')
                ->onDelete('cascade');
            
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
            
            // Indexes
            $table->index('warehouse_id', 'idx_inventory_logs_warehouse');
            $table->index('product_id', 'idx_inventory_logs_product');
            $table->index('product_variant_id', 'idx_inventory_logs_variant');
            $table->index('add_on_id', 'idx_inventory_logs_addon');
            $table->index('action', 'idx_inventory_logs_action');
            $table->index('created_at', 'idx_inventory_logs_created_at');
            $table->index(['reference_type', 'reference_id'], 'idx_inventory_logs_reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_logs');
    }
};
