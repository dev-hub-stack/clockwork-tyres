<?php

namespace App\Filament\Resources\Settings\CompanyBrandings\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class CompanyBrandingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Section::make('Company Information')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('company_name')
                                    ->label('Company Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('TunerStop Tyres & Acc. Trading L.L.C')
                                    ->columnSpan(2),

                                Forms\Components\Textarea::make('company_address')
                                    ->label('Address')
                                    ->rows(3)
                                    ->maxLength(500)
                                    ->placeholder('Warehouse 3, No. 36, Street 4B, Ras Al Khor Industrial 2')
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('company_phone')
                                    ->label('Phone')
                                    ->tel()
                                    ->maxLength(50)
                                    ->placeholder('+971 4 123 4567'),

                                Forms\Components\TextInput::make('company_email')
                                    ->label('Email')
                                    ->email()
                                    ->maxLength(100)
                                    ->placeholder('info@tunerstop.ae'),

                                Forms\Components\TextInput::make('company_website')
                                    ->label('Website')
                                    ->url()
                                    ->maxLength(255)
                                    ->placeholder('https://tunerstop.ae'),

                                Forms\Components\TextInput::make('tax_registration_number')
                                    ->label('Tax Registration Number (TRN)')
                                    ->maxLength(50)
                                    ->placeholder('100479491100003'),

                                Forms\Components\TextInput::make('commercial_registration')
                                    ->label('Commercial Registration (CR)')
                                    ->maxLength(50)
                                    ->columnSpan(2),
                            ]),
                    ])
                    ->columns(1),

                Forms\Components\Section::make('Branding & Logo')
                    ->schema([
                        Forms\Components\FileUpload::make('logo_path')
                            ->label('Company Logo')
                            ->image()
                            ->imageEditor()
                            ->imageEditorAspectRatios([
                                null,
                                '16:9',
                                '4:3',
                                '1:1',
                            ])
                            ->directory('company-logos')
                            ->visibility('public')
                            ->maxSize(2048) // 2MB max
                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/jpg', 'image/svg+xml'])
                            ->helperText('Upload your company logo. Recommended size: 300x100px. Max file size: 2MB.')
                            ->columnSpan(2),

                        Forms\Components\Placeholder::make('logo_preview')
                            ->label('Current Logo')
                            ->content(fn ($record) => $record && $record->logo_url 
                                ? new \Illuminate\Support\HtmlString('<img src="' . $record->logo_url . '" alt="Company Logo" class="max-w-xs h-auto">')
                                : 'No logo uploaded'
                            )
                            ->visible(fn ($record) => $record !== null)
                            ->columnSpan(2),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Document Prefixes')
                    ->description('These prefixes will be used for auto-generating document numbers')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('invoice_prefix')
                                    ->label('Invoice Prefix')
                                    ->default('INV')
                                    ->maxLength(10)
                                    ->placeholder('INV'),

                                Forms\Components\TextInput::make('quote_prefix')
                                    ->label('Quote Prefix')
                                    ->default('QUO')
                                    ->maxLength(10)
                                    ->placeholder('QUO'),

                                Forms\Components\TextInput::make('order_prefix')
                                    ->label('Order Prefix')
                                    ->default('ORD')
                                    ->maxLength(10)
                                    ->placeholder('ORD'),

                                Forms\Components\TextInput::make('consignment_prefix')
                                    ->label('Consignment Prefix')
                                    ->default('CON')
                                    ->maxLength(10)
                                    ->placeholder('CON'),
                            ]),
                    ])
                    ->columns(1)
                    ->collapsed(),

                Forms\Components\Section::make('Document Footers')
                    ->description('Footer text that will appear at the bottom of documents')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Textarea::make('invoice_footer')
                                    ->label('Invoice Footer')
                                    ->rows(2)
                                    ->maxLength(500)
                                    ->placeholder('Thank you for your business!'),

                                Forms\Components\Textarea::make('quote_footer')
                                    ->label('Quote Footer')
                                    ->rows(2)
                                    ->maxLength(500)
                                    ->placeholder('This quote is valid for 30 days.'),
                            ]),
                    ])
                    ->columns(1)
                    ->collapsed(),

                Forms\Components\Section::make('Status')
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->helperText('Only one company branding can be active at a time. Setting this as active will deactivate all others.')
                            ->default(true),
                    ]),
            ]);
    }
}
