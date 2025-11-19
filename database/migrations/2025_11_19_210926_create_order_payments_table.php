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
        Schema::create('order_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->decimal('payment_amount', 10, 2);
            $table->enum('payment_type', ['full', 'partial'])->default('partial');
            $table->string('payment_method'); // cash, credit_card, bank_transfer, check, etc
            $table->text('payment_notes')->nullable();
            $table->string('payment_reference')->nullable(); // transaction ID, check number, etc
            $table->string('payment_status')->default('completed'); // completed, pending, failed, refunded
            $table->timestamp('payment_date')->useCurrent();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_payments');
    }
};
