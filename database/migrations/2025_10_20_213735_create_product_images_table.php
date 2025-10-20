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
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            
            // Image Mapping (by brand + model + finish)
            $table->foreignId('brand_id')->constrained('brands')->onDelete('cascade');
            $table->foreignId('model_id')->constrained('models')->onDelete('cascade');
            $table->foreignId('finish_id')->constrained('finishes')->onDelete('cascade');
            
            // Images (up to 9 images per combination)
            $table->string('image_1', 500)->nullable();
            $table->string('image_2', 500)->nullable();
            $table->string('image_3', 500)->nullable();
            $table->string('image_4', 500)->nullable();
            $table->string('image_5', 500)->nullable();
            $table->string('image_6', 500)->nullable();
            $table->string('image_7', 500)->nullable();
            $table->string('image_8', 500)->nullable();
            $table->string('image_9', 500)->nullable();
            
            // Timestamps
            $table->timestamps();
            
            // Unique constraint
            $table->unique(['brand_id', 'model_id', 'finish_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};
