<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Makes order_items.product_name nullable so that add-on items
     * or items where the addon record is missing don't crash on insert.
     * The OrderItemObserver populates product_name from the addon/variant,
     * but as a safety net the column should accept NULL gracefully.
     */
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('product_name')->nullable()->default(null)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            // Restore NOT NULL — note: any existing NULL rows will prevent this
            $table->string('product_name')->nullable(false)->change();
        });
    }
};
