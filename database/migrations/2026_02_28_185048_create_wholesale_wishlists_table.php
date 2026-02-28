<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wholesale_wishlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dealer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            
            // A dealer can only wishlist a specific variant once
            $table->unique(['dealer_id', 'product_variant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wholesale_wishlists');
    }
};
