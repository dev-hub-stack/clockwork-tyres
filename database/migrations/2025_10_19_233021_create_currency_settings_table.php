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
        Schema::create('currency_settings', function (Blueprint $table) {
            $table->id();
            $table->string('currency_code', 3); // ISO 4217 code (USD, EUR, SAR, etc.)
            $table->string('currency_name', 50);
            $table->string('currency_symbol', 10); // $, €, ر.س, etc.
            $table->string('symbol_position', 10)->default('before'); // 'before' or 'after'
            $table->decimal('exchange_rate', 10, 4)->default(1.0000); // Exchange rate to base currency
            $table->boolean('is_base_currency')->default(false); // Is this the base currency
            $table->boolean('is_active')->default(true);
            $table->integer('decimal_places')->default(2);
            $table->string('thousands_separator', 5)->default(',');
            $table->string('decimal_separator', 5)->default('.');
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->unique('currency_code');
            $table->index('is_base_currency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('currency_settings');
    }
};
