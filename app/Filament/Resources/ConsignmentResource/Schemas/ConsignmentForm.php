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
                            ->helperText('Will be auto-generated upon creation')
                            ->columnSpanFull(),
                        
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
                            ->helperText('Select the customer receiving the consignment')
                            ->columnSpanFull(),
                        
                        Grid::make(2)
                            ->schema([
                                Select::make('representative_id')
                                    ->label('Sales Representative')
                                    ->relationship('representative', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->helperText('Sales rep responsible for this consignment')
                                    ->columnSpan(1),
                                
                                DatePicker::make('issue_date')
                                    ->label('Issue Date')
                                    ->default(now())
                                    ->required()
                                    ->columnSpan(1),
                            ]),
                        
                        Grid::make(2)
                            ->schema([
                                DatePicker::make('expected_return_date')
                                    ->label('Expected Return Date')
                                    ->helperText('When items are expected back (if not sold)')
                                    ->columnSpan(1),
                                
                                TextInput::make('tracking_number')
                                    ->label('Tracking Number')
                                    ->maxLength(255)
                                    ->helperText('tracking number not required')
                                    ->columnSpan(1),
                            ]),
                    ]),

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
                                    ->live()
                                    ->afterStateUpdated(function ($state, $set, $get) {
                                        if ($state) {
                                            $variant = \App\Modules\Products\Models\ProductVariant::with('product')->find($state);
                                            if ($variant) {
                                                // Get customer to check if dealer
                                                $customerId = $get('../../customer_id');
                                                $customer = $customerId ? \App\Modules\Customers\Models\Customer::find($customerId) : null;
                                                
                                                // Use uae_retail_price from tunerstop-admin
                                                // Dealer pricing discounts will be applied at order/invoice creation time by DealerPricingService
                                                $price = floatval($variant->uae_retail_price ?? 0);
                                                
                                                $set('sku', $variant->sku);
                                                $set('product_name', $variant->product->name ?? '');
                                                $set('brand_name', $variant->product->brand->name ?? '');
                                                $set('price', $price);
                                                
                                                // Set tax_inclusive from system setting
                                                $taxSetting = \App\Modules\Settings\Models\TaxSetting::getDefault();
                                                $set('tax_inclusive', $taxSetting ? $taxSetting->tax_inclusive_default : true);
                                                
                                                // Trigger total calculation
                                                self::calculateTotals($get, $set);
                                            }
                                        }
                                    })
                                    ->required()
                                    ->columnSpan(2),
                                
                                Hidden::make('sku'),
                                Hidden::make('product_name'),
                                Hidden::make('brand_name'),

                                
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
                                                '%s (Available: %d)',
                                                $warehouse->warehouse_name ?? $warehouse->name,
                                                $available
                                            );
                                            
                                            $options[$warehouse->id] = $label;
                                        }
                                        
                                        // If no inventory records, show all warehouses
                                        if (empty($options)) {
                                            $warehouses = \App\Modules\Inventory\Models\Warehouse::where('status', 1)->get();
                                            foreach ($warehouses as $wh) {
                                                $options[$wh->id] = $wh->warehouse_name . ' (Available: 0)';
                                            }
                                        }
                                        
                                        return $options;
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->helperText('Warehouse where this item is sent from')
                                    ->visible(fn ($get) => $get('product_variant_id') !== null)
                                    ->columnSpan(1),
                                
                                TextInput::make('quantity_sent')
                                    ->label('Qty to Send')
                                    ->numeric()
                                    ->default(1)
                                    ->required()
                                    ->minValue(1)
                                    ->live()
                                    ->afterStateUpdated(function ($get, $set) {
                                        self::calculateTotals($get, $set);
                                    })
                                    ->visible(fn ($get) => $get('product_variant_id') !== null)
                                    ->columnSpan(1),
                                
                                TextInput::make('price')
                                    ->label('Price')
                                    ->numeric()
                                    ->prefix(fn () => \App\Modules\Settings\Models\CurrencySetting::getBase()?->currency_symbol ?? 'AED')
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($get, $set) {
                                        self::calculateTotals($get, $set);
                                    })
                                    ->visible(fn ($get) => $get('product_variant_id') !== null)
                                    ->columnSpan(1),

                                \Filament\Forms\Components\Toggle::make('tax_inclusive')
                                    ->label('Tax Inclusive')
                                    ->default(function () {
                                        $taxSetting = \App\Modules\Settings\Models\TaxSetting::getDefault();
                                        return $taxSetting ? $taxSetting->tax_inclusive_default : true;
                                    })
                                    ->live()
                                    ->reactive()
                                    ->inline(false)
                                    ->helperText('Is the price tax-inclusive?')
                                    ->visible(fn ($get) => $get('product_variant_id') !== null)
                                    ->columnSpan(1),
                                
                                Textarea::make('notes')
                                    ->label('Item Notes')
                                    ->rows(2)
                                    ->columnSpanFull(),
                            ])
                            ->columns(1)
                            ->defaultItems(1)
                            ->addActionLabel('Add Item')
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => 
                                $state['product_name'] ?? 'New Item'
                            )
                            ->live(),
                    ]),

                // Financial Information Section
                Section::make('Financial Information')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                \Filament\Forms\Components\Placeholder::make('subtotal_display')
                                    ->label('Subtotal')
                                    ->content(function ($get, $record) {
                                        $items = $record ? $record->items : ($get('items') ?? []);
                                        $subtotal = 0;
                                        
                                        foreach ($items as $item) {
                                            $qty = floatval($item['quantity_sent'] ?? 0);
                                            $price = floatval($item['price'] ?? 0);
                                            $lineTotal = $qty * $price;
                                            $subtotal += $lineTotal;
                                        }
                                        
                                        $currencySymbol = \App\Modules\Settings\Models\CurrencySetting::getBase()?->currency_symbol ?? 'AED';
                                        return $currencySymbol . ' ' . number_format($subtotal, 2);
                                    })
                                    ->helperText('Calculated from items'),
                                
                                \Filament\Forms\Components\Placeholder::make('tax_display')
                                    ->label(function () {
                                        $taxSetting = \App\Modules\Settings\Models\TaxSetting::getDefault();
                                        $taxRate = $taxSetting ? $taxSetting->rate : 5;
                                        return "Tax ({$taxRate}%)";
                                    })
                                    ->content(function ($get, $record) {
                                        $taxSetting = \App\Modules\Settings\Models\TaxSetting::getDefault();
                                        $taxRate = $taxSetting ? floatval($taxSetting->rate) : 5;
                                        
                                        $items = $record ? $record->items : ($get('items') ?? []);
                                        $subtotal = 0;
                                        $totalTax = 0;
                                        
                                        foreach ($items as $item) {
                                            $qty = floatval($item['quantity_sent'] ?? 0);
                                            $price = floatval($item['price'] ?? 0);
                                            $lineTotal = $qty * $price;
                                            
                                            $isInclusive = $item['tax_inclusive'] ?? true;
                                            
                                            if ($isInclusive) {
                                                $taxAmount = $lineTotal - ($lineTotal / (1 + ($taxRate / 100)));
                                            } else {
                                                $taxAmount = $lineTotal * ($taxRate / 100);
                                            }
                                            $totalTax += $taxAmount;
                                        }
                                        
                                        $currencySymbol = \App\Modules\Settings\Models\CurrencySetting::getBase()?->currency_symbol ?? 'AED';
                                        return $currencySymbol . ' ' . number_format($totalTax, 2);
                                    })
                                    ->helperText(function () {
                                        $taxSetting = \App\Modules\Settings\Models\TaxSetting::getDefault();
                                        $taxRate = $taxSetting ? $taxSetting->rate : 5;
                                        return "Calculated from subtotal at {$taxRate}%";
                                    }),
                                
                                \Filament\Forms\Components\Placeholder::make('total_display')
                                    ->label('Total')
                                    ->content(function ($get, $record) {
                                        $taxSetting = \App\Modules\Settings\Models\TaxSetting::getDefault();
                                        $taxRate = $taxSetting ? floatval($taxSetting->rate) : 5;
                                        
                                        $items = $record ? $record->items : ($get('items') ?? []);
                                        $subtotal = 0;
                                        $runningTotal = 0;
                                        
                                        foreach ($items as $item) {
                                            $qty = floatval($item['quantity_sent'] ?? 0);
                                            $price = floatval($item['price'] ?? 0);
                                            $lineTotal = $qty * $price;
                                            
                                            $isInclusive = $item['tax_inclusive'] ?? true;
                                            
                                            if ($isInclusive) {
                                                $runningTotal += $lineTotal;
                                            } else {
                                                $taxAmount = $lineTotal * ($taxRate / 100);
                                                $runningTotal += $lineTotal + $taxAmount;
                                            }
                                        }
                                        
                                        $discount = floatval($get('discount') ?? $record->discount ?? 0);
                                        $shipping = floatval($get('shipping_cost') ?? $record->shipping_cost ?? 0);
                                        $total = $runningTotal - $discount + $shipping;
                                        
                                        $currencySymbol = \App\Modules\Settings\Models\CurrencySetting::getBase()?->currency_symbol ?? 'AED';
                                        return $currencySymbol . ' ' . number_format($total, 2);
                                    })
                                    ->helperText('(Subtotal - Discount) + Tax + Shipping'),
                            ]),
                        
                        Hidden::make('subtotal')
                            ->default(0)
                            ->dehydrated(true),
                        
                        Hidden::make('tax')
                            ->default(0)
                            ->dehydrated(true),
                        
                        Hidden::make('total')
                            ->default(0)
                            ->dehydrated(true),
                        
                        Grid::make(2)
                            ->schema([
                                TextInput::make('discount')
                                    ->label('Discount')
                                    ->numeric()
                                    ->prefix(fn () => \App\Modules\Settings\Models\CurrencySetting::getBase()?->currency_symbol ?? 'AED')
                                    ->default(0)
                                    ->minValue(0)
                                    ->maxValue(function ($get) {
                                        // Prevent discount from exceeding subtotal
                                        $items = $get('../../items') ?? [];
                                        $subtotal = 0;
                                        foreach ($items as $item) {
                                            $qty = floatval($item['quantity_sent'] ?? 0);
                                            $price = floatval($item['price'] ?? 0);
                                            $subtotal += ($qty * $price);
                                        }
                                        return $subtotal > 0 ? $subtotal : 999999;
                                    })
                                    ->helperText('Cannot exceed subtotal')
                                    ->live()
                                    ->afterStateUpdated(function ($get, $set) {
                                        self::calculateTotals($get, $set);
                                    }),
                                
                                TextInput::make('shipping_cost')
                                    ->label('Shipping Cost')
                                    ->numeric()
                                    ->prefix(fn () => \App\Modules\Settings\Models\CurrencySetting::getBase()?->currency_symbol ?? 'AED')
                                    ->default(0)
                                    ->live()
                                    ->afterStateUpdated(function ($get, $set) {
                                        self::calculateTotals($get, $set);
                                    }),
                            ]),
                    ])
                    ->collapsible(),

                // Notes Section
                Section::make('Notes')
                    ->schema([
                        Textarea::make('notes')
                            ->label('Notes')
                            ->default(function () {
                                // Pre-fill notes from last consignment by this user
                                $userId = auth()->id();
                                return cache()->get("consignment_notes_{$userId}", '');
                            })
                            ->afterStateUpdated(function ($state) {
                                // Save to cache when updated
                                $userId = auth()->id();
                                cache()->put("consignment_notes_{$userId}", $state, now()->addDays(30));
                            })
                            ->live(onBlur: true)
                            ->rows(5)
                            ->helperText('add memory, so notes can be pre-set on every new consignment')
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),
            ]);
    }

    /**
     * Calculate and set totals (subtotal, tax, total)
     */
    protected static function calculateTotals($get, $set): void
    {
        // Get tax rate
        $taxSetting = \App\Modules\Settings\Models\TaxSetting::getDefault();
        $taxRate = $taxSetting ? floatval($taxSetting->rate) : 5;
        
        // Calculate subtotal from items
        $items = $get('items') ?? [];
        $subtotal = 0;
        $totalTax = 0;
        $runningTotal = 0;
        
        foreach ($items as $item) {
            $qty = floatval($item['quantity_sent'] ?? 0);
            $price = floatval($item['price'] ?? 0);
            $lineTotal = $qty * $price;
            
            $isInclusive = $item['tax_inclusive'] ?? true;
            
            if ($isInclusive) {
                // Price includes tax
                $taxAmount = $lineTotal - ($lineTotal / (1 + ($taxRate / 100)));
                $runningTotal += $lineTotal;
            } else {
                // Price excludes tax
                $taxAmount = $lineTotal * ($taxRate / 100);
                $runningTotal += $lineTotal + $taxAmount;
            }
            
            $totalTax += $taxAmount;
            $subtotal += $lineTotal;
        }
        
        // Get discount and shipping
        $discount = floatval($get('discount') ?? 0);
        $shipping = floatval($get('shipping_cost') ?? 0);
        
        // Calculate total
        // Note: Discount is usually applied before tax in exclusive systems, 
        // but in inclusive systems it's applied to the gross amount.
        // Here we subtract discount from the running total (which includes tax for inclusive items).
        // If discount is tax-inclusive, this is correct.
        
        $total = $runningTotal - $discount + $shipping;
        
        // Calculate value tracking fields
        $totalValue = $subtotal; // Total value of all sent items
        $invoicedValue = 0; 
        $returnedValue = 0; 
        $balanceValue = $totalValue - $invoicedValue - $returnedValue;
        
        // Set the values
        $set('subtotal', $subtotal);
        $set('tax', $totalTax);
        $set('total', $total);
        $set('total_value', $totalValue);
        $set('invoiced_value', $invoicedValue);
        $set('returned_value', $returnedValue);
        $set('balance_value', $balanceValue);
    }
}
