<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Ensure all 6 canonical addon categories exist in the DB.
     * Uses INSERT IGNORE / updateOrCreate logic so it never duplicates.
     */
    public function up(): void
    {
        $categories = [
            ['name' => 'Wheel Accessories', 'slug' => 'wheel-accessories', 'order' => 1, 'is_active' => true],
            ['name' => 'Lug Nuts',          'slug' => 'lug-nuts',          'order' => 2, 'is_active' => true],
            ['name' => 'Lug Bolts',         'slug' => 'lug-bolts',         'order' => 3, 'is_active' => true],
            ['name' => 'Hub Rings',         'slug' => 'hub-rings',         'order' => 4, 'is_active' => true],
            ['name' => 'Spacers',           'slug' => 'spacers',           'order' => 5, 'is_active' => true],
            ['name' => 'TPMS',              'slug' => 'tpms',              'order' => 6, 'is_active' => true],
        ];

        foreach ($categories as $cat) {
            $exists = DB::table('addon_categories')->where('slug', $cat['slug'])->first();
            if ($exists) {
                DB::table('addon_categories')
                    ->where('slug', $cat['slug'])
                    ->update([
                        'name'      => $cat['name'],
                        'order'     => $cat['order'],
                        'is_active' => $cat['is_active'],
                    ]);
            } else {
                DB::table('addon_categories')->insert(array_merge($cat, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }
    }

    public function down(): void {}
};
