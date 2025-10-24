<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * ORDER ITEMS - Line items for orders/quotes/invoices
     * CRITICAL: Uses JSONB snapshots to preserve product/variant/addon data at time of order
     */
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            
            // Parent order reference
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            
            // Product references (nullable for flexibility)
            $table->foreignId('product_id')->nullable()->constrained('products')->onDelete('set null');
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->onDelete('set null');
            $table->foreignId('add_on_id')->nullable()->constrained('addons')->onDelete('set null');
            
            // CRITICAL: JSONB Snapshots - preserve product/variant/addon data at time of order
            // These snapshots ensure historical accuracy even if products are modified/deleted later
            $table->json('product_snapshot')->nullable(); // Product data snapshot
            $table->json('variant_snapshot')->nullable(); // Variant specs snapshot
            $table->json('addon_snapshot')->nullable();   // Addon data snapshot
            
            // Denormalized fields for quick access (from snapshots)
            $table->string('sku', 100)->nullable();
            $table->string('product_name', 255);
            $table->text('product_description')->nullable();
            $table->string('brand_name', 100)->nullable();
            $table->string('model_name', 100)->nullable();
            
            // Pricing and quantity
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->boolean('tax_inclusive')->default(true); // Inherited from order or item-specific
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('line_total', 10, 2)->default(0); // Calculated: (unit_price * quantity) - discount + tax
            
            // Fulfillment tracking
            $table->integer('allocated_quantity')->default(0); // Quantity allocated from warehouses
            $table->integer('shipped_quantity')->default(0);   // Quantity actually shipped
            
            // Timestamps
            $table->timestamps();
            
            // Indexes
            $table->index('order_id');
            $table->index('product_id');
            $table->index('product_variant_id');
            $table->index('add_on_id');
            $table->index('sku');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
