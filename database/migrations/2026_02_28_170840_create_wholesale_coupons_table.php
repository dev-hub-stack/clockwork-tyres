<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wholesale_coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->string('discount_type')->default('percentage'); // 'fixed' | 'percentage'
            $table->decimal('amount', 10, 2)->default(0);
            $table->decimal('min_spent', 10, 2)->default(0);
            $table->integer('max_usage_limit')->nullable();
            $table->integer('used_count')->default(0);
            $table->boolean('free_shipping')->default(false);
            $table->date('start_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->boolean('status')->default(true);
            $table->json('brand_ids')->nullable();  // restrict to specific brands
            $table->json('model_ids')->nullable();  // restrict to specific models
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wholesale_coupons');
    }
};
