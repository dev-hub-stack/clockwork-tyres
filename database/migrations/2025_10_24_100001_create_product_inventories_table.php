<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * CRITICAL: Based on old Reporting system
     * - Supports Products, Product Variants, and AddOns (polymorphic)
     * - Includes eta_qty for inbound stock tracking
     * - ETA is VARCHAR for flexible date formats
     */
    public function up(): void
    {
        Schema::create('product_inventories', function (Blueprint $table) {
            $table->id();
            
            // Foreign Keys - One of these must be set (polymorphic relationship)
            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->unsignedBigInteger('add_on_id')->nullable();
            
            // Inventory Data
            $table->unsignedInteger('quantity')->default(0)->comment('Current stock quantity on hand');
            $table->string('eta', 15)->nullable()->comment('Expected arrival date - flexible format (2025-12-01, Q4 2025, Late Dec)');
            $table->unsignedInteger('eta_qty')->default(0)->comment('Quantity expected to arrive (inbound stock)');
            
            $table->timestamps();
            
            // Foreign Key Constraints
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
            
            // Indexes
            $table->index('warehouse_id', 'idx_product_inventories_warehouse');
            $table->index('product_id', 'idx_product_inventories_product');
            $table->index('product_variant_id', 'idx_product_inventories_variant');
            $table->index('add_on_id', 'idx_product_inventories_addon');
            $table->index('quantity', 'idx_product_inventories_quantity');
            
            // Note: Unique constraints are enforced at application level
            // MySQL doesn't support partial unique indexes with WHERE clause
            // Application logic will ensure one record per warehouse+item combination
        });
        
        // Add total_quantity to products table (for quick reference)
        if (!Schema::hasColumn('products', 'total_quantity')) {
            Schema::table('products', function (Blueprint $table) {
                $table->unsignedInteger('total_quantity')->default(0)->after('status');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_inventories');
        
        if (Schema::hasColumn('products', 'total_quantity')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('total_quantity');
            });
        }
    }
};
