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
        Schema::create('customer_addon_category_pricing', function (Blueprint $table) {
            $table->id();
            
            // Relations
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('add_on_category_id');
            
            // Discount Configuration
            $table->enum('discount_type', ['percentage', 'fixed'])->default('percentage');
            $table->decimal('discount_percentage', 5, 2)->default(0.00)->comment('0-100%');
            $table->decimal('discount_value', 10, 2)->default(0.00)->comment('Fixed amount discount');
            
            $table->timestamps();
            
            // Indexes
            $table->index('customer_id');
            $table->index('add_on_category_id');
            $table->unique(['customer_id', 'add_on_category_id'], 'customer_addon_cat_pricing_unique');
            
            // Foreign Keys
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            // add_on_category_id foreign key will be added after add_on_categories table is created
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_addon_category_pricing');
    }
};
