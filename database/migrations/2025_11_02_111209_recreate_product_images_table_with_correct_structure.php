<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Drop the old product_images table and recreate with correct Tunerstop structure
     */
    public function up(): void
    {
        // Drop old table
        Schema::dropIfExists('product_images');
        
        // Recreate with correct structure matching Tunerstop
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('model_id')->constrained('models')->onDelete('cascade');
            $table->foreignId('brand_id')->constrained('brands')->onDelete('cascade');
            $table->foreignId('finish_id')->constrained('finishes')->onDelete('cascade');
            $table->text('image_1')->nullable();
            $table->text('image_2')->nullable();
            $table->text('image_3')->nullable();
            $table->text('image_4')->nullable();
            $table->text('image_5')->nullable();
            $table->text('image_6')->nullable();
            $table->text('image_7')->nullable();
            $table->text('image_8')->nullable();
            $table->text('image_9')->nullable();
            $table->timestamps();
            
            // Add unique index to prevent duplicate brand+model+finish combinations
            $table->unique(['brand_id', 'model_id', 'finish_id'], 'unique_product_image_combination');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_images');
        
        // Restore old structure
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->string('image_path');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_primary')->default(0);
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
        });
    }
};

