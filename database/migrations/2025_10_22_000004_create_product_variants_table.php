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
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->unsignedBigInteger('finish_id')->nullable();
            $table->string('size')->nullable();
            $table->string('bolt_pattern')->nullable();
            $table->string('hub_bore')->nullable();
            $table->string('offset')->nullable();
            $table->string('weight')->nullable();
            $table->string('backspacing')->nullable();
            $table->string('lipsize')->nullable();
            $table->string('finish')->nullable();
            $table->string('max_wheel_load')->nullable();
            $table->string('rim_diameter')->nullable();
            $table->string('rim_width')->nullable();
            $table->string('cost')->nullable();
            $table->string('price')->nullable();
            $table->decimal('us_retail_price', 8, 2)->nullable();
            $table->decimal('uae_retail_price', 8, 2)->nullable();
            $table->string('sale_price')->nullable();
            $table->boolean('clearance_corner')->default(0);
            $table->text('image')->nullable();
            $table->unsignedInteger('supplier_stock')->nullable();
            $table->unsignedBigInteger('product_id');
            $table->timestamps();

            $table->foreign('finish_id')->references('id')->on('finishes')->onDelete('SET NULL');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
