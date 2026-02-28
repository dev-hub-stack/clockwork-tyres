<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wholesale_product_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dealer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('rating')->unsigned(); // 1 to 5
            $table->text('review')->nullable();
            $table->boolean('is_approved')->default(false); // require moderation
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wholesale_product_reviews');
    }
};
