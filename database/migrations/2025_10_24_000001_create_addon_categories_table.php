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
        Schema::create('addon_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indexes
            $table->index('slug');
            $table->index('order');
            $table->index('is_active');
        });

        // Now add the foreign key to customer_addon_category_pricing
        if (Schema::hasTable('customer_addon_category_pricing')) {
            Schema::table('customer_addon_category_pricing', function (Blueprint $table) {
                $table->foreign('add_on_category_id')
                      ->references('id')
                      ->on('addon_categories')
                      ->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop foreign key from customer_addon_category_pricing first
        if (Schema::hasTable('customer_addon_category_pricing')) {
            Schema::table('customer_addon_category_pricing', function (Blueprint $table) {
                $table->dropForeign(['add_on_category_id']);
            });
        }
        
        Schema::dropIfExists('addon_categories');
    }
};
