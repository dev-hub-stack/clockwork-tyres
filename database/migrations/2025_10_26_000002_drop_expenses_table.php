<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Dropping expenses table as we're using orders table fields instead.
     * This matches the old Reporting system structure (no separate expenses table).
     * Better to remove now during development than later with production data.
     * 
     * NOTE: payments table is kept - it's different and used for tracking customer payments.
     */
    public function up(): void
    {
        // Drop expenses table - not needed, using orders table expense fields instead
        Schema::dropIfExists('expenses');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate expenses table if we need to rollback
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('expense_type', 50);
            $table->decimal('amount', 10, 2);
            $table->date('expense_date');
            $table->string('vendor_name')->nullable();
            $table->string('vendor_reference')->nullable();
            $table->string('receipt_path')->nullable();
            $table->text('description')->nullable();
            $table->string('payment_status', 20)->default('unpaid');
            $table->timestamps();
            
            $table->index('order_id');
            $table->index('customer_id');
            $table->index('expense_date');
        });
    }
};
