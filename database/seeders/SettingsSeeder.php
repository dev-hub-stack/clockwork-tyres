<?php

namespace Database\Seeders;

use App\Modules\Settings\Models\TaxSetting;
use App\Modules\Settings\Models\CurrencySetting;
use App\Modules\Settings\Models\CompanyBranding;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Tax Settings
        TaxSetting::create([
            'name' => 'VAT (Saudi Arabia)',
            'rate' => 15.00,
            'is_default' => true,
            'tax_inclusive_default' => true,
            'is_active' => true,
            'description' => 'Value Added Tax for Saudi Arabia (15%)',
        ]);

        TaxSetting::create([
            'name' => 'No Tax',
            'rate' => 0.00,
            'is_default' => false,
            'tax_inclusive_default' => false,
            'is_active' => true,
            'description' => 'Tax-exempt transactions',
        ]);

        // Create Currency Settings
        CurrencySetting::create([
            'currency_code' => 'SAR',
            'currency_name' => 'Saudi Riyal',
            'currency_symbol' => 'ر.س',
            'symbol_position' => 'before',
            'exchange_rate' => 1.0000,
            'is_base_currency' => true,
            'is_active' => true,
            'decimal_places' => 2,
            'thousands_separator' => ',',
            'decimal_separator' => '.',
        ]);

        CurrencySetting::create([
            'currency_code' => 'USD',
            'currency_name' => 'US Dollar',
            'currency_symbol' => '$',
            'symbol_position' => 'before',
            'exchange_rate' => 3.75,
            'is_base_currency' => false,
            'is_active' => true,
            'decimal_places' => 2,
            'thousands_separator' => ',',
            'decimal_separator' => '.',
        ]);

        CurrencySetting::create([
            'currency_code' => 'EUR',
            'currency_name' => 'Euro',
            'currency_symbol' => '€',
            'symbol_position' => 'before',
            'exchange_rate' => 4.10,
            'is_base_currency' => false,
            'is_active' => true,
            'decimal_places' => 2,
            'thousands_separator' => ',',
            'decimal_separator' => '.',
        ]);

        // Create Company Branding
        CompanyBranding::create([
            'company_name' => 'Reporting CRM',
            'company_address' => 'Riyadh, Saudi Arabia',
            'company_phone' => '+966 XX XXX XXXX',
            'company_email' => 'info@reportingcrm.com',
            'company_website' => 'www.reportingcrm.com',
            'tax_registration_number' => '300000000000003',
            'commercial_registration' => '1010000000',
            'logo_path' => null,
            'primary_color' => '#1e40af',
            'secondary_color' => '#64748b',
            'invoice_prefix' => 'INV-',
            'quote_prefix' => 'QUO-',
            'order_prefix' => 'ORD-',
            'consignment_prefix' => 'CON-',
            'invoice_footer' => 'Thank you for your business!',
            'quote_footer' => 'We appreciate the opportunity to serve you.',
            'is_active' => true,
        ]);
    }
}

