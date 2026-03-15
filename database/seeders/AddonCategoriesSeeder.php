<?php

namespace Database\Seeders;

use App\Models\AddonCategory;
use Illuminate\Database\Seeder;

class AddonCategoriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Uses updateOrCreate (keyed on slug) so this is safe to re-run
     * and will always correct any name/order mismatches.
     */
    public function run()
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
            AddonCategory::updateOrCreate(['slug' => $cat['slug']], $cat);
        }
    }
}
