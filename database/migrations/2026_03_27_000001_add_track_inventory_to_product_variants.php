<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->boolean('track_inventory')->default(false)->after('supplier_stock');
        });

        // Backfill: copy product-level flag to all child variants
        if (DB::getDriverName() === 'sqlite') {
            DB::table('product_variants')
                ->select('id', 'product_id')
                ->orderBy('id')
                ->get()
                ->each(function ($variant) {
                    $trackInventory = DB::table('products')
                        ->where('id', $variant->product_id)
                        ->value('track_inventory');

                    DB::table('product_variants')
                        ->where('id', $variant->id)
                        ->update(['track_inventory' => (bool) $trackInventory]);
                });

            return;
        }

        DB::statement('
            UPDATE product_variants pv
            JOIN products p ON p.id = pv.product_id
            SET pv.track_inventory = p.track_inventory
        ');
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn('track_inventory');
        });
    }
};
