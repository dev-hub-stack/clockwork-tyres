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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            
            // Foreign Keys
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->cascadeOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            
            // Expense Details
            $table->string('expense_number')->unique(); // EXP-YYYYMMDD-####
            $table->string('expense_type'); // shipping, customs, packaging, insurance, etc.
            $table->decimal('amount', 12, 2);
            $table->date('expense_date');
            $table->string('currency', 3)->default('AED');
            
            // Vendor/Supplier
            $table->string('vendor_name')->nullable();
            $table->string('vendor_reference')->nullable(); // Invoice/receipt number from vendor
            
            // Payment Status
            $table->string('payment_status')->default('unpaid'); // unpaid, paid, pending
            $table->date('paid_date')->nullable();
            $table->string('payment_method')->nullable();
            
            // Attachments
            $table->string('receipt_path')->nullable(); // Path to receipt/invoice file
            
            // Notes
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            
            // Metadata
            $table->json('metadata')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('order_id');
            $table->index('customer_id');
            $table->index('expense_type');
            $table->index('expense_date');
            $table->index('payment_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
