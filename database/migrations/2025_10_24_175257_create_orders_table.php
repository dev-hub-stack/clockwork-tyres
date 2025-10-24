<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * UNIFIED ORDERS TABLE - stores quotes, invoices, and orders in ONE table
     * differentiated by document_type field
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            
            // CRITICAL: Document type discriminator (quote/invoice/order)
            $table->string('document_type', 20)->default('quote')->index();
            
            // Quote-specific fields
            $table->string('quote_number', 50)->nullable()->unique();
            $table->string('quote_status', 20)->nullable()->index();
            
            // Order/Invoice fields (used for all document types)
            $table->string('order_number', 50)->unique();
            $table->string('order_status', 30)->default('pending')->index();
            $table->string('payment_status', 20)->default('pending')->index();
            
            // Customer & relationships
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->onDelete('set null');
            $table->foreignId('representative_id')->nullable()->constrained('users')->onDelete('set null');
            
            // External order tracking (from TunerStop/Wholesale)
            $table->string('external_order_id', 100)->nullable()->index();
            $table->string('external_source', 20)->nullable(); // retail/wholesale/manual
            
            // Financial fields
            $table->decimal('sub_total', 10, 2)->default(0);
            $table->decimal('tax', 10, 2)->default(0);
            $table->decimal('vat', 10, 2)->default(0);
            $table->decimal('shipping', 10, 2)->default(0);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->string('currency', 3)->default('AED');
            
            // CRITICAL: Tax calculation mode
            $table->boolean('tax_inclusive')->default(true); // true = tax included in price, false = tax added on top
            
            // Vehicle information
            $table->string('vehicle_year', 4)->nullable();
            $table->string('vehicle_make', 100)->nullable();
            $table->string('vehicle_model', 100)->nullable();
            $table->string('vehicle_sub_model', 100)->nullable();
            
            // Quote to Invoice conversion tracking
            $table->boolean('is_quote_converted')->default(false);
            $table->foreignId('converted_to_invoice_id')->nullable()->constrained('orders')->onDelete('set null');
            
            // Date fields
            $table->date('issue_date')->nullable();
            $table->date('valid_until')->nullable(); // For quotes
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            
            // Shipping information
            $table->string('tracking_number', 100)->nullable();
            $table->string('shipping_carrier', 50)->nullable();
            
            // Notes
            $table->text('order_notes')->nullable();
            $table->text('internal_notes')->nullable();
            
            // Timestamps
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index(['document_type', 'order_status']);
            $table->index(['customer_id', 'document_type']);
            $table->index(['external_order_id', 'external_source']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
