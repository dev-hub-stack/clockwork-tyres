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
        Schema::table('product_variants', function (Blueprint $table) {
            $table->index('rim_diameter');
            $table->index('rim_width');
            $table->index('bolt_pattern');
            $table->index('clearance_corner');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->index('status');
            $table->index('brand_id');
            $table->index('model_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropIndex(['rim_diameter']);
            $table->dropIndex(['rim_width']);
            $table->dropIndex(['bolt_pattern']);
            $table->dropIndex(['clearance_corner']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['brand_id']);
            $table->dropIndex(['model_id']);
        });
    }
};
