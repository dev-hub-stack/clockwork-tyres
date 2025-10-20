<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CountriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $countries = [
            ['name' => 'United Arab Emirates', 'code' => 'AE', 'code3' => 'ARE', 'phone_code' => '971', 'is_active' => true],
            ['name' => 'Saudi Arabia', 'code' => 'SA', 'code3' => 'SAU', 'phone_code' => '966', 'is_active' => true],
            ['name' => 'United States', 'code' => 'US', 'code3' => 'USA', 'phone_code' => '1', 'is_active' => true],
            ['name' => 'United Kingdom', 'code' => 'GB', 'code3' => 'GBR', 'phone_code' => '44', 'is_active' => true],
            ['name' => 'Canada', 'code' => 'CA', 'code3' => 'CAN', 'phone_code' => '1', 'is_active' => true],
            ['name' => 'Australia', 'code' => 'AU', 'code3' => 'AUS', 'phone_code' => '61', 'is_active' => true],
            ['name' => 'Germany', 'code' => 'DE', 'code3' => 'DEU', 'phone_code' => '49', 'is_active' => true],
            ['name' => 'France', 'code' => 'FR', 'code3' => 'FRA', 'phone_code' => '33', 'is_active' => true],
            ['name' => 'India', 'code' => 'IN', 'code3' => 'IND', 'phone_code' => '91', 'is_active' => true],
            ['name' => 'China', 'code' => 'CN', 'code3' => 'CHN', 'phone_code' => '86', 'is_active' => true],
        ];

        foreach ($countries as $country) {
            DB::table('countries')->insert(array_merge($country, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }
}
