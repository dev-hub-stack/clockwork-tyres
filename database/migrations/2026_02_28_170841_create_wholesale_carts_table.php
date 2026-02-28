<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wholesale_carts', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->unique();
            $table->foreignId('dealer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('order_number')->unique()->nullable();
            $table->foreignId('coupon_id')->nullable()->constrained('wholesale_coupons')->nullOnDelete();
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('sub_total', 10, 2)->default(0);
            $table->decimal('shipping', 10, 2)->default(0);
            $table->decimal('vat', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->foreignId('checkout_address_id')->nullable()->constrained('address_books')->nullOnDelete();
            $table->string('shipping_option')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wholesale_carts');
    }
};
