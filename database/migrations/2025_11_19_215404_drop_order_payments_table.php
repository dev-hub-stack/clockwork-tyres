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
        // Drop order_payments table - we're using the existing payments table instead
        Schema::dropIfExists('order_payments');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate the table if we need to rollback
        Schema::create('order_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->decimal('payment_amount', 10, 2);
            $table->enum('payment_type', ['full', 'partial'])->default('partial');
            $table->string('payment_method');
            $table->text('payment_notes')->nullable();
            $table->string('payment_reference')->nullable();
            $table->string('payment_status')->default('completed');
            $table->timestamp('payment_date')->useCurrent();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });
    }
};
