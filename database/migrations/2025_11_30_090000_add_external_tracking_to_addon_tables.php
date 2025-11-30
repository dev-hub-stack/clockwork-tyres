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
        // Add external tracking to addon_categories
        Schema::table('addon_categories', function (Blueprint $table) {
            $table->string('display_name')->nullable()->after('name');
            $table->string('image')->nullable()->after('display_name');
            $table->integer('order_sort')->default(0)->after('order');
            $table->bigInteger('external_id')->nullable()->after('is_active');
            $table->string('external_source')->nullable()->after('external_id');
            
            $table->index(['external_id', 'external_source']);
        });

        // Add external tracking to addons
        Schema::table('addons', function (Blueprint $table) {
            $table->bigInteger('external_addon_id')->nullable()->after('id');
            $table->string('external_source')->nullable()->after('external_addon_id');
            
            $table->index(['external_addon_id', 'external_source']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('addon_categories', function (Blueprint $table) {
            $table->dropIndex(['external_id', 'external_source']);
            $table->dropColumn(['display_name', 'image', 'order_sort', 'external_id', 'external_source']);
        });

        Schema::table('addons', function (Blueprint $table) {
            $table->dropIndex(['external_addon_id', 'external_source']);
            $table->dropColumn(['external_addon_id', 'external_source']);
        });
    }
};
