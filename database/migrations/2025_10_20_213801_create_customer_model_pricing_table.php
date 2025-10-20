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
        Schema::create('customer_model_pricing', function (Blueprint $table) {
            $table->id();
            
            // Relations
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('model_id')->comment('Product model, not vehicle model');
            
            // Discount Configuration
            $table->enum('discount_type', ['percentage', 'fixed'])->default('percentage');
            $table->decimal('discount_percentage', 5, 2)->default(0.00)->comment('0-100%');
            $table->decimal('discount_value', 10, 2)->default(0.00)->comment('Fixed amount discount');
            
            $table->timestamps();
            
            // Indexes
            $table->index('customer_id');
            $table->index('model_id');
            $table->unique(['customer_id', 'model_id']);
            
            // Foreign Keys
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->foreign('model_id')->references('id')->on('models')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_model_pricing');
    }
};
