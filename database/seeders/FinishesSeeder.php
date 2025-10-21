<?php

namespace Database\Seeders;

use App\Modules\Products\Models\Finish;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FinishesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $finishes = [
            [
                'name' => 'Chrome',
                'hex_color' => '#C0C0C0',
                'description' => 'Classic chrome finish with mirror-like reflective surface',
            ],
            [
                'name' => 'Matte Black',
                'hex_color' => '#1C1C1C',
                'description' => 'Sleek matte black finish with no shine or gloss',
            ],
            [
                'name' => 'Gloss Black',
                'hex_color' => '#000000',
                'description' => 'High-gloss black finish with deep, reflective shine',
            ],
            [
                'name' => 'Machined Face',
                'hex_color' => '#8B8B8B',
                'description' => 'Polished aluminum face with black or dark accents',
            ],
            [
                'name' => 'Gunmetal',
                'hex_color' => '#2C3539',
                'description' => 'Dark gray metallic finish with subtle metallic flake',
            ],
            [
                'name' => 'Bronze',
                'hex_color' => '#CD7F32',
                'description' => 'Rich bronze metallic finish',
            ],
            [
                'name' => 'White',
                'hex_color' => '#FFFFFF',
                'description' => 'Clean white finish, available in gloss or matte',
            ],
            [
                'name' => 'Polished',
                'hex_color' => '#E8E8E8',
                'description' => 'Highly polished aluminum finish',
            ],
        ];

        foreach ($finishes as $finish) {
            Finish::create([
                'name' => $finish['name'],
                'slug' => Str::slug($finish['name']),
                'color_code' => $finish['hex_color'],
                'description' => $finish['description'],
                'status' => 1, // Active
            ]);
        }

        $this->command->info('Created 8 common wheel finishes');
    }
}
