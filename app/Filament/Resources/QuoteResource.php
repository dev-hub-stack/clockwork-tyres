<?php

namespace App\Filament\Resources;

use App\Filament\Resources\QuoteResource\Pages;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Enums\QuoteStatus;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Orders\Services\QuoteConversionService;
use App\Modules\Customers\Models\Customer;
use App\Modules\Products\Models\ProductVariant;
use App\Modules\Settings\Models\CompanyBranding;
use App\Modules\Settings\Models\TaxSetting;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Set;
use Filament\Forms\Get;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Number;
use UnitEnum;
use BackedEnum;

class QuoteResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';
    
    protected static ?string $navigationLabel = 'Quotes & Proformas';
    
    protected static string|UnitEnum|null $navigationGroup = 'Sales';
    
    protected static ?int $navigationSort = 1;
    
    protected static ?string $modelLabel = 'Quote';
    
    protected static ?string $pluralModelLabel = 'Quotes & Proformas';

    /**
     * Global scope to only show quotes
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->quotes() // Uses the scope from Order model
            ->with(['customer', 'warehouse'])
            ->latest('issue_date');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Quote Information')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Select::make('customer_id')
                                    ->label('Customer')
                                    ->relationship('customer', 'business_name')
                                    ->searchable(['business_name', 'first_name', 'last_name', 'email'])
                                    ->required()
                                    ->preload()
                                    ->getOptionLabelFromRecordUsing(function ($record) {
                                        $name = $record->business_name ?? $record->name ?? 'Unknown Customer';
                                        $type = $record->customer_type ? '(' . ucfirst($record->customer_type) . ')' : '';
                                        return trim("$name $type");
                                    })
                                    ->createOptionForm([
                                        Select::make('customer_type')
                                            ->label('Customer Type')
                                            ->options([
                                                'retail' => 'Retail',
                                                'dealer' => 'Dealer',
                                            ])
                                            ->default('retail')
                                            ->required(),
                                        TextInput::make('business_name')->required(),
                                        TextInput::make('first_name'),
                                        TextInput::make('last_name'),
                                        TextInput::make('email')->email(),
                                        TextInput::make('phone'),
                                    ])
                                    ->createOptionUsing(function (array $data) {
                                        return Customer::create($data)->id;
                                    })
                                    ->live()
                                    ->columnSpan(1),
                                
                                Select::make('representative_id')
                                    ->label('Sales Representative')
                                    ->relationship('representative', 'name')
                                    ->searchable(['name', 'email'])
                                    ->preload()
                                    ->columnSpan(1),
                                
                                Select::make('warehouse_id')
                                    ->label('Warehouse')
                                    ->relationship('warehouse', 'warehouse_name')
                                    ->required()
                                    ->preload()
                                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->warehouse_name ?? $record->code ?? 'Unknown Warehouse')
                                    ->columnSpan(1),
                            ]),
                        
                        Grid::make(3)
                            ->schema([
                                DatePicker::make('issue_date')
                                    ->label('Issue Date')
                                    ->required()
                                    ->default(now())
                                    ->columnSpan(1),
                                
                                DatePicker::make('valid_until')
                                    ->label('Valid Until')
                                    ->required()
                                    ->default(now()->addDays(30))
                                    ->columnSpan(1),
                                
                                Select::make('quote_status')
                                    ->label('Status')
                                    ->options([
                                        'draft' => 'Draft',
                                        'sent' => 'Sent',
                                        'approved' => 'Approved',
                                        'rejected' => 'Rejected',
                                        'expired' => 'Expired',
                                    ])
                                    ->default('draft')
                                    ->required()
                                    ->columnSpan(1),
                            ]),
                    ]),

                Section::make('Vehicle Information')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextInput::make('vehicle_year')
                                    ->label('Year')
                                    ->maxLength(10)
                                    ->placeholder('2024')
                                    ->columnSpan(1),
                                
                                TextInput::make('vehicle_make')
                                    ->label('Make')
                                    ->maxLength(100)
                                    ->placeholder('Ford')
                                    ->columnSpan(1),
                                
                                TextInput::make('vehicle_model')
                                    ->label('Model')
                                    ->maxLength(100)
                                    ->placeholder('Ranger')
                                    ->columnSpan(1),
                                
                                TextInput::make('vehicle_sub_model')
                                    ->label('Sub Model')
                                    ->maxLength(100)
                                    ->placeholder('Wildtrak')
                                    ->columnSpan(1),
                            ]),
                    ])
                    ->collapsible()
                    ->collapsed(false),

                Section::make('Line Items')
                    ->schema([
                        Repeater::make('items')
                            ->relationship('items')
                            ->schema([
                                Select::make('product_variant_id')
                                    ->label('Product')
                                    ->searchable()
                                    ->getSearchResultsUsing(function (string $search) {
                                        return ProductVariant::query()
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
                                                    '%s - %s | %s | %s | Size: %s | Bolt: %s | Offset: %s',
                                                    $variant->sku ?? 'NO-SKU',
                                                    $variant->product->brand?->name ?? 'N/A',
                                                    $variant->product->model?->name ?? 'N/A',
                                                    $variant->product->finish?->name ?? 'N/A',
                                                    $variant->size ?? 'N/A',
                                                    $variant->bolt_pattern ?? 'N/A',
                                                    $variant->offset ?? 'N/A'
                                                )
                                            ]);
                                    })
                                    ->getOptionLabelUsing(function ($value) {
                                        if (!$value) return 'Unknown';
                                        
                                        $variant = ProductVariant::with(['product.brand', 'product.model', 'product.finish'])->find($value);
                                        if (!$variant || !$variant->product) return 'Unknown Product';
                                        
                                        return sprintf(
                                            '%s - %s | %s | %s',
                                            $variant->sku ?? 'NO-SKU',
                                            $variant->product->brand?->name ?? 'N/A',
                                            $variant->product->model?->name ?? 'N/A',
                                            $variant->product->finish?->name ?? 'N/A'
                                        );
                                    })
                                    ->afterStateUpdated(function ($state, $set, $get) {
                                        if ($state) {
                                            $variant = ProductVariant::with('product')->find($state);
                                            if ($variant) {
                                                // Get customer to check if dealer
                                                $customerId = $get('../../customer_id');
                                                $customer = $customerId ? Customer::find($customerId) : null;
                                                
                                                // Determine price based on customer type
                                                $price = 0;
                                                if ($customer && $customer->isDealer()) {
                                                    // Dealer: use price (dealer/cost price)
                                                    $price = floatval($variant->price ?? 0);
                                                } else {
                                                    // Retail: use UAE retail price → US retail price → base price
                                                    $price = floatval($variant->uae_retail_price ?? $variant->us_retail_price ?? $variant->price ?? 0);
                                                }
                                                
                                                $set('unit_price', $price);
                                                $set('quantity', 1);
                                            }
                                        }
                                    })
                                    ->live()
                                    ->required()
                                    ->columnSpanFull(),
                                
                                TextInput::make('quantity')
                                    ->label('Quantity')
                                    ->numeric()
                                    ->default(1)
                                    ->required()
                                    ->minValue(1)
                                    ->live()
                                    ->reactive(),
                                
                                TextInput::make('unit_price')
                                    ->label('Unit Price')
                                    ->numeric()
                                    ->prefix('AED')
                                    ->required()
                                    ->live()
                                    ->reactive(),
                                
                                TextInput::make('discount')
                                    ->label('Discount')
                                    ->numeric()
                                    ->prefix('AED')
                                    ->default(0)
                                    ->live()
                                    ->reactive(),
                                
                                Placeholder::make('line_total')
                                    ->label('Line Total')
                                    ->content(function ($get) {
                                        $qty = floatval($get('quantity') ?? 0);
                                        $price = floatval($get('unit_price') ?? 0);
                                        $discount = floatval($get('discount') ?? 0);
                                        $total = ($qty * $price) - $discount;
                                        return Number::currency($total, 'AED');
                                    }),
                            ])
                            ->columns(4)
                            ->defaultItems(1)
                            ->addActionLabel('Add Line Item')
                            ->reorderable()
                            ->collapsible(),
                    ]),

                Section::make('Totals & Notes')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                // Left column - Notes
                                Grid::make(1)
                                    ->schema([
                                        Textarea::make('order_notes')
                                            ->label('Customer Notes')
                                            ->rows(3),
                                        
                                        Textarea::make('internal_notes')
                                            ->label('Internal Notes')
                                            ->rows(3),
                                    ])
                                    ->columnSpan(1),
                                
                                // Right column - Totals  
                                Grid::make(1)
                                    ->schema([
                                        Placeholder::make('sub_total_display')
                                            ->label('Subtotal')
                                            ->content(function ($get, $record) {
                                                // Access items from the record or form state
                                                $items = $record ? $record->items : ($get('items') ?? []);
                                                $subtotal = 0;
                                                
                                                foreach ($items as $item) {
                                                    $qty = floatval($item['quantity'] ?? 0);
                                                    $price = floatval($item['unit_price'] ?? 0);
                                                    $discount = floatval($item['discount'] ?? 0);
                                                    $subtotal += ($qty * $price) - $discount;
                                                }
                                                
                                                return 'AED ' . number_format($subtotal, 2);
                                            }),
                                        
                                        Placeholder::make('vat_display')
                                            ->label(function () {
                                                $taxSetting = TaxSetting::getDefault();
                                                $taxName = $taxSetting ? $taxSetting->name : 'VAT';
                                                $taxRate = $taxSetting ? $taxSetting->rate : 5;
                                                return "{$taxName} ({$taxRate}%)";
                                            })
                                            ->content(function ($get, $record) {
                                                // Get tax rate from settings
                                                $taxSetting = TaxSetting::getDefault();
                                                $taxRate = $taxSetting ? floatval($taxSetting->rate) : 5;
                                                
                                                // Calculate subtotal
                                                $items = $record ? $record->items : ($get('items') ?? []);
                                                $subtotal = 0;
                                                
                                                foreach ($items as $item) {
                                                    $qty = floatval($item['quantity'] ?? 0);
                                                    $price = floatval($item['unit_price'] ?? 0);
                                                    $discount = floatval($item['discount'] ?? 0);
                                                    $subtotal += ($qty * $price) - $discount;
                                                }
                                                
                                                // Calculate VAT
                                                $vat = $subtotal * ($taxRate / 100);
                                                
                                                return 'AED ' . number_format($vat, 2);
                                            }),
                                        
                                        Hidden::make('vat')
                                            ->default(0),
                                        
                                        TextInput::make('shipping')
                                            ->label('Shipping')
                                            ->numeric()
                                            ->prefix('AED')
                                            ->default(0)
                                            ->live()
                                            ->afterStateUpdated(function ($get, $set) {
                                                // Get tax rate from settings
                                                $taxSetting = TaxSetting::getDefault();
                                                $taxRate = $taxSetting ? floatval($taxSetting->rate) : 5;
                                                
                                                // Recalculate total when shipping changes
                                                $items = $get('items') ?? [];
                                                $subtotal = 0;
                                                
                                                foreach ($items as $item) {
                                                    $qty = floatval($item['quantity'] ?? 0);
                                                    $price = floatval($item['unit_price'] ?? 0);
                                                    $discount = floatval($item['discount'] ?? 0);
                                                    $subtotal += ($qty * $price) - $discount;
                                                }
                                                
                                                $vat = $subtotal * ($taxRate / 100);
                                                $shipping = floatval($get('shipping') ?? 0);
                                                $total = $subtotal + $vat + $shipping;
                                                
                                                $set('sub_total', $subtotal);
                                                $set('vat', $vat);
                                                $set('total', $total);
                                            }),
                                        
                                        Placeholder::make('total_display')
                                            ->label('Total')
                                            ->content(function ($get, $record) {
                                                // Get tax rate from settings
                                                $taxSetting = TaxSetting::getDefault();
                                                $taxRate = $taxSetting ? floatval($taxSetting->rate) : 5;
                                                
                                                // Calculate subtotal
                                                $items = $record ? $record->items : ($get('items') ?? []);
                                                $subtotal = 0;
                                                
                                                foreach ($items as $item) {
                                                    $qty = floatval($item['quantity'] ?? 0);
                                                    $price = floatval($item['unit_price'] ?? 0);
                                                    $discount = floatval($item['discount'] ?? 0);
                                                    $subtotal += ($qty * $price) - $discount;
                                                }
                                                
                                                // Calculate VAT and total
                                                $vat = $subtotal * ($taxRate / 100);
                                                $shipping = floatval($get('shipping') ?? $record->shipping ?? 0);
                                                $total = $subtotal + $vat + $shipping;
                                                
                                                return 'AED ' . number_format($total, 2);
                                            })
                                            ->extraAttributes(['class' => 'font-bold text-lg']),
                                        
                                        Hidden::make('sub_total')->default(0),
                                        Hidden::make('total')->default(0),
                                    ])
                                    ->columnSpan(1),
                            ]),
                    ]),
                    
                Hidden::make('document_type')
                    ->default('quote'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('issue_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),
                
                TextColumn::make('quote_number')
                    ->label('Quote #')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                
                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable(['business_name', 'first_name', 'last_name'])
                    ->sortable(),
                
                BadgeColumn::make('quote_status')
                    ->label('Status')
                    ->colors([
                        'secondary' => 'draft',
                        'primary' => 'sent',
                        'success' => 'approved',
                        'danger' => 'rejected',
                        'warning' => 'expired',
                    ]),
                
                TextColumn::make('total')
                    ->label('Amount')
                    ->money('AED')
                    ->sortable(),
                
                TextColumn::make('valid_until')
                    ->label('Valid Until')
                    ->date()
                    ->sortable(),
                
                TextColumn::make('warehouse.warehouse_name')
                    ->label('Warehouse')
                    ->sortable()
                    ->toggleable(),
                
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('quote_status')
                    ->label('Status')
                    ->options([
                        'draft' => 'Draft',
                        'sent' => 'Sent',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        'expired' => 'Expired',
                    ]),
                
                SelectFilter::make('customer_id')
                    ->label('Customer')
                    ->relationship('customer', 'business_name')
                    ->searchable()
                    ->preload()
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->business_name ?? $record->name ?? 'Unknown Customer'),
                
                Filter::make('issue_date')
                    ->form([
                        DatePicker::make('issued_from')
                            ->label('Issued From'),
                        DatePicker::make('issued_until')
                            ->label('Issued Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['issued_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('issue_date', '>=', $date),
                            )
                            ->when(
                                $data['issued_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('issue_date', '<=', $date),
                            );
                    }),
            ])
            ->recordActions([
                Action::make('preview')
                    ->label('Preview')
                    ->icon('heroicon-o-eye')
                    ->slideOver()
                    ->modalWidth('7xl')
                    ->modalContent(function ($record) {
                        // Get settings
                        $companyBranding = \App\Modules\Settings\Models\CompanyBranding::getActive();
                        $taxSetting = \App\Modules\Settings\Models\TaxSetting::getDefault();
                        
                        return view('templates.invoice-preview', [
                            'record' => $record,
                            'documentType' => 'quote',
                            'companyName' => $companyBranding->company_name ?? 'TunerStop LLC',
                            'companyAddress' => $companyBranding->company_address ?? '',
                            'companyPhone' => $companyBranding->company_phone ?? '',
                            'companyEmail' => $companyBranding->company_email ?? '',
                            'taxNumber' => $companyBranding->tax_registration_number ?? '',
                            'logo' => $companyBranding ? $companyBranding->logo_url : null,
                            'currency' => 'AED',
                            'vatRate' => $taxSetting ? $taxSetting->rate : 5,
                        ]);
                    }),
                
                Action::make('send')
                    ->label('Send')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('primary')
                    ->visible(fn($record) => $record->quote_status->value === 'draft')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        app(OrderService::class)->sendQuote($record);
                    }),
                
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn($record) => $record->quote_status->value === 'sent')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        app(OrderService::class)->approveQuote($record);
                    }),
                
                Action::make('convert')
                    ->label('Convert to Invoice')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('warning')
                    ->visible(fn($record) => $record->canConvertToInvoice())
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $invoice = app(QuoteConversionService::class)->convertQuoteToInvoice($record);
                        // Redirect to invoice edit page (will be created)
                        return redirect()->route('filament.admin.resources.invoices.edit', ['record' => $invoice]);
                    }),
                
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('issue_date', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListQuotes::route('/'),
            'create' => Pages\CreateQuote::route('/create'),
            'view' => Pages\ViewQuote::route('/{record}'),
            'edit' => Pages\EditQuote::route('/{record}/edit'),
        ];
    }
}
