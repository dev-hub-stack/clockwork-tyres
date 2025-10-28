<?php

namespace App\Filament\Resources\ConsignmentResource\Schemas;

use App\Modules\Consignments\Enums\ConsignmentStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class ConsignmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Consignment Information Section
                Section::make('Consignment Information')
                    ->schema([
                        TextInput::make('consignment_number')
                            ->label('Consignment Number')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('Auto-generated')
                            ->helperText('Will be auto-generated upon creation'),
                        
                        Select::make('customer_id')
                            ->label('Customer')
                            ->relationship('customer', 'business_name')
                            ->searchable(['business_name', 'first_name', 'last_name', 'email'])
                            ->getOptionLabelFromRecordUsing(function ($record) {
                                $name = $record->business_name ?? ($record->first_name . ' ' . $record->last_name) ?? 'Unknown Customer';
                                return trim($name);
                            })
                            ->preload()
                            ->required()
                            ->live()
                            ->helperText('Select the customer receiving the consignment'),
                        
                        Select::make('warehouse_id')
                            ->label('Warehouse')
                            ->relationship('warehouse', 'warehouse_name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('Warehouse where items are sent from'),
                        
                        Select::make('representative_id')
                            ->label('Sales Representative')
                            ->relationship('representative', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText('Sales rep responsible for this consignment'),
                        
                        Select::make('status')
                            ->label('Status')
                            ->options(ConsignmentStatus::class)
                            ->default(ConsignmentStatus::DRAFT)
                            ->required(),
                        
                        DatePicker::make('issue_date')
                            ->label('Issue Date')
                            ->default(now())
                            ->required(),
                        
                        DatePicker::make('expected_return_date')
                            ->label('Expected Return Date')
                            ->helperText('When items are expected back (if not sold)'),
                        
                        TextInput::make('tracking_number')
                            ->label('Tracking Number')
                            ->maxLength(255),
                    ])
                    ->columns(2),

                // Vehicle Information Section
                Section::make('Vehicle Information')
                    ->schema([
                        TextInput::make('vehicle_year')
                            ->label('Year')
                            ->numeric()
                            ->maxLength(4)
                            ->placeholder('2024'),
                        
                        TextInput::make('vehicle_make')
                            ->label('Make')
                            ->maxLength(100)
                            ->placeholder('Toyota'),
                        
                        TextInput::make('vehicle_model')
                            ->label('Model')
                            ->maxLength(100)
                            ->placeholder('Camry'),
                        
                        TextInput::make('vehicle_sub_model')
                            ->label('Sub Model')
                            ->maxLength(100)
                            ->placeholder('SE, XLE, etc.'),
                    ])
                    ->columns(4)
                    ->collapsible(),

                // Consignment Items Section
                Section::make('Consignment Items')
                    ->schema([
                        Repeater::make('items')
                            ->label('')
                            ->relationship('items')
                            ->schema([
                                Select::make('product_variant_id')
                                    ->label('Product')
                                    ->searchable()
                                    ->getSearchResultsUsing(function (string $search) {
                                        return \App\Modules\Products\Models\ProductVariant::query()
                                            ->with(['product.brand', 'product.model', 'product.finish'])
                                            ->where(function ($query) use ($search) {
                                                $query->where('sku', 'like', "%{$search}%")
                                                    ->orWhereHas('product', function ($q) use ($search) {
                                                        $q->where('name', 'like', "%{$search}%")
                                                          ->orWhereHas('brand', fn($b) => $b->where('name', 'like', "%{$search}%"))
                                                          ->orWhereHas('model', fn($m) => $m->where('name', 'like', "%{$search}%"))
                                                          ->orWhereHas('finish', fn($f) => $f->where('name', 'like', "%{$search}%"));
                                                    })
                                                    ->orWhere('size', 'like', "%{$search}%")
                                                    ->orWhere('bolt_pattern', 'like', "%{$search}%")
                                                    ->orWhere('offset', 'like', "%{$search}%");
                                            })
                                            ->limit(50)
                                            ->get()
                                            ->filter(fn($variant) => $variant->product !== null && $variant->sku !== null)
                                            ->mapWithKeys(fn($variant) => [
                                                $variant->id => sprintf(
                                                    '%s - %s | %s | %s',
                                                    $variant->sku ?? 'NO-SKU',
                                                    $variant->product->brand?->name ?? 'N/A',
                                                    $variant->product->model?->name ?? 'N/A',
                                                    $variant->product->finish?->name ?? 'N/A'
                                                )
                                            ]);
                                    })
                                    ->getOptionLabelUsing(function ($value) {
                                        if (!$value) return 'Unknown';
                                        
                                        $variant = \App\Modules\Products\Models\ProductVariant::with(['product.brand', 'product.model', 'product.finish'])->find($value);
                                        if (!$variant || !$variant->product) return 'Unknown Product';
                                        
                                        return sprintf(
                                            '%s - %s | %s | %s',
                                            $variant->sku ?? 'NO-SKU',
                                            $variant->product->brand?->name ?? 'N/A',
                                            $variant->product->model?->name ?? 'N/A',
                                            $variant->product->finish?->name ?? 'N/A'
                                        );
                                    })
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        if ($state) {
                                            $variant = \App\Modules\Products\Models\ProductVariant::with('product')->find($state);
                                            if ($variant) {
                                                $set('sku', $variant->sku);
                                                $set('product_name', $variant->product->name ?? '');
                                                $set('brand_name', $variant->product->brand->name ?? '');
                                                $set('price', $variant->price ?? 0);
                                            }
                                        }
                                    })
                                    ->live()
                                    ->required()
                                    ->columnSpan(2),
                                
                                Hidden::make('sku'),
                                Hidden::make('product_name'),
                                Hidden::make('brand_name'),
                                
                                TextInput::make('quantity_sent')
                                    ->label('Qty to Send')
                                    ->numeric()
                                    ->default(1)
                                    ->required()
                                    ->minValue(1),
                                
                                TextInput::make('price')
                                    ->label('Price')
                                    ->numeric()
                                    ->prefix(fn () => \App\Modules\Settings\Models\CurrencySetting::getBase()?->currency_symbol ?? 'AED')
                                    ->required(),
                                
                                Textarea::make('notes')
                                    ->label('Item Notes')
                                    ->rows(2)
                                    ->columnSpanFull(),
                            ])
                            ->columns(3)
                            ->defaultItems(1)
                            ->addActionLabel('Add Item')
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => 
                                $state['product_name'] ?? 'New Item'
                            ),
                    ]),

                // Financial Information Section
                Section::make('Financial Information')
                    ->schema([
                        Hidden::make('tax_rate')
                            ->default(fn () => \App\Modules\Settings\Models\TaxSetting::getDefault()?->rate ?? 5),
                        
                        Grid::make(3)
                            ->schema([
                                TextInput::make('sub_total')
                                    ->label('Subtotal')
                                    ->numeric()
                                    ->prefix(fn () => \App\Modules\Settings\Models\CurrencySetting::getBase()?->currency_symbol ?? 'AED')
                                    ->disabled()
                                    ->dehydrated(true)
                                    ->default(0)
                                    ->helperText('Calculated from items'),
                                
                                TextInput::make('tax')
                                    ->label('Tax')
                                    ->numeric()
                                    ->prefix(fn () => \App\Modules\Settings\Models\CurrencySetting::getBase()?->currency_symbol ?? 'AED')
                                    ->disabled()
                                    ->dehydrated(true)
                                    ->default(0)
                                    ->helperText(function () {
                                        $taxSetting = \App\Modules\Settings\Models\TaxSetting::getDefault();
                                        $taxRate = $taxSetting ? $taxSetting->rate : 5;
                                        return "Calculated from subtotal at {$taxRate}%";
                                    }),
                                
                                TextInput::make('total')
                                    ->label('Total')
                                    ->numeric()
                                    ->prefix(fn () => \App\Modules\Settings\Models\CurrencySetting::getBase()?->currency_symbol ?? 'AED')
                                    ->disabled()
                                    ->dehydrated(true)
                                    ->default(0)
                                    ->helperText('Subtotal + Tax - Discount + Shipping'),
                            ]),
                        
                        Grid::make(2)
                            ->schema([
                                TextInput::make('discount')
                                    ->label('Discount')
                                    ->numeric()
                                    ->prefix(fn () => \App\Modules\Settings\Models\CurrencySetting::getBase()?->currency_symbol ?? 'AED')
                                    ->default(0),
                                
                                TextInput::make('shipping_cost')
                                    ->label('Shipping Cost')
                                    ->numeric()
                                    ->prefix(fn () => \App\Modules\Settings\Models\CurrencySetting::getBase()?->currency_symbol ?? 'AED')
                                    ->default(0),
                            ]),
                    ])
                    ->collapsible(),

                // Notes Section
                Section::make('Notes')
                    ->schema([
                        Textarea::make('notes')
                            ->label('Customer Notes')
                            ->rows(3)
                            ->helperText('Notes visible to customer'),
                        
                        Textarea::make('internal_notes')
                            ->label('Internal Notes')
                            ->rows(3)
                            ->helperText('Internal notes (not visible to customer)'),
                    ])
                    ->columns(2)
                    ->collapsible(),
            ]);
    }
}
