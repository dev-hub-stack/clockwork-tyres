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
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            
            // Variant Specifics
            $table->string('size', 50)->nullable()->comment('20x9, 20x10.5, etc.');
            $table->decimal('width', 5, 2)->nullable()->comment('9.0, 10.5, 12.0');
            $table->decimal('diameter', 5, 2)->nullable()->comment('20.0, 22.0, 24.0');
            $table->string('bolt_pattern', 100)->nullable()->comment('6x5.5, 5x114.3, etc.');
            $table->string('offset', 50)->nullable()->comment('+1mm, -12mm, etc.');
            $table->decimal('backspacing', 5, 2)->nullable()->comment('5.5, 6.0 inches');
            $table->decimal('center_bore', 5, 2)->nullable()->comment('78.1mm, 106.1mm');
            
            // Optional Variant-Specific Fields
            $table->foreignId('finish_id')->nullable()->constrained('finishes')->onDelete('set null')->comment('Override finish');
            $table->string('variant_sku', 100)->nullable()->comment('Unique SKU for variant');
            $table->decimal('variant_price', 10, 2)->nullable()->comment('Override price');
            
            // Stock
            $table->integer('quantity')->default(0);
            $table->string('warehouse_location', 100)->nullable();
            
            // Status
            $table->tinyInteger('status')->default(1);
            
            // Timestamps
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('product_id');
            $table->index('size');
            $table->index('bolt_pattern');
            $table->index('status');
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
