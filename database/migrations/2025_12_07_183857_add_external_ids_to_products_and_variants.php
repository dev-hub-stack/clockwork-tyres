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
        Schema::table('products', function (Blueprint $table) {
            $table->string('external_product_id')->nullable()->after('id');
            $table->string('external_source')->nullable()->after('external_product_id');
            $table->index(['external_product_id', 'external_source']);
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->string('external_variant_id')->nullable()->after('id');
            $table->string('external_source')->nullable()->after('external_variant_id');
            $table->index(['external_variant_id', 'external_source']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['external_product_id', 'external_source']);
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn(['external_variant_id', 'external_source']);
        });
    }
};
