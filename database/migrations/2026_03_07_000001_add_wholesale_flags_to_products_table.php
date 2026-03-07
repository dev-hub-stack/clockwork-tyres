<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add wholesale visibility and inventory tracking flags to the products table.
     *
     * available_on_wholesale — controls which products appear on tunerstopwholesale.com
     *                          Defaults to TRUE so all existing active products remain visible.
     *                          Admin can then bulk-deselect the ~4,000 non-wholesale products.
     *
     * track_inventory        — controls whether stock is enforced in the cart.
     *                          Defaults to FALSE (no restriction). Enable per-product for
     *                          products where over-selling must be prevented.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('available_on_wholesale')->default(true)->after('status');
            $table->boolean('track_inventory')->default(false)->after('available_on_wholesale');
        });

        // Ensure all currently active products stay visible on wholesale immediately.
        // Admins can deselect non-wholesale products via the Filament ProductResource.
        DB::table('products')->where('status', 1)->update(['available_on_wholesale' => true]);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['available_on_wholesale', 'track_inventory']);
        });
    }
};
