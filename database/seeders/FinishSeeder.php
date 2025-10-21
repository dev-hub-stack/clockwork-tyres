<?php

namespace Database\Seeders;

use App\Modules\Products\Models\Finish;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FinishSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $finishes = [
            [
                'name' => 'Matte Black',
                'hex_color' => '#1C1C1C',
                'description' => 'Non-reflective black finish with a smooth, flat appearance',
            ],
            [
                'name' => 'Gloss Black',
                'hex_color' => '#000000',
                'description' => 'High-gloss, deep black finish with a mirror-like shine',
            ],
            [
                'name' => 'Chrome',
                'hex_color' => '#C0C0C0',
                'description' => 'Bright, reflective chrome plating for a premium look',
            ],
            [
                'name' => 'Gunmetal',
                'hex_color' => '#2A3439',
                'description' => 'Dark grey metallic finish with subtle sheen',
            ],
            [
                'name' => 'Machined Black',
                'hex_color' => '#383838',
                'description' => 'Black finish with machined face for contrast',
            ],
            [
                'name' => 'Bronze',
                'hex_color' => '#CD7F32',
                'description' => 'Warm bronze metallic finish',
            ],
            [
                'name' => 'Silver',
                'hex_color' => '#C0C0C0',
                'description' => 'Classic silver finish with metallic sheen',
            ],
            [
                'name' => 'Anthracite',
                'hex_color' => '#383E42',
                'description' => 'Dark grey finish with slight metallic tint',
            ],
            [
                'name' => 'Hyper Silver',
                'hex_color' => '#D3D3D3',
                'description' => 'Bright silver finish with high reflectivity',
            ],
            [
                'name' => 'Matte Bronze',
                'hex_color' => '#B8860B',
                'description' => 'Non-reflective bronze finish with earthy tones',
            ],
            [
                'name' => 'Titanium',
                'hex_color' => '#878681',
                'description' => 'Metallic grey-silver finish resembling titanium',
            ],
            [
                'name' => 'Gold',
                'hex_color' => '#FFD700',
                'description' => 'Bright gold metallic finish',
            ],
        ];

        foreach ($finishes as $finish) {
            Finish::updateOrCreate(
                ['slug' => Str::slug($finish['name'])],
                [
                    'name' => $finish['name'],
                    'slug' => Str::slug($finish['name']),
                    'hex_color' => $finish['hex_color'],
                    'description' => $finish['description'],
                    'status' => 1,
                ]
            );
        }

        $this->command->info('Seeded ' . count($finishes) . ' finishes successfully!');
    }
}
