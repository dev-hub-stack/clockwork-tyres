<?php

namespace App\Filament\Pages\Settings;

use App\Modules\Settings\Models\CompanyBranding;
use App\Modules\Settings\Models\CurrencySetting;
use App\Modules\Settings\Models\SystemSetting;
use App\Modules\Settings\Models\TaxSetting;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;

class ManageSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';
    
    protected static ?string $navigationLabel = 'Settings';

    protected static \UnitEnum|string|null $navigationGroup = 'Administration';
    
    protected static ?int $navigationSort = 99;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_settings') ?? false;
    }

    protected string $view = 'filament.pages.settings.manage-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $companyBranding = CompanyBranding::getActive() ?? new CompanyBranding();
        $baseCurrency = CurrencySetting::getBase() ?? new CurrencySetting();
        $defaultTax = TaxSetting::getDefault() ?? new TaxSetting();

        $this->form->fill([
            // Company Branding
            'company_name' => $companyBranding->company_name,
            'company_address' => $companyBranding->company_address,
            'company_phone' => $companyBranding->company_phone,
            'company_email' => $companyBranding->company_email,
            'company_website' => $companyBranding->company_website,
            'tax_registration_number' => $companyBranding->tax_registration_number,
            'commercial_registration' => $companyBranding->commercial_registration,
            'logo_path' => $companyBranding->logo_path ? [$companyBranding->logo_path] : [],
            'invoice_prefix' => $companyBranding->invoice_prefix,
            'quote_prefix' => $companyBranding->quote_prefix,
            'order_prefix' => $companyBranding->order_prefix,
            'consignment_prefix' => $companyBranding->consignment_prefix,
            'invoice_footer' => $companyBranding->invoice_footer,
            'quote_footer' => $companyBranding->quote_footer,
            
            // Currency
            'currency_code' => $baseCurrency->currency_code,
            'currency_name' => $baseCurrency->currency_name,
            'currency_symbol' => $baseCurrency->currency_symbol,
            'symbol_position' => $baseCurrency->symbol_position,
            'decimal_places' => $baseCurrency->decimal_places,
            'thousands_separator' => $baseCurrency->thousands_separator,
            'decimal_separator' => $baseCurrency->decimal_separator,
            
            // Tax
            'tax_name' => $defaultTax->name,
            'tax_rate' => $defaultTax->rate,
            'tax_inclusive_default' => $defaultTax->tax_inclusive_default,
            'tax_description' => $defaultTax->description,

            // Wholesale Store Settings
            'ws_pickup_enabled'        => (bool) SystemSetting::get('admin.pickup', '0'),
            'ws_delivery_enabled'      => (bool) SystemSetting::get('admin.delivery', '0'),
            'ws_cod_enabled'           => (bool) SystemSetting::get('admin.cod', '0'),
            'ws_bank_enabled'          => (bool) SystemSetting::get('admin.bank', '0'),
            'ws_credit_enabled'        => (bool) SystemSetting::get('credit_account_enable', '0'),
            'ws_bank_detail'           => SystemSetting::get('admin.bank_detail', ''),
            'ws_eta_message'           => SystemSetting::get('admin.eta_item_message', ''),
            'ws_shipping_rate_four'    => SystemSetting::get('admin.shipping_rate_upto_four', 200),
            'ws_shipping_rate_per_item'=> SystemSetting::get('admin.shipping_rate_per_item', 50),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Company Information')
                    ->description('Manage company details and contact information')
                    ->schema([
                        TextInput::make('company_name')
                            ->label('Company Name')
                            ->required()
                            ->maxLength(200),
                        Textarea::make('company_address')
                            ->label('Address')
                            ->rows(3)
                            ->columnSpanFull(),
                        TextInput::make('company_phone')
                            ->label('Phone')
                            ->maxLength(50)
                            ->placeholder('+971 50 123 4567'),
                        TextInput::make('company_email')
                            ->label('Email')
                            ->email()
                            ->maxLength(100),
                        TextInput::make('company_website')
                            ->label('Website')
                            ->url()
                            ->maxLength(200),
                        TextInput::make('tax_registration_number')
                            ->label('Tax Registration Number')
                            ->maxLength(100),
                        TextInput::make('commercial_registration')
                            ->label('Commercial Registration')
                            ->maxLength(100),
                    ])
                    ->columns(2)
                    ->collapsible(),
                    
                Section::make('Logo & Branding')
                    ->description('Configure company logo and document settings')
                    ->schema([
                        FileUpload::make('logo_path')
                            ->label('Company Logo')
                            ->image()
                            ->maxSize(10240)
                            ->disk('public')
                            ->directory('company-logos')
                            ->visibility('public')
                            ->preserveFilenames()
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp'])
                            ->helperText('Upload your company logo (max 10MB). Recommended size: 200x200px')
                            ->columnSpanFull(),
                        TextInput::make('invoice_prefix')
                            ->label('Invoice Prefix')
                            ->default('INV-')
                            ->maxLength(20),
                        TextInput::make('quote_prefix')
                            ->label('Quote Prefix')
                            ->default('QUO-')
                            ->maxLength(20),
                        TextInput::make('order_prefix')
                            ->label('Order Prefix')
                            ->default('ORD-')
                            ->maxLength(20),
                        TextInput::make('consignment_prefix')
                            ->label('Consignment Prefix')
                            ->default('CON-')
                            ->maxLength(20),
                        Textarea::make('invoice_footer')
                            ->label('Invoice Footer Text')
                            ->rows(2)
                            ->columnSpanFull(),
                        Textarea::make('quote_footer')
                            ->label('Quote Footer Text')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->collapsible(),
                    
                Section::make('Currency Settings')
                    ->description('Configure base currency and formatting')
                    ->schema([
                        Select::make('currency_code')
                            ->label('Currency Code')
                            ->options([
                                'SAR' => 'SAR - Saudi Riyal',
                                'USD' => 'USD - US Dollar',
                                'EUR' => 'EUR - Euro',
                                'GBP' => 'GBP - British Pound',
                                'AED' => 'AED - UAE Dirham',
                            ])
                            ->required()
                            ->searchable(),
                        TextInput::make('currency_name')
                            ->label('Currency Name')
                            ->required()
                            ->maxLength(50),
                        TextInput::make('currency_symbol')
                            ->label('Currency Symbol')
                            ->required()
                            ->maxLength(10),
                        Select::make('symbol_position')
                            ->label('Symbol Position')
                            ->options([
                                'before' => 'Before amount (e.g., $100)',
                                'after' => 'After amount (e.g., 100$)',
                            ])
                            ->default('before')
                            ->required(),
                        TextInput::make('decimal_places')
                            ->label('Decimal Places')
                            ->numeric()
                            ->default(2)
                            ->minValue(0)
                            ->maxValue(4),
                        TextInput::make('thousands_separator')
                            ->label('Thousands Separator')
                            ->default(',')
                            ->maxLength(5),
                        TextInput::make('decimal_separator')
                            ->label('Decimal Separator')
                            ->default('.')
                            ->maxLength(5),
                    ])
                    ->columns(3)
                    ->collapsible(),
                    
                Section::make('Wholesale Store Settings')
                    ->description('Control which delivery and payment options are available to wholesale customers at checkout')
                    ->schema([
                        Toggle::make('ws_pickup_enabled')
                            ->label('Allow Warehouse Pickup')
                            ->helperText('Customers can choose to collect their order from the warehouse.'),
                        Toggle::make('ws_delivery_enabled')
                            ->label('Allow Standard Delivery')
                            ->helperText('Enable delivery as a shipping option at checkout.'),
                        Toggle::make('ws_cod_enabled')
                            ->label('Allow Cash on Delivery')
                            ->helperText('Customers can pay in cash when the order is delivered.'),
                        Toggle::make('ws_bank_enabled')
                            ->label('Allow Bank Transfer')
                            ->helperText('Customers can pay by direct bank transfer.'),
                        Toggle::make('ws_credit_enabled')
                            ->label('Allow Credit Account')
                            ->helperText('Eligible customers can place orders on credit.'),
                        Textarea::make('ws_bank_detail')
                            ->label('Bank Account Details')
                            ->helperText('Shown to the customer when they select Bank Transfer at checkout.')
                            ->rows(3)
                            ->columnSpanFull(),
                        Textarea::make('ws_eta_message')
                            ->label('Estimated Delivery Message')
                            ->helperText('Optional message shown to customers at checkout (e.g. "Estimated delivery: 3–7 business days").')
                            ->rows(2)
                            ->columnSpanFull(),
                        TextInput::make('ws_shipping_rate_four')
                            ->label('Delivery Rate — up to 4 items (AED)')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('AED'),
                        TextInput::make('ws_shipping_rate_per_item')
                            ->label('Delivery Rate — each extra item beyond 4 (AED)')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('AED'),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Section::make('Tax Settings')
                    ->description('Configure default tax rate and behavior')
                    ->schema([
                        TextInput::make('tax_name')
                            ->label('Tax Name')
                            ->required()
                            ->maxLength(100)
                            ->placeholder('e.g., VAT, GST, Sales Tax'),
                        TextInput::make('tax_rate')
                            ->label('Tax Rate (%)')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.01)
                            ->suffix('%'),
                        Toggle::make('tax_inclusive_default')
                            ->label('Tax Inclusive by Default')
                            ->helperText('When enabled, prices will include tax by default.')
                            ->default(true),
                        Textarea::make('tax_description')
                            ->label('Description')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->collapsible(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        try {
            $data = $this->form->getState();

            // Handle logo path - it comes as an array from FileUpload
            $logoPath = $data['logo_path'] ?? null;
            if (is_array($logoPath)) {
                $logoPath = !empty($logoPath) ? reset($logoPath) : null;
            }

            // Update or create Company Branding
            $companyBranding = CompanyBranding::getActive() ?? new CompanyBranding();
            $companyBranding->fill([
                'company_name' => $data['company_name'],
                'company_address' => $data['company_address'],
                'company_phone' => $data['company_phone'],
                'company_email' => $data['company_email'],
                'company_website' => $data['company_website'],
                'tax_registration_number' => $data['tax_registration_number'],
                'commercial_registration' => $data['commercial_registration'],
                'logo_path' => $logoPath ?: $companyBranding->logo_path,
                'invoice_prefix' => $data['invoice_prefix'],
                'quote_prefix' => $data['quote_prefix'],
                'order_prefix' => $data['order_prefix'],
                'consignment_prefix' => $data['consignment_prefix'],
                'invoice_footer' => $data['invoice_footer'],
                'quote_footer' => $data['quote_footer'],
                'is_active' => true,
            ]);
            $companyBranding->save();

            // Update or create Base Currency
            $baseCurrency = CurrencySetting::getBase() ?? new CurrencySetting();
            $baseCurrency->fill([
                'currency_code' => $data['currency_code'],
                'currency_name' => $data['currency_name'],
                'currency_symbol' => $data['currency_symbol'],
                'symbol_position' => $data['symbol_position'],
                'decimal_places' => $data['decimal_places'],
                'thousands_separator' => $data['thousands_separator'],
                'decimal_separator' => $data['decimal_separator'],
                'exchange_rate' => 1.0000,
                'is_base_currency' => true,
                'is_active' => true,
            ]);
            $baseCurrency->save();

            // Update or create Default Tax
            $defaultTax = TaxSetting::getDefault() ?? new TaxSetting();
            $defaultTax->fill([
                'name' => $data['tax_name'],
                'rate' => $data['tax_rate'],
                'tax_inclusive_default' => $data['tax_inclusive_default'],
                'description' => $data['tax_description'],
                'is_default' => true,
                'is_active' => true,
            ]);
            $defaultTax->save();

            // Save Wholesale Store Settings into system_settings
            SystemSetting::set('admin.pickup',              $data['ws_pickup_enabled'] ? '1' : '0',  'boolean', 'Allow warehouse pickup at checkout');
            SystemSetting::set('admin.delivery',            $data['ws_delivery_enabled'] ? '1' : '0','boolean', 'Allow standard delivery at checkout');
            SystemSetting::set('admin.cod',                 $data['ws_cod_enabled'] ? '1' : '0',     'boolean', 'Allow cash on delivery');
            SystemSetting::set('admin.bank',                $data['ws_bank_enabled'] ? '1' : '0',    'boolean', 'Allow bank transfer payment');
            SystemSetting::set('credit_account_enable',     $data['ws_credit_enabled'] ? '1' : '0',  'boolean', 'Allow credit account payment');
            SystemSetting::set('admin.bank_detail',         $data['ws_bank_detail'] ?? '',            'string',  'Bank account details shown at checkout');
            SystemSetting::set('admin.eta_item_message',    $data['ws_eta_message'] ?? '',            'string',  'Estimated delivery message shown at checkout');
            SystemSetting::set('admin.shipping_rate_upto_four',  $data['ws_shipping_rate_four'] ?? 200,   'float', 'Delivery rate for up to 4 items (AED)');
            SystemSetting::set('admin.shipping_rate_per_item',   $data['ws_shipping_rate_per_item'] ?? 50, 'float', 'Delivery rate per extra item beyond 4 (AED)');

            // Clear settings cache
            app(\App\Modules\Settings\Services\SettingsService::class)->clearCache();

            Notification::make()
                ->title('Settings saved successfully')
                ->success()
                ->send();

        } catch (Halt $exception) {
            return;
        }
    }
}
