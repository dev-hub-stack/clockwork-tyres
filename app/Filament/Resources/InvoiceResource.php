<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InvoiceResource\Pages;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\Payment;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Customers\Models\Customer;
use App\Modules\Products\Models\ProductVariant;
use App\Modules\Settings\Models\CompanyBranding;
use App\Modules\Settings\Models\CurrencySetting;
use App\Modules\Settings\Models\TaxSetting;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Set;
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
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Number;
use UnitEnum;
use BackedEnum;

class InvoiceResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-currency-dollar';
    
    protected static ?string $navigationLabel = 'Invoices';
    
    protected static string|UnitEnum|null $navigationGroup = 'Sales';
    
    protected static ?int $navigationSort = 2;
    
    protected static ?string $modelLabel = 'Invoice';
    
    protected static ?string $pluralModelLabel = 'Invoices';

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_invoices') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create_invoices') ?? false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->can('edit_invoices') ?? false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->can('delete_invoices') ?? false;
    }



    /**
     * Global scope to only show invoices
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->invoices() // Uses the scope from Order model
            ->with(['customer', 'warehouse', 'payments'])
            ->latest('issue_date');
    }

    /**
     * Calculate totals based on items and shipping.
     *
     * Standard e-commerce formula:
     *   items_total = Σ (qty × price − item_discount)
     *   subtotal    = items_total + shipping
     *   tax         = subtotal × rate%
     *   total       = subtotal + tax
     */
    public static function calculateValues(array $items, float $shipping): array
    {
        $taxSetting = TaxSetting::getDefault();
        $taxRate    = $taxSetting ? floatval($taxSetting->rate) : 5;
        $multiplier = 1 + ($taxRate / 100);

        $inclGross = 0.0;
        $exclNet   = 0.0;

        foreach ($items as $item) {
            $qty          = floatval($item['quantity'] ?? 0);
            $price        = floatval($item['unit_price'] ?? 0);
            $lineDiscount = floatval($item['discount'] ?? 0);
            $taxInclusive = $item['tax_inclusive'] ?? true;
            $lineTotal    = ($qty * $price) - $lineDiscount;

            if ($taxInclusive) {
                $inclGross += $lineTotal;
            } else {
                $exclNet += $lineTotal;
            }
        }

        // Inclusive: extract tax
        $inclTax = $inclGross - ($inclGross / $multiplier);
        $inclNet = $inclGross / $multiplier;

        // Exclusive + shipping: add tax on top
        $exclBase = $exclNet + $shipping;
        $exclTax  = $exclBase * ($taxRate / 100);

        return [
            'sub_total'   => round($inclNet  + $exclBase, 2),
            'items_total' => round($inclGross + $exclNet,  2),
            'vat'         => round($inclTax   + $exclTax,  2),
            'total'       => round($inclGross  + $exclBase + $exclTax, 2),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Invoice Information')
                    ->schema([
                        // Full width customer field
                        Select::make('customer_id')
                            ->label('Customer')
                            ->relationship('customer', 'business_name')
                            ->searchable(['business_name', 'first_name', 'last_name', 'email'])
                            ->required()
                            ->getOptionLabelFromRecordUsing(function ($record) {
                                $name = $record->business_name ?? $record->name ?? 'Unknown Customer';
                                $type = $record->customer_type ? '(' . ucfirst($record->customer_type) . ')' : '';
                                return trim("$name $type");
                            })
                            ->createOptionForm([
                                Select::make('customer_type')
                                    ->label('Customer Type')
                                    ->options([
                                        'retail'    => 'Retail',
                                        'wholesale' => 'Wholesale',
                                    ])
                                    ->default('retail')
                                    ->required(),
                                TextInput::make('business_name')
                                    ->label('Customer name')
                                    ->required(),
                                TextInput::make('email')->email(),
                                TextInput::make('phone'),
                                TextInput::make('address')
                                    ->label('Address'),
                                TextInput::make('city')
                                    ->label('City'),
                                Select::make('country_id')
                                    ->label('Country')
                                    ->relationship('country', 'name')
                                    ->searchable()
                                    ->preload(),
                            ])
                            ->createOptionUsing(function (array $data) {
                                return \App\Modules\Customers\Models\Customer::create($data)->id;
                            })
                            ->live()
                            ->columnSpanFull(),
                        
                        Grid::make(2)
                            ->schema([
                                Select::make('representative_id')
                                    ->label('Sales Representative')
                                    ->relationship('representative', 'name')
                                    ->searchable(['name', 'email'])
                                    ->preload()
                                    ->columnSpan(1),
                                
                                Select::make('channel')
                                    ->label('Channel')
                                    ->options([
                                        'retail' => 'Retail',
                                        'wholesale' => 'Wholesale',
                                    ])
                                    ->default('retail')
                                    ->required()
                                    ->columnSpan(1),
                            ]),
                        
                        Grid::make(2)
                            ->schema([
                                DatePicker::make('issue_date')
                                    ->label('Issue Date')
                                    ->required()
                                    ->default(now())
                                    ->columnSpan(1),
                                
                                DatePicker::make('valid_until')
                                    ->label('Due Date')
                                    ->required()
                                    ->default(now()->addDays(30))
                                    ->columnSpan(1),
                            ]),

                        Select::make('order_status')
                            ->label('Order Status')
                            ->options([
                                'processing' => 'Processing',
                                'shipped'    => 'Shipped',
                                'delivered'  => 'Delivered',
                                'cancelled'  => 'Cancelled',
                            ])
                            ->default('processing')
                            ->required()
                            ->hiddenOn('view')
                            ->columnSpan(1),
                        
                        Select::make('payment_status')
                            ->label('Payment Status')
                            ->options([
                                'pending' => 'Pending',
                                'partial' => 'Partial',
                                'paid' => 'Paid',
                                'refunded' => 'Refunded',
                                'failed' => 'Failed',
                            ])
                            ->default('pending')
                            ->disabled() // Auto-calculated from payments
                            ->dehydrated(false)
                            ->hiddenOn('view')
                            ->columnSpan(1),
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
                                                    $variant->finish ?? 'N/A',
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
                                            $variant->finish ?? 'N/A'
                                        );
                                    })
                                    ->afterStateUpdated(function ($state, Set $set) {
                                        if ($state) {
                                            $variant = ProductVariant::with('product')->find($state);
                                            if ($variant) {
                                                // Use uae_retail_price from tunerstop-admin
                                                $price = floatval($variant->uae_retail_price ?? 0);
                                                
                                                // Apply Dealer Pricing if applicable
                                                $customerId = $set->get('../../customer_id');
                                                if ($customerId) {
                                                    $customer = \App\Modules\Customers\Models\Customer::find($customerId);
                                                    if ($customer && $customer->isDealer()) {
                                                        $dealerService = new \App\Modules\Customers\Services\DealerPricingService();
                                                        $pricing = $dealerService->calculateProductPrice(
                                                            $customer,
                                                            $price,
                                                            $variant->product->model_id ?? null,
                                                            $variant->product->brand_id ?? null
                                                        );
                                                        
                                                        // If discount applied, update price and maybe show info
                                                        if ($pricing['discount_amount'] > 0) {
                                                            $price = $pricing['final_price'];
                                                            // We could set a discount field if we wanted to show it explicitly, 
                                                            // but usually dealer price is the unit price.
                                                            // Or we can set the discount field.
                                                            // For invoices, we usually set unit_price to base and discount to amount.
                                                            $set('discount', $pricing['discount_amount']);
                                                        }
                                                    }
                                                }
                                                
                                                $set('unit_price', $price);
                                                $set('quantity', 1);
                                                
                                                // Set tax_inclusive from system setting
                                                $taxSetting = \App\Modules\Settings\Models\TaxSetting::getDefault();
                                                $set('tax_inclusive', $taxSetting ? $taxSetting->tax_inclusive_default : true);
                                            }
                                        }
                                    })
                                    ->live()
                                    ->required()
                                    ->columnSpanFull(),
                                
                                Select::make('warehouse_id')
                                    ->label('Warehouse')
                                    ->options(function ($get) {
                                        $variantId = $get('product_variant_id');
                                        
                                        if (!$variantId) {
                                            return ['' => 'Select product first'];
                                        }
                                        
                                        // Get inventory per warehouse for this variant
                                        $inventories = \App\Modules\Inventory\Models\ProductInventory::where('product_variant_id', $variantId)
                                            ->with('warehouse')
                                            ->get();
                                        
                                        $options = [];
                                        
                                        foreach ($inventories as $inv) {
                                            if (!$inv->warehouse) continue;
                                            
                                            $warehouse = $inv->warehouse;
                                            $available = ($inv->quantity ?? 0) + ($inv->eta_qty ?? 0);
                                            
                                            // Show all warehouses, even with 0 stock
                                            $label = sprintf(
                                                '%s - %d available (%d in stock%s)',
                                                $warehouse->name,
                                                $available,
                                                $inv->quantity ?? 0,
                                                ($inv->eta_qty ?? 0) > 0 ? ", {$inv->eta_qty} expected" : ''
                                            );
                                            
                                            $options[$warehouse->id] = $label;
                                        }
                                        
                                        // Always add non-stock option
                                        $options['non_stock'] = '⚡ Non-Stock (Special Order) - Unlimited';
                                        
                                        return $options;
                                    })
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, $get, $set) {
                                        // Validate quantity against available stock
                                        if (!$state || $state === 'non_stock') return;
                                        
                                        $variantId = $get('product_variant_id');
                                        $quantity = intval($get('quantity') ?? 0);
                                        
                                        if ($variantId && $quantity > 0) {
                                            $inventory = \App\Modules\Inventory\Models\ProductInventory::where('product_variant_id', $variantId)
                                                ->where('warehouse_id', $state)
                                                ->first();
                                            
                                            if ($inventory) {
                                                $available = ($inventory->quantity ?? 0) + ($inventory->eta_qty ?? 0);
                                                
                                                if ($quantity > $available) {
                                                    \Filament\Notifications\Notification::make()
                                                        ->warning()
                                                        ->title('Low Stock Warning')
                                                        ->body("Requested {$quantity} but only {$available} available in this warehouse")
                                                        ->send();
                                                }
                                            }
                                        }
                                    })
                                    ->dehydrateStateUsing(fn ($state) => $state === 'non_stock' ? null : $state)
                                    ->required()
                                    ->helperText('Select warehouse for this item')
                                    ->columnSpan(2),
                                                                TextInput::make('quantity')
                                    ->label('Quantity')
                                    ->numeric()
                                    ->default(1)
                                    ->required()
                                    ->minValue(1)
                                    ->live(onBlur: true)
                                    ->reactive(),
                                
                                TextInput::make('unit_price')
                                    ->label('Unit Price')
                                    ->numeric()
                                    ->prefix(fn() => CurrencySetting::getBase()?->currency_symbol ?? 'AED')
                                    ->required()
                                    ->live(onBlur: true)
                                    ->reactive(),
                                
                                TextInput::make('discount')
                                    ->label('Discount')
                                    ->numeric()
                                    ->prefix(fn() => CurrencySetting::getBase()?->currency_symbol ?? 'AED')
                                    ->default(0)
                                    ->live(onBlur: true)
                                    ->reactive(),
                                
                                \Filament\Forms\Components\Toggle::make('tax_inclusive')
                                    ->label('Tax Inclusive')
                                    ->default(function () {
                                        $taxSetting = \App\Modules\Settings\Models\TaxSetting::getDefault();
                                        return $taxSetting ? $taxSetting->tax_inclusive_default : true;
                                    })
                                    ->live()
                                    ->reactive()
                                    ->dehydrated()
                                    ->inline(false)
                                    ->helperText('Is the price tax-inclusive?')
                                    ->afterStateUpdated(function ($get, $set) {
                                        $items = $get('../../items') ?? [];
                                        $shipping = floatval($get('../../shipping') ?? 0); 
                                        $totals = self::calculateValues($items, $shipping);
                                        
                                        // InvoiceResource doesn't seem to have hidden total fields in the same way, 
                                        // but we should trigger updates if there are any dependent fields.
                                        // Based on the file content, there are placeholders in the 'Totals' section.
                                        // Since placeholders use $get('items'), updating this state should trigger them if they are reactive.
                                        // However, placeholders are not inputs, so we can't 'set' them.
                                        // We just need to ensure the form state updates so the placeholders re-render.
                                    }),
                                
                                Placeholder::make('line_total')
                                    ->label('Line Total')
                                    ->content(function ($get) {
                                        $currency = CurrencySetting::getBase();
                                        $currencyCode = $currency ? $currency->currency_code : 'AED';
                                        $qty = floatval($get('quantity') ?? 0);
                                        $price = floatval($get('unit_price') ?? 0);
                                        $discount = floatval($get('discount') ?? 0);
                                        $total = ($qty * $price) - $discount;
                                        return Number::currency($total, $currencyCode);
                                    }),
                            ])
                            ->columns(1)
                            ->defaultItems(1)
                            ->addActionLabel('Add Line Item')
                            ->reorderable()
                            ->collapsible(),
                    ]),

                Section::make('Shipping & Notes')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('tracking_number')
                                    ->label('Tracking Number')
                                    ->maxLength(255)
                                    ->columnSpan(1),
                                
                                TextInput::make('shipping_carrier')
                                    ->label('Shipping Carrier')
                                    ->maxLength(255)
                                    ->columnSpan(1),
                                
                                DatePicker::make('shipped_at')
                                    ->label('Shipped Date')
                                    ->disabled()
                                    ->columnSpan(1),
                            ]),
                        
                        TextInput::make('tracking_url')
                            ->label('Tracking URL')
                            ->url()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        
                        Grid::make(2)
                            ->schema([
                                Textarea::make('order_notes')
                                    ->label('Customer Notes')
                                    ->rows(3)
                                    ->columnSpan(1),
                                
                                Textarea::make('internal_notes')
                                    ->label('Internal Notes')
                                    ->rows(3)
                                    ->columnSpan(1),
                            ]),
                    ]),
                    
                Section::make('Totals')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                Placeholder::make('sub_total_preview')
                                    ->label('Sub Total')
                                    ->content(function ($get) {
                                        $currency = \App\Modules\Settings\Models\CurrencySetting::getBase();
                                        $code = $currency?->currency_code ?? 'AED';
                                        $items = $get('items') ?? [];
                                        $shipping = floatval($get('shipping') ?? 0);
                                        $totals = self::calculateValues($items, $shipping);
                                        return Number::currency($totals['sub_total'], $code);
                                    })
                                    ->helperText('Items − Discounts'),

                                Placeholder::make('vat_preview')
                                    ->label(function () {
                                        $taxSetting = TaxSetting::getDefault();
                                        $taxName = $taxSetting ? $taxSetting->name : 'VAT';
                                        $taxRate = $taxSetting ? $taxSetting->rate : 5;
                                        return "{$taxName} ({$taxRate}%)";
                                    })
                                    ->content(function ($get) {
                                        $currency = \App\Modules\Settings\Models\CurrencySetting::getBase();
                                        $code = $currency?->currency_code ?? 'AED';
                                        $items = $get('items') ?? [];
                                        $shipping = floatval($get('shipping') ?? 0);
                                        $totals = self::calculateValues($items, $shipping);
                                        return Number::currency($totals['vat'], $code);
                                    })
                                    ->helperText('Subtotal × rate%'),
                                    
                                TextInput::make('shipping')
                                    ->label('Shipping')
                                    ->numeric()
                                    ->prefix(fn() => CurrencySetting::getBase()?->currency_symbol ?? 'AED')
                                    ->default(0)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($get, $set) {
                                        // Values are purely computed for placeholders here.
                                    }),

                                Placeholder::make('total_preview')
                                    ->label('Total Amount')
                                    ->content(function ($get) {
                                        $currency = \App\Modules\Settings\Models\CurrencySetting::getBase();
                                        $code = $currency?->currency_code ?? 'AED';
                                        $items = $get('items') ?? [];
                                        $shipping = floatval($get('shipping') ?? 0);
                                        $totals = self::calculateValues($items, $shipping);
                                        return Number::currency($totals['total'], $code);
                                    })
                                    ->helperText('Subtotal + Tax')
                                    ->extraAttributes(['class' => 'font-bold text-lg']),
                            ]),
                    ]),

                Hidden::make('document_type')
                    ->default('invoice'),
                    
                Hidden::make('tax_inclusive')
                    ->default(function () {
                        $taxSetting = \App\Modules\Settings\Models\TaxSetting::getDefault();
                        return $taxSetting ? $taxSetting->tax_inclusive_default : true;
                    }),
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
                
                TextColumn::make('order_number')
                    ->label('Invoice #')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                
                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable(['business_name', 'first_name', 'last_name', 'email', 'phone'])
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy(
                            \DB::raw('COALESCE(customers.business_name, CONCAT(customers.first_name, " ", customers.last_name))'),
                            $direction
                        );
                    }),
                
                TextColumn::make('representative.name')
                    ->label('Sales Rep')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                
                TextColumn::make('payment_status')
                    ->label('Payment')
                    ->badge()
                    ->color(fn ($state) => $state?->color())
                    ->action(
                        Action::make('viewPayments')
                            ->label('Payment History')
                            ->modalHeading('Payment History')
                            ->modalWidth('4xl')
                            ->modalContent(fn ($record) => view('filament.resources.invoices.payment-history', ['payments' => $record->payments]))
                            ->modalSubmitAction(false)
                            ->modalCancelAction(fn ($action) => $action->label('Close'))
                    ),
                
                BadgeColumn::make('order_status')
                    ->label('Status')
                    ->color(fn ($state) => $state?->color()),
                
                TextColumn::make('total')
                    ->label('Amount')
                    ->money(fn() => CurrencySetting::getBase()?->currency_code ?? 'AED')
                    ->sortable(),
                
                TextColumn::make('balance')
                    ->label('Balance')
                    ->money(fn() => CurrencySetting::getBase()?->currency_code ?? 'AED')
                    ->getStateUsing(fn($record) => $record->outstanding_amount)
                    ->color(fn($state) => $state > 0 ? 'danger' : 'success')
                    ->sortable(),
                
                TextColumn::make('gross_profit')
                    ->label('Profit')
                    ->money(fn() => CurrencySetting::getBase()?->currency_code ?? 'AED')
                    ->color(fn($state) => $state >= 0 ? 'success' : 'danger')
                    ->visible(fn() => auth()->user()?->can('view_expenses') ?? false)
                    ->sortable()
                    ->toggleable()
                    ->tooltip(function($record) {
                        if (!$record->hasExpensesRecorded()) {
                            return 'Expenses not recorded yet';
                        }
                        $currency = CurrencySetting::getBase();
                        $currencySymbol = $currency ? $currency->currency_symbol : 'AED';
                        return "Margin: {$record->profit_margin}% | Expenses: {$currencySymbol} " . number_format($record->total_expenses, 2);
                    }),
                
                TextColumn::make('profit_margin')
                    ->label('Margin %')
                    ->suffix('%')
                    ->color(fn($state) => $state >= 20 ? 'success' : ($state >= 10 ? 'warning' : 'danger'))
                    ->visible(fn() => auth()->user()?->can('view_expenses') ?? false)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                
                TextColumn::make('total_expenses')
                    ->label('Expenses')
                    ->money(fn() => CurrencySetting::getBase()?->currency_code ?? 'AED')
                    ->visible(fn() => auth()->user()?->can('view_expenses') ?? false)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                
                TextColumn::make('valid_until')
                    ->label('Due Date')
                    ->formatStateUsing(function ($state, $record) {
                        $date = \Carbon\Carbon::parse($state);
                        if ($date->isPast() && $record->payment_status !== \App\Modules\Orders\Enums\PaymentStatus::PAID) {
                            $days = (int) $date->diffInDays(now());
                            return "Overdue by {$days} days";
                        }
                        return $date->format('M j, Y');
                    })
                    ->color(fn($state, $record) => \Carbon\Carbon::parse($state)->isPast() && $record->payment_status !== \App\Modules\Orders\Enums\PaymentStatus::PAID ? 'danger' : null)
                    ->sortable(),
                
                TextColumn::make('warehouse.warehouse_name')
                    ->label('Warehouse')
                    ->sortable()
                    ->toggleable(),
                
                TextColumn::make('tracking_number')
                    ->label('Tracking')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('payment_status')
                    ->label('Payment Status')
                    ->options([
                        'pending' => 'Pending',
                        'partial' => 'Partial',
                        'paid' => 'Paid',
                        'refunded' => 'Refunded',
                        'failed' => 'Failed',
                    ]),
                
                SelectFilter::make('order_status')
                    ->label('Order Status')
                    ->options([
                        'pending'    => 'Pending',
                        'processing' => 'Processing',
                        'shipped'    => 'Shipped',
                        'delivered'  => 'Delivered',
                        'completed'  => 'Completed',
                        'cancelled'  => 'Cancelled',
                    ]),
                
                SelectFilter::make('customer_id')
                    ->label('Customer')
                    ->relationship('customer', 'business_name')
                    ->searchable()
                    ->preload()
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->business_name ?? $record->name ?? 'Unknown Customer'),

                SelectFilter::make('representative_id')
                    ->label('Sales Representative')
                    ->relationship('representative', 'name')
                    ->searchable()
                    ->preload(),
                
                Filter::make('issue_date')
                    ->label('Invoice Date')
                    ->form([
                        DatePicker::make('date_from')
                            ->label('From Date'),
                        DatePicker::make('date_until')
                            ->label('Until Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['date_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('issue_date', '>=', $date),
                            )
                            ->when(
                                $data['date_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('issue_date', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['date_from'] ?? null) {
                            $indicators[] = 'From: ' . \Carbon\Carbon::parse($data['date_from'])->format('M j, Y');
                        }
                        if ($data['date_until'] ?? null) {
                            $indicators[] = 'Until: ' . \Carbon\Carbon::parse($data['date_until'])->format('M j, Y');
                        }
                        return $indicators;
                    }),
                
                Filter::make('due_date')
                    ->form([
                        DatePicker::make('due_from')
                            ->label('Due From'),
                        DatePicker::make('due_until')
                            ->label('Due Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['due_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('valid_until', '>=', $date),
                            )
                            ->when(
                                $data['due_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('valid_until', '<=', $date),
                            );
                    }),
                
                Filter::make('overdue')
                    ->query(fn (Builder $query): Builder => $query->where('valid_until', '<', now())->where('payment_status', '!=', 'paid'))
                    ->toggle(),
            ])
            ->headerActions([
                Action::make('export')
                    ->label('Export CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function ($livewire) {
                        $query = $livewire->getFilteredTableQuery();
                        $records = $query->get();
                        
                        return response()->streamDownload(function () use ($records) {
                            $handle = fopen('php://output', 'w');
                            fputcsv($handle, ['Invoice #', 'Date', 'Customer', 'Status', 'Payment', 'Total', 'Balance']);
                            
                            foreach ($records as $record) {
                                fputcsv($handle, [
                                    $record->order_number,
                                    $record->issue_date->format('Y-m-d'),
                                    $record->customer->name ?? '',
                                    $record->order_status->name,
                                    $record->payment_status->name,
                                    $record->total,
                                    $record->outstanding_amount,
                                ]);
                            }
                            fclose($handle);
                        }, 'invoices-' . now()->format('Y-m-d') . '.csv');
                    }),
            ])
            ->recordActions([
                Action::make('preview')
                    ->label('Preview')
                    ->icon('heroicon-o-eye')
                    ->tooltip('Preview invoice document')
                    ->slideOver()
                    ->modalWidth('7xl')
                    ->modalContent(function ($record) {
                        // Get settings
                        $companyBranding = \App\Modules\Settings\Models\CompanyBranding::getActive();
                        $taxSetting = \App\Modules\Settings\Models\TaxSetting::getDefault();
                        $currency = \App\Modules\Settings\Models\CurrencySetting::getBase();
                        
                        return view('templates.invoice-preview', [
                            'record' => $record,
                            'documentType' => 'invoice',
                            'companyName' => $companyBranding->company_name ?? 'TunerStop LLC',
                            'companyAddress' => $companyBranding->company_address ?? '',
                            'companyPhone' => $companyBranding->company_phone ?? '',
                            'companyEmail' => $companyBranding->company_email ?? '',
                            'taxNumber' => $companyBranding->tax_registration_number ?? '',
                            'logo' => $companyBranding ? $companyBranding->logo_url : null,
                            'currency' => $currency ? $currency->currency_symbol : 'AED',
                            'vatRate' => $taxSetting ? $taxSetting->rate : 5,
                        ]);
                    }),
                
                Action::make('recordPayment')
                    ->label('Record Payment')
                    ->icon('heroicon-o-currency-dollar')
                    ->color('success')
                    ->tooltip('Record a payment received for this invoice')
                    ->visible(fn($record) =>
                        !$record->isFullyPaid() &&
                        $record->order_status !== \App\Modules\Orders\Enums\OrderStatus::CANCELLED
                    )
                    ->form([
                        Select::make('payment_type')
                            ->label('Payment Type')
                            ->options([
                                'full' => 'Full Payment',
                                'partial' => 'Partial Payment',
                            ])
                            ->required()
                            ->default('full'),

                        TextInput::make('amount')
                            ->label('Amount')
                            ->numeric()
                            ->prefix(fn() => CurrencySetting::getBase()?->currency_symbol ?? 'AED')
                            ->required()
                            ->default(fn($record) => $record->outstanding_amount)
                            ->maxValue(fn($record) => $record->outstanding_amount)
                            ->helperText(function($record) {
                                $currency = CurrencySetting::getBase();
                                $currencySymbol = $currency ? $currency->currency_symbol : 'AED';
                                return "Enter full or partial amount. Outstanding: {$currencySymbol} " . number_format($record->outstanding_amount, 2);
                            }),
                        
                        Select::make('payment_method')
                            ->label('Payment Method')
                            ->options([
                                'cash' => 'Cash',
                                'card' => 'Credit/Debit Card',
                                'bank_transfer' => 'Bank Transfer',
                                'cheque' => 'Cheque',
                                'online' => 'Online Payment',
                            ])
                            ->required(),
                        
                        DatePicker::make('payment_date')
                            ->label('Payment Date')
                            ->default(now())
                            ->required(),
                        
                        TextInput::make('reference_number')
                            ->label('Reference Number')
                            ->maxLength(255),
                        
                        TextInput::make('bank_name')
                            ->label('Bank Name')
                            ->maxLength(255),
                        
                        TextInput::make('cheque_number')
                            ->label('Cheque Number')
                            ->maxLength(255)
                            ->visible(fn($get) => $get('payment_method') === 'cheque'),
                        
                        Textarea::make('notes')
                            ->label('Notes')
                            ->rows(2),
                    ])
                    ->action(function ($record, array $data) {
                        Payment::create([
                            'order_id' => $record->id,
                            'customer_id' => $record->customer_id,
                            'recorded_by' => auth()->id(),
                            'amount' => $data['amount'],
                            'payment_type' => $data['payment_type'],
                            'payment_method' => $data['payment_method'],
                            'payment_date' => $data['payment_date'],
                            'reference_number' => $data['reference_number'] ?? null,
                            'bank_name' => $data['bank_name'] ?? null,
                            'cheque_number' => $data['cheque_number'] ?? null,
                            'notes' => $data['notes'] ?? null,
                            'status' => 'completed',
                        ]);
                        
                        $currency = CurrencySetting::getBase();
                        $currencySymbol = $currency ? $currency->currency_symbol : 'AED';
                        
                        Notification::make()
                            ->title('Payment Recorded')
                            ->body("Payment of {$currencySymbol} " . number_format($data['amount'], 2) . ' has been recorded')
                            ->success()
                            ->send();
                    }),

                Action::make('recordRefund')
                    ->label('Record Refund')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->tooltip('Issue a refund for payments made on this cancelled order')
                    ->visible(fn($record) =>
                        $record->order_status === \App\Modules\Orders\Enums\OrderStatus::CANCELLED &&
                        in_array($record->payment_status?->value, ['paid', 'partial'])
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Record Refund')
                    ->modalDescription('This order has been cancelled. Record a refund for payments already received.')
                    ->form([
                        TextInput::make('amount')
                            ->label('Refund Amount')
                            ->numeric()
                            ->prefix(fn() => CurrencySetting::getBase()?->currency_symbol ?? 'AED')
                            ->required()
                            ->default(fn($record) => $record->paid_amount)
                            ->helperText(fn($record) => 'Total paid: ' . (CurrencySetting::getBase()?->currency_symbol ?? 'AED') . ' ' . number_format($record->paid_amount, 2)),

                        Select::make('payment_method')
                            ->label('Refund Method')
                            ->options([
                                'cash'          => 'Cash',
                                'card'          => 'Credit/Debit Card',
                                'bank_transfer' => 'Bank Transfer',
                                'cheque'        => 'Cheque',
                            ])
                            ->required(),

                        DatePicker::make('payment_date')
                            ->label('Refund Date')
                            ->default(now())
                            ->required(),

                        TextInput::make('reference_number')
                            ->label('Reference / Transaction #')
                            ->maxLength(255),

                        Textarea::make('notes')
                            ->label('Reason / Notes')
                            ->rows(2),
                    ])
                    ->action(function ($record, array $data) {
                        Payment::create([
                            'order_id'         => $record->id,
                            'customer_id'      => $record->customer_id,
                            'recorded_by'      => auth()->id(),
                            'amount'           => -abs($data['amount']), // negative = refund
                            'payment_type'     => 'refund',
                            'payment_method'   => $data['payment_method'],
                            'payment_date'     => $data['payment_date'],
                            'reference_number' => $data['reference_number'] ?? null,
                            'notes'            => $data['notes'] ?? null,
                            'status'           => 'refunded',
                        ]);

                        // Mark payment status as refunded
                        $record->update(['payment_status' => \App\Modules\Orders\Enums\PaymentStatus::REFUNDED]);

                        $currency = CurrencySetting::getBase();
                        $sym = $currency?->currency_symbol ?? 'AED';

                        Notification::make()
                            ->title('Refund Recorded')
                            ->body("Refund of {$sym} " . number_format(abs($data['amount']), 2) . ' has been processed.')
                            ->success()
                            ->send();
                    }),
                

                
                Action::make('recordExpenses')
                    ->label('Record Expenses & Calculate Profit')
                    ->icon('heroicon-o-calculator')
                    ->color('warning')
                    ->tooltip('Record costs and expenses to calculate profit margin')
                    ->visible(fn($record) =>
                        (auth()->user()?->can('view_expenses') ?? false) &&
                        (in_array($record->payment_status->value, ['paid', 'partial']) ||
                        $record->order_status->value === 'completed')
                    )
                    ->form([
                        Section::make('Expense Breakdown')
                            ->description('Enter all expenses related to this order to calculate profit')
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('cost_of_goods')
                                            ->label('Cost of Goods')
                                            ->numeric()
                                            ->prefix(fn() => CurrencySetting::getBase()?->currency_symbol ?? 'AED')
                                            ->default(fn($record) => $record->cost_of_goods ?? 0)
                                            ->helperText('Direct product costs')
                                            ->reactive(),
                                        
                                        TextInput::make('shipping_cost')
                                            ->label('Shipping Cost')
                                            ->numeric()
                                            ->prefix(fn() => CurrencySetting::getBase()?->currency_symbol ?? 'AED')
                                            ->default(fn($record) => $record->shipping_cost ?? 0)
                                            ->helperText('Freight and shipping charges')
                                            ->reactive(),
                                        
                                        TextInput::make('duty_amount')
                                            ->label('Customs Duty')
                                            ->numeric()
                                            ->prefix(fn() => CurrencySetting::getBase()?->currency_symbol ?? 'AED')
                                            ->default(fn($record) => $record->duty_amount ?? 0)
                                            ->helperText('Import duties and taxes')
                                            ->reactive(),
                                        
                                        TextInput::make('delivery_fee')
                                            ->label('Delivery Fee')
                                            ->numeric()
                                            ->prefix(fn() => CurrencySetting::getBase()?->currency_symbol ?? 'AED')
                                            ->default(fn($record) => $record->delivery_fee ?? 0)
                                            ->helperText('Last-mile delivery charges')
                                            ->reactive(),
                                        
                                        TextInput::make('installation_cost')
                                            ->label('Installation Cost')
                                            ->numeric()
                                            ->prefix(fn() => CurrencySetting::getBase()?->currency_symbol ?? 'AED')
                                            ->default(fn($record) => $record->installation_cost ?? 0)
                                            ->helperText('Setup and installation fees')
                                            ->reactive(),
                                        
                                        TextInput::make('bank_fee')
                                            ->label('Bank Fee')
                                            ->numeric()
                                            ->prefix(fn() => CurrencySetting::getBase()?->currency_symbol ?? 'AED')
                                            ->default(fn($record) => $record->bank_fee ?? 0)
                                            ->helperText('Wire transfer and banking fees')
                                            ->reactive(),
                                        
                                        TextInput::make('credit_card_fee')
                                            ->label('Credit Card Fee')
                                            ->numeric()
                                            ->prefix(fn() => CurrencySetting::getBase()?->currency_symbol ?? 'AED')
                                            ->default(fn($record) => $record->credit_card_fee ?? 0)
                                            ->helperText('Payment processing fees')
                                            ->reactive(),
                                    ]),
                                
                                Placeholder::make('profit_preview')
                                    ->label('Profit Preview')
                                    ->content(function ($get, $record) {
                                        $currency = CurrencySetting::getBase();
                                        $currencySymbol = $currency ? $currency->currency_symbol : 'AED';
                                        
                                        $revenue = $record->sub_total ?? 0;
                                        $expenses = 
                                            floatval($get('cost_of_goods') ?? 0) +
                                            floatval($get('shipping_cost') ?? 0) +
                                            floatval($get('duty_amount') ?? 0) +
                                            floatval($get('delivery_fee') ?? 0) +
                                            floatval($get('installation_cost') ?? 0) +
                                            floatval($get('bank_fee') ?? 0) +
                                            floatval($get('credit_card_fee') ?? 0);
                                        
                                        $profit = $revenue - $expenses;
                                        $margin = $revenue > 0 ? ($profit / $revenue) * 100 : 0;
                                        
                                        $color = $profit >= 0 ? 'text-green-600' : 'text-red-600';
                                        
                                        return new \Illuminate\Support\HtmlString("
                                            <div class='p-4 bg-gray-50 dark:bg-gray-800 rounded-lg'>
                                                <div class='grid grid-cols-3 gap-4'>
                                                    <div>
                                                        <p class='text-sm text-gray-600 dark:text-gray-400'>Revenue (excl. VAT)</p>
                                                        <p class='text-lg font-semibold'>{$currencySymbol} " . number_format($revenue, 2) . "</p>
                                                    </div>
                                                    <div>
                                                        <p class='text-sm text-gray-600 dark:text-gray-400'>Total Expenses</p>
                                                        <p class='text-lg font-semibold text-red-600'>{$currencySymbol} " . number_format($expenses, 2) . "</p>
                                                    </div>
                                                    <div>
                                                        <p class='text-sm text-gray-600 dark:text-gray-400'>Gross Profit</p>
                                                        <p class='text-xl font-bold {$color}'>AED " . number_format($profit, 2) . "</p>
                                                        <p class='text-sm {$color}'>" . number_format($margin, 2) . "% Margin</p>
                                                    </div>
                                                </div>
                                            </div>
                                        ");
                                    })
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->action(function ($record, array $data) {
                        $record->recordExpenses($data);
                        
                        Notification::make()
                            ->title('Expenses Recorded Successfully')
                            ->body("Gross Profit: AED " . number_format($record->gross_profit, 2) . " (" . number_format($record->profit_margin, 2) . "% margin)")
                            ->success()
                            ->duration(5000)
                            ->send();
                    })
                    ->successNotificationTitle('Expenses Recorded'),
                
                Action::make('addTracking')
                    ->label('Mark as Shipped')
                    ->icon('heroicon-o-truck')
                    ->color('primary')
                    ->visible(fn($record) => $record->order_status->value === 'processing')
                    ->requiresConfirmation()
                    ->modalHeading('Mark Order as Shipped')
                    ->modalDescription('Enter tracking information and confirm shipped quantities.')
                    ->form([
                        TextInput::make('tracking_number')
                            ->label('Tracking Number')
                            ->required()
                            ->maxLength(255),
                        
                        TextInput::make('shipping_carrier')
                            ->label('Shipping Carrier')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Aramex, DHL, FedEx'),
                        
                        TextInput::make('tracking_url')
                            ->label('Tracking URL')
                            ->url()
                            ->maxLength(255)
                            ->placeholder('https://...'),
                        
                        DatePicker::make('shipped_at')
                            ->label('Shipped Date')
                            ->default(now())
                            ->required(),
                    ])
                    ->action(function ($record, array $data) {
                        // Update order with tracking info
                        $record->update([
                            'order_status' => OrderStatus::SHIPPED,
                            'tracking_number' => $data['tracking_number'],
                            'shipping_carrier' => $data['shipping_carrier'],
                            'tracking_url' => $data['tracking_url'] ?? null,
                            'shipped_at' => $data['shipped_at'],
                        ]);
                        
                        // Update shipped quantities for all items
                        foreach ($record->items as $item) {
                            $item->update([
                                'shipped_quantity' => $item->quantity,
                            ]);
                        }
                        
                        // TODO: Send tracking email to customer
                        // Mail::to($record->customer->email)->send(new OrderShippedMail($record));
                        
                        Notification::make()
                            ->title('Order Marked as Shipped')
                            ->body("Tracking: {$data['tracking_number']} via {$data['shipping_carrier']}")
                            ->success()
                            ->send();
                    }),
                
                Action::make('markCompleted')
                    ->label('Mark as Delivered')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn($record) => in_array($record->order_status->value, ['pending', 'processing', 'shipped']))
                    ->requiresConfirmation()
                    ->modalHeading('Mark as Delivered')
                    ->modalDescription('Confirm that this order has been delivered to the customer. Payment can be updated separately.')
                    ->action(function ($record, array $data) {
                        $record->update([
                            'order_status' => OrderStatus::DELIVERED,
                        ]);
                        
                        Notification::make()
                            ->title('Order Marked as Delivered')
                            ->body("Order {$record->order_number} has been marked as delivered")
                            ->success()
                            ->send();
                    }),
                
                Action::make('cancelOrder')
                    ->label('Cancel Order')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->tooltip('Cancel this order and return any allocated inventory to stock')
                    ->visible(fn($record) => in_array($record->order_status->value, ['pending', 'processing']))
                    ->requiresConfirmation()
                    ->modalHeading('Cancel Order')
                    ->modalDescription('This will cancel the order and deallocate any allocated inventory.')
                    ->form([
                        Textarea::make('cancellation_reason')
                            ->label('Cancellation Reason')
                            ->required()
                            ->rows(3)
                            ->placeholder('Why is this order being cancelled?'),
                    ])
                    ->action(function ($record, array $data) {
                        // Handle null notes (use order_notes field)
                        $currentNotes = $record->order_notes ?? '';
                        $newNotes = trim($currentNotes) . "\n\nCancellation Reason: " . $data['cancellation_reason'];
                        
                        // Update status - OrderObserver will handle inventory release
                        $record->update([
                            'order_status' => OrderStatus::CANCELLED,
                            'order_notes' => $newNotes,
                        ]);
                        
                        Notification::make()
                            ->title('Order Cancelled')
                            ->body("Order {$record->order_number} has been cancelled and inventory deallocated")
                            ->warning()
                            ->send();
                    }),
                
                EditAction::make()
                    ->tooltip('Edit invoice details'),

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
            'index' => Pages\ListInvoices::route('/'),
            'create' => Pages\CreateInvoice::route('/create'),
            'view' => Pages\ViewInvoice::route('/{record}'),
            'edit' => Pages\EditInvoice::route('/{record}/edit'),
        ];
    }
}
