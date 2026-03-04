<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WholesaleSettingsSeeder extends Seeder
{
    /**
     * Wholesale checkout settings — mirrors the old wholesale admin's
     * settings table (group = 'Admin') keys so the frontend can use the
     * same checkoutOptions['admin.pickup'] style checks.
     */
    public function run(): void
    {
        $settings = [
            [
                'key'         => 'admin.pickup',
                'value'       => '1',
                'type'        => 'boolean',
                'description' => 'Allow customers to pick up orders from the warehouse',
            ],
            [
                'key'         => 'admin.delivery',
                'value'       => '1',
                'type'        => 'boolean',
                'description' => 'Allow standard delivery orders',
            ],
            [
                'key'         => 'admin.cod',
                'value'       => '0',
                'type'        => 'boolean',
                'description' => 'Allow cash on delivery payment',
            ],
            [
                'key'         => 'admin.bank',
                'value'       => '1',
                'type'        => 'boolean',
                'description' => 'Allow bank transfer payment',
            ],
            [
                'key'         => 'admin.bank_detail',
                'value'       => 'Bank: Emirates NBD | Account: 1234567890 | IBAN: AE123456789012345678901',
                'type'        => 'string',
                'description' => 'Bank account details shown to customer when bank transfer is selected',
            ],
            [
                'key'         => 'admin.tax_rate',
                'value'       => '5',
                'type'        => 'float',
                'description' => 'VAT / Tax rate applied to orders (%)',
            ],
            [
                'key'         => 'admin.eta_item_message',
                'value'       => 'Estimated delivery: 3–7 business days',
                'type'        => 'string',
                'description' => 'ETA message shown to customers at checkout',
            ],
            [
                'key'         => 'admin.shipping_rate_upto_four',
                'value'       => '200',
                'type'        => 'float',
                'description' => 'Shipping rate (AED) for orders with up to 4 items',
            ],
            [
                'key'         => 'admin.shipping_rate_per_item',
                'value'       => '50',
                'type'        => 'float',
                'description' => 'Additional shipping rate (AED) per item beyond 4',
            ],
            [
                'key'         => 'credit_account_enable',
                'value'       => '0',
                'type'        => 'boolean',
                'description' => 'Allow credit account payment method for eligible customers',
            ],
        ];

        foreach ($settings as $setting) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => $setting['key']],
                array_merge($setting, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        $this->command->info('Wholesale settings seeded successfully.');
    }
}
