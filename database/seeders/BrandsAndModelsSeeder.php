<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Modules\Products\Models\Brand;
use App\Modules\Products\Models\ProductModel;
use Illuminate\Support\Str;

class BrandsAndModelsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Brands
        $brands = [
            ['name' => 'Fuel Off-Road'],
            ['name' => 'XD Series'],
            ['name' => 'Method Race Wheels'],
            ['name' => 'Black Rhino'],
            ['name' => 'Rotiform'],
        ];

        foreach ($brands as $brandData) {
            $brand = Brand::create([
                'name' => $brandData['name'],
                'slug' => Str::slug($brandData['name']),
                'status' => 1,
            ]);

            // Add models for each brand
            $this->createModelsForBrand($brand);
        }
    }

    private function createModelsForBrand(Brand $brand): void
    {
        $models = match($brand->name) {
            'Fuel Off-Road' => ['Assault', 'Maverick', 'Pump', 'Rebel', 'Sledge'],
            'XD Series' => ['Grenade', 'Rockstar', 'Monster', 'Addict', 'Hoss'],
            'Method Race Wheels' => ['MR301', 'MR305', 'MR312', 'MR701', 'MR703'],
            'Black Rhino' => ['Armory', 'Barstow', 'Sentinel', 'Warlord', 'Arsenal'],
            'Rotiform' => ['BLQ', 'KPS', 'SIX', 'RSE', 'CVT'],
            default => ['Model A', 'Model B', 'Model C'],
        };

        foreach ($models as $index => $modelName) {
            ProductModel::create([
                'brand_id' => $brand->id,
                'name' => $modelName,
                'slug' => Str::slug($modelName),
                'status' => 1,
            ]);
        }
    }
}

