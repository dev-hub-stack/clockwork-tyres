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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            
            // Foreign Keys
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            
            // Payment Details
            $table->string('payment_number')->unique(); // PAY-YYYYMMDD-####
            $table->decimal('amount', 12, 2); // Payment amount
            $table->string('payment_method')->nullable(); // Cash, Card, Bank Transfer, etc.
            $table->string('payment_type')->default('full'); // full, partial
            $table->date('payment_date'); // When payment was received
            $table->string('reference_number')->nullable(); // Bank reference, transaction ID, etc.
            $table->string('currency', 3)->default('AED');
            
            // Bank Details (for bank transfers)
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('cheque_number')->nullable(); // For cheque payments
            
            // Status
            $table->string('status')->default('completed'); // completed, pending, failed, refunded
            
            // Notes
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();
            
            // Metadata
            $table->json('metadata')->nullable(); // For extensibility
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('order_id');
            $table->index('customer_id');
            $table->index('payment_date');
            $table->index('payment_method');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
