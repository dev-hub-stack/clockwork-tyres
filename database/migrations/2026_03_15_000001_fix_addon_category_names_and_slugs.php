<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Ensure every addon_category row has the correct name for its slug.
     * Fixes cases where the original insert-based seeder created rows with
     * mismatched name/slug pairs.
     */
    public function up(): void
    {
        $categories = [
            ['slug' => 'wheel-accessories', 'name' => 'Wheel Accessories', 'order' => 1],
            ['slug' => 'lug-nuts',          'name' => 'Lug Nuts',          'order' => 2],
            ['slug' => 'lug-bolts',         'name' => 'Lug Bolts',         'order' => 3],
            ['slug' => 'hub-rings',         'name' => 'Hub Rings',         'order' => 4],
            ['slug' => 'spacers',           'name' => 'Spacers',           'order' => 5],
            ['slug' => 'tpms',              'name' => 'TPMS',              'order' => 6],
        ];

        foreach ($categories as $cat) {
            DB::table('addon_categories')
                ->where('slug', $cat['slug'])
                ->update([
                    'name'  => $cat['name'],
                    'order' => $cat['order'],
                ]);
        }
    }

    public function down(): void
    {
        // No rollback — names were already wrong; reverting would re-break them.
    }
};
