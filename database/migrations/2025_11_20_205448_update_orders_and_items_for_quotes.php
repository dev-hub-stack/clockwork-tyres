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
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'channel')) {
                $table->string('channel')->nullable()->after('order_number'); // retail, wholesale
            }
            if (!Schema::hasColumn('orders', 'tax_type')) {
                $table->string('tax_type')->default('standard')->after('vat'); // standard, zero_rated
            }
            if (!Schema::hasColumn('orders', 'tax_inclusive')) {
                $table->boolean('tax_inclusive')->default(true)->after('tax_type');
            }
        });

        Schema::table('order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('order_items', 'category')) {
                $table->string('category')->nullable()->after('product_variant_id'); // wheels, tires, etc.
            }
            if (!Schema::hasColumn('order_items', 'item_attributes')) {
                $table->json('item_attributes')->nullable()->after('category');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['channel', 'tax_type', 'tax_inclusive']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['category', 'item_attributes']);
        });
    }
};
