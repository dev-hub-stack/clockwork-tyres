<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('product_full_name', 255)->nullable()->after('name');
        });

        // Populate product_full_name from tunerstop source DB (same MySQL server)
        DB::statement("
            UPDATE reporting_crm.products p
            JOIN tunerstop.products ts ON ts.id = p.id
            SET p.product_full_name = NULLIF(TRIM(ts.product_full_name), '')
            WHERE p.product_full_name IS NULL
        ");

        // Backfill missing product rows that variants reference but don't exist here
        DB::statement("
            INSERT IGNORE INTO reporting_crm.products
                (id, name, product_full_name, sku, price, brand_id, model_id, finish_id, status, created_at, updated_at)
            SELECT
                ts.id, ts.name, ts.product_full_name, NULL,
                COALESCE(ts.price, 0), ts.brand_id, ts.model_id, ts.finish_id,
                ts.status, ts.created_at, ts.updated_at
            FROM tunerstop.products ts
            WHERE ts.id IN (
                SELECT DISTINCT pv.product_id
                FROM reporting_crm.product_variants pv
                LEFT JOIN reporting_crm.products p ON p.id = pv.product_id
                WHERE p.id IS NULL AND pv.product_id IS NOT NULL
            )
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('product_full_name');
        });
    }
};
