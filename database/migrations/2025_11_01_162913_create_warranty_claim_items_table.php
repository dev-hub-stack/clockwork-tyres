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
        Schema::create('warranty_claim_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warranty_claim_id')->constrained()->cascadeOnDelete();
            
            // Product reference (from invoice or manual entry)
            $table->foreignId('product_id')->nullable()->constrained();
            $table->foreignId('product_variant_id')->nullable()->constrained();
            
            // Invoice reference (if claim linked to invoice)
            $table->foreignId('invoice_id')->nullable()->constrained('orders');
            $table->foreignId('invoice_item_id')->nullable()->constrained('order_items');
            
            // Claim details
            $table->integer('quantity');
            $table->text('issue_description');
            $table->string('resolution_action'); // ResolutionAction enum
            
            $table->timestamps();
            
            // Indexes
            $table->index('warranty_claim_id');
            $table->index('product_variant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warranty_claim_items');
    }
};
