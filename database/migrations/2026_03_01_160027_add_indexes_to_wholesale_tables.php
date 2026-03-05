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
        // Use try/catch per index to gracefully skip any already-existing indexes
        // (prevents duplicate key name errors if migration is re-run or another migration pre-created them)
        $variantIndexes = ['rim_diameter', 'rim_width', 'bolt_pattern', 'clearance_corner'];
        foreach ($variantIndexes as $column) {
            try {
                Schema::table('product_variants', fn(Blueprint $table) => $table->index($column));
            } catch (\Illuminate\Database\QueryException $e) {
                // Index already exists — skip silently
            }
        }

        $productIndexes = ['status', 'brand_id', 'model_id'];
        foreach ($productIndexes as $column) {
            try {
                Schema::table('products', fn(Blueprint $table) => $table->index($column));
            } catch (\Illuminate\Database\QueryException $e) {
                // Index already exists — skip silently
            }
        }
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
