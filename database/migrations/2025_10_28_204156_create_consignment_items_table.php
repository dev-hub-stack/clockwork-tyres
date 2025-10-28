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
        Schema::create('consignment_items', function (Blueprint $table) {
            $table->id();
            
            // Relationships
            $table->foreignId('consignment_id')->constrained('consignments')->onDelete('cascade');
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->onDelete('set null');
            
            // Product Snapshot (JSONB for historical data)
            $table->json('product_snapshot')->nullable();
            
            // Denormalized Product Info (for quick access without parsing JSON)
            $table->string('product_name');
            $table->string('brand_name');
            $table->string('sku', 100);
            $table->text('description')->nullable();
            
            // Quantity Tracking
            $table->integer('quantity_sent')->default(0);
            $table->integer('quantity_sold')->default(0);
            $table->integer('quantity_returned')->default(0);
            
            // Pricing
            $table->decimal('price', 10, 2); // Original consignment price
            $table->decimal('actual_sale_price', 10, 2)->nullable(); // Actual price when sold
            
            // Status
            $table->string('status', 50)->default('sent');
            
            // Dates
            $table->timestamp('date_sold')->nullable();
            $table->timestamp('date_returned')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('consignment_id');
            $table->index('product_variant_id');
            $table->index('sku');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consignment_items');
    }
};