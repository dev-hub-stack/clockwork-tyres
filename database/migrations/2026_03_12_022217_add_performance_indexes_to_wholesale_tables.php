<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // product_variants: speed up the main /api/products listing query
        Schema::table('product_variants', function (Blueprint $table) {
            if (!$this->hasIndex('product_variants', 'idx_pv_product_id')) {
                $table->index('product_id', 'idx_pv_product_id');
            }
        });

        // products: WHERE status=1 AND available_on_wholesale=1
        Schema::table('products', function (Blueprint $table) {
            if (!$this->hasIndex('products', 'idx_products_status_wholesale')) {
                $table->index(['status', 'available_on_wholesale'], 'idx_products_status_wholesale');
            }
        });

        // product_inventories: stock lookups per variant and per warehouse
        Schema::table('product_inventories', function (Blueprint $table) {
            if (!$this->hasIndex('product_inventories', 'idx_pi_variant_warehouse')) {
                $table->index(['product_variant_id', 'warehouse_id'], 'idx_pi_variant_warehouse');
            }
            if (!$this->hasIndex('product_inventories', 'idx_pi_addon_id')) {
                $table->index('add_on_id', 'idx_pi_addon_id');
            }
        });

        // wholesale_carts: session_id and dealer_id lookups
        Schema::table('wholesale_carts', function (Blueprint $table) {
            if (!$this->hasIndex('wholesale_carts', 'idx_carts_session')) {
                $table->index('session_id', 'idx_carts_session');
            }
            if (!$this->hasIndex('wholesale_carts', 'idx_carts_dealer')) {
                $table->index('dealer_id', 'idx_carts_dealer');
            }
        });

        // wholesale_cart_items: lookups by cart_id and variant
        Schema::table('wholesale_cart_items', function (Blueprint $table) {
            if (!$this->hasIndex('wholesale_cart_items', 'idx_cart_items_cart_variant')) {
                $table->index(['cart_id', 'product_variant_id'], 'idx_cart_items_cart_variant');
            }
        });

        // addons: speed up category + deleted_at filter
        Schema::table('addons', function (Blueprint $table) {
            if (!$this->hasIndex('addons', 'idx_addons_category_deleted')) {
                $table->index(['addon_category_id', 'deleted_at'], 'idx_addons_category_deleted');
            }
        });
    }

    public function down(): void
    {
        Schema::table('product_variants',   fn($t) => $t->dropIndexIfExists('idx_pv_product_id'));
        Schema::table('products',           fn($t) => $t->dropIndexIfExists('idx_products_status_wholesale'));
        Schema::table('product_inventories',fn($t) => $t->dropIndexIfExists('idx_pi_variant_warehouse'));
        Schema::table('product_inventories',fn($t) => $t->dropIndexIfExists('idx_pi_addon_id'));
        Schema::table('wholesale_carts',    fn($t) => $t->dropIndexIfExists('idx_carts_session'));
        Schema::table('wholesale_carts',    fn($t) => $t->dropIndexIfExists('idx_carts_dealer'));
        Schema::table('wholesale_cart_items',fn($t) => $t->dropIndexIfExists('idx_cart_items_cart_variant'));
        Schema::table('addons',             fn($t) => $t->dropIndexIfExists('idx_addons_category_deleted'));
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        return collect(\DB::select("SHOW INDEX FROM `{$table}`"))
            ->pluck('Key_name')
            ->contains($indexName);
    }
};
