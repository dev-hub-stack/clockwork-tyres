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
        Schema::create('damaged_inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained('product_variants')->onDelete('cascade');
            $table->foreignId('warehouse_id')->constrained('warehouses')->onDelete('cascade');
            $table->integer('quantity');
            $table->string('condition')->comment('damaged, defective');
            $table->text('notes')->nullable();
            $table->foreignId('consignment_id')->nullable()->constrained('consignments')->onDelete('set null');
            $table->timestamps();
            
            // Indexes for grid performance
            $table->index(['product_variant_id', 'warehouse_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('damaged_inventories');
    }
};
