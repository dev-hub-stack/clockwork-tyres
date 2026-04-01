<?php

namespace App\Filament\Resources;

use App\Filament\Resources\QuoteResource\Pages;
use App\Modules\Accounts\Support\CurrentAccountResolver;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Enums\QuoteStatus;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Orders\Services\QuoteConversionService;
use App\Modules\Procurement\Actions\ApproveProcurementRequestAction;
use App\Modules\Procurement\Enums\ProcurementWorkflowStage;
use App\Modules\Procurement\Models\ProcurementRequest;
use App\Modules\Procurement\Support\ProcurementQuoteLifecycle;
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
use Filament\Forms\Components\Toggle;
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

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_quotes') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create_quotes') ?? false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->can('edit_quotes') ?? false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->can('delete_quotes') ?? false;
    }
    
    protected static string|UnitEnum|null $navigationGroup = 'Sales';
    
    protected static ?int $navigationSort = 1;
    
    protected static ?string $modelLabel = 'Quote';
    
    protected static ?string $pluralModelLabel = 'Quotes & Proformas';

    /**
     * Show a sidebar badge for wholesale quotes/proformas the same way
     * abandoned carts use a navigation badge: count the records in this section.
     */
    public static function getNavigationBadge(): ?string
    {
        $count = (clone static::getEloquentQuery())->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }

    /**
     * Global scope to only show quotes
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->quotes() // Uses the scope from Order model
            ->with([
                'customer',
                'items.warehouse',
                'procurementQuoteRequest.retailerAccount',
                'procurementQuoteRequest.supplierAccount',
            ])
            ->latest('issue_date');

        $user = auth()->user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->hasRole('super_admin')) {
            return $query;
        }

        $currentAccount = app(CurrentAccountResolver::class)
            ->resolve(request(), $user)
            ->currentAccount;

        if (! $currentAccount) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $builder) use ($currentAccount): void {
            $builder->whereHas('customer', function (Builder $customerQuery) use ($currentAccount): void {
                $customerQuery->where('account_id', $currentAccount->id);
            })->orWhereHas('procurementQuoteRequest', function (Builder $procurementQuery) use ($currentAccount): void {
                $procurementQuery
                    ->where('retailer_account_id', $currentAccount->id)
                    ->orWhere('supplier_account_id', $currentAccount->id);
            });
        });
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
    public static function calculateValues(array $items, float $shipping, bool $isZeroRated = false): array
    {
        // Cache within a single request/render cycle to avoid repeated DB queries
        static $cache = [];
        $cacheKey = md5(serialize($items) . '|' . $shipping . '|' . ($isZeroRated ? '1' : '0'));
        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        if ($isZeroRated) {
            $taxRate = 0;
            $multiplier = 1;
        } else {
            $taxSetting = TaxSetting::getDefault();
            $taxRate    = $taxSetting ? floatval($taxSetting->rate) : 5;
            $multiplier = 1 + ($taxRate / 100);
        }

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

        $result = [
            'sub_total'   => round($inclNet  + $exclBase, 2),
            'items_total' => round($inclGross + $exclNet,  2),
            'vat'         => round($inclTax   + $exclTax,  2),
            'total'       => round($inclGross  + $exclBase + $exclTax, 2),
        ];

        $cache[$cacheKey] = $result;
        return $result;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Quote Information')
                    ->schema([
                        // Full width customer field
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
                                if (!empty($data['email'])) {
                                    $customer = Customer::where('email', $data['email'])->first();
                                    if ($customer) {
                                        $customer->update(collect($data)->except('email')->filter()->all());
                                        return $customer->id;
                                    }
                                }
                                return Customer::create($data)->id;
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
                                    ->label('Valid Until')
                                    ->required()
                                    ->default(now()->addDays(30))
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
                                    ->label('Generation')
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
                                Select::make('item_selection')
                                    ->label('Product / Add-on')
                                    ->searchable()
                                    ->dehydrated(false) // Don't save this field directly
                                    ->getSearchResultsUsing(function (string $search) {
                                        // Search Products
                                        $products = ProductVariant::query()
                                            ->with(['product.brand', 'product.model', 'product.finish'])
                                            ->where(function ($query) use ($search) {
                                                $query->where('sku', 'like', "%{$search}%")
                                                    ->orWhereHas('product', function ($q) use ($search) {
                                                        $q->where('name', 'like', "%{$search}%")
                                                          ->orWhereHas('brand', fn($b) => $b->where('name', 'like', "%{$search}%"))
                                                          ->orWhereHas('model', fn($m) => $m->where('name', 'like', "%{$search}%"))
                                                          ->orWhereHas('finish', fn($f) => $f->where('finish', 'like', "%{$search}%"));
                                                    })
                                                    ->orWhere('size', 'like', "%{$search}%")
                                                    ->orWhere('bolt_pattern', 'like', "%{$search}%")
                                                    ->orWhere('offset', 'like', "%{$search}%");
                                            })
                                            ->limit(20)
                                            ->get()
                                            ->filter(fn($variant) => $variant->product !== null && $variant->sku !== null)
                                            ->mapWithKeys(fn($variant) => [
                                                'product_' . $variant->id => sprintf(
                                                    '%s - %s | %s | %s | Size: %s | Bolt: %s | Offset: %s',
                                                    $variant->sku ?? 'NO-SKU',
                                                    $variant->product->brand?->name ?? 'N/A',
                                                    $variant->product->model?->name ?? 'N/A',
                                                    $variant->product?->finish?->finish ?? 'N/A',
                                                    $variant->size ?? 'N/A',
                                                    $variant->bolt_pattern ?? 'N/A',
                                                    $variant->offset ?? 'N/A'
                                                )
                                            ]);

                                        // Search Add-ons
                                        $addons = \App\Modules\Products\Models\AddOn::query()
                                            ->where(function ($q) use ($search) {
                                                $q->where('title', 'like', "%{$search}%")
                                                  ->orWhere('part_number', 'like', "%{$search}%");
                                            })
                                            ->limit(20)
                                            ->get()
                                            ->mapWithKeys(fn($addon) => [
                                                'addon_' . $addon->id => sprintf(
                                                    'ADDON: %s%s (%s)',
                                                    $addon->title,
                                                    $addon->part_number ? ' [' . $addon->part_number . ']' : '',
                                                    $addon->category?->name ?? 'N/A'
                                                )
                                            ]);

                                        $results = $products->union($addons)->toArray();
                                        
                                        // Add custom item option if search is not empty
                                        if (strlen($search) > 0) {
                                            $results['custom_item'] = '➕ Add Custom Item: "' . $search . '"';
                                        }

                                        return $results;
                                    })
                                    ->getOptionLabelUsing(function ($value) {
                                        if (!$value) return 'Unknown';
                                        
                                        if ($value === 'custom_item') {
                                            return '✏️ Custom Item';
                                        }
                                        
                                        if (str_starts_with($value, 'addon_')) {
                                            $id = substr($value, 6);
                                            $addon = \App\Modules\Products\Models\AddOn::find($id);
                                            if (!$addon) return 'Unknown Add-on';
                                            return sprintf('ADDON: %s%s',
                                                $addon->title,
                                                $addon->part_number ? ' [' . $addon->part_number . ']' : ''
                                            );
                                        }
                                        
                                        $id = str_starts_with($value, 'product_') ? substr($value, 8) : $value;
                                        $variant = ProductVariant::with(['product.brand', 'product.model', 'product.finish'])->find($id);
                                        if (!$variant || !$variant->product) return 'Unknown Product';
                                        
                                        return sprintf(
                                            '%s - %s | %s | %s | Size: %s | Bolt: %s | Offset: %s',
                                            $variant->sku ?? 'NO-SKU',
                                            $variant->product->brand?->name ?? 'N/A',
                                            $variant->product->model?->name ?? 'N/A',
                                            $variant->product?->finish?->finish ?? 'N/A',
                                            $variant->size ?? 'N/A',
                                            $variant->bolt_pattern ?? 'N/A',
                                            $variant->offset ?? 'N/A'
                                        );
                                    })
                                    ->afterStateHydrated(function ($component, $state, $record) {
                                        // Load initial state from record (if editing)
                                        // In a repeater, $record is the OrderItem instance
                                        if ($record) {
                                            if ($record->add_on_id) {
                                                $component->state('addon_' . $record->add_on_id);
                                            } elseif ($record->product_variant_id) {
                                                $component->state('product_' . $record->product_variant_id);
                                            } elseif (!empty($record->product_name)) {
                                                // Custom item — no product_variant_id or add_on_id
                                                $component->state('custom_item');
                                            }
                                        }
                                    })
                                    ->preload() // Enable preloading to show initial options
                                     ->afterStateUpdated(function ($state, $set, $get) {
                                         if ($state) {
                                             if ($state === 'custom_item') {
                                                 // Extract the search term used
                                                 $set('is_custom', true);
                                                 $set('product_variant_id', null);
                                                 $set('add_on_id', null);
                                                 $set('discount', 0);
                                                 
                                                 $set('unit_price', 0);
                                                 $set('quantity', 1);
                                                 
                                                 // Set tax_inclusive from system setting
                                                 $taxSetting = \App\Modules\Settings\Models\TaxSetting::getDefault();
                                                 $set('tax_inclusive', $taxSetting ? $taxSetting->tax_inclusive_default : true);
                                             } elseif (str_starts_with($state, 'addon_')) {
                                                 $id = substr($state, 6);
                                                 $addon = \App\Modules\Products\Models\AddOn::find($id);
                                                 if ($addon) {
                                                     $customerId = $get('../../customer_id');
                                                     $customer = $customerId
                                                         ? \App\Modules\Customers\Models\Customer::find($customerId)
                                                         : null;

                                                     $set('is_custom', false);
                                                     $set('add_on_id', $id);
                                                     $set('product_variant_id', null); // Clear product variant
                                                     $set('discount', 0);
                                                     $set('unit_price', $addon->resolvePriceForCustomer($customer));
                                                     $set('quantity', 1);
                                                     
                                                     // Set tax_inclusive from system setting
                                                     $taxSetting = \App\Modules\Settings\Models\TaxSetting::getDefault();
                                                     $set('tax_inclusive', $taxSetting ? $taxSetting->tax_inclusive_default : true);
                                                 }
                                             } else {
                                                 $id = str_starts_with($state, 'product_') ? substr($state, 8) : $state;
                                                 $variant = ProductVariant::with('product')->find($id);
                                                 if ($variant) {
                                                     $set('is_custom', false);
                                                     $set('product_variant_id', $id); // Ensure clean ID is set
                                                     $set('add_on_id', null); // Clear addon
                                                     $set('discount', 0);
                                                     
                                                     // Dealer % applies to MSRP; best price wins vs sale_price
                                                     $retail = floatval($variant->uae_retail_price ?? 0);
                                                     $salePr = $variant->sale_price ? floatval($variant->sale_price) : null;
                                                     // Dealer % applies to sale_price when set, otherwise MSRP
                                                     $price  = ($salePr && $salePr < $retail) ? $salePr : $retail;
                                                     
                                                     // Apply Dealer Pricing if applicable
                                                     $customerId = $get('../../customer_id');
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
                                                             
                                                             if ($pricing['discount_amount'] > 0) {
                                                                 $price = $pricing['final_price'];
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
                                         }
                                     })
                                     ->live()
                                     ->required()
                                     ->columnSpanFull(),
                                     
                                 \Filament\Forms\Components\Hidden::make('is_custom')
                                     ->default(false)
                                     ->afterStateHydrated(function ($component, $record) {
                                         if ($record && !$record->product_variant_id && !$record->add_on_id && !empty($record->product_name)) {
                                             $component->state(true);
                                         }
                                     }),
                                     
                                 TextInput::make('product_name')
                                     ->label('Custom Product Name')
                                     ->required(fn ($get) => $get('is_custom'))
                                     ->visible(fn ($get) => $get('is_custom'))
                                     ->columnSpanFull()
                                     ->helperText('Enter the name or description of this custom item.'),
                                
                                // Hidden fields to store actual IDs
                                \Filament\Forms\Components\Hidden::make('add_on_id'),
                                \Filament\Forms\Components\Hidden::make('product_variant_id'),
                                
                                // Product Image Display
                                \Filament\Forms\Components\ViewField::make('product_image')
                                    ->label('Product Image')
                                    ->view('filament.forms.components.product-image-display')
                                    ->columnSpanFull()
                                    ->visible(fn ($get) => $get('product_variant_id') !== null),
                                
                                Select::make('warehouse_id')
                                    ->label('Warehouse')
                                    ->options(function ($get) {
                                        $variantId = $get('product_variant_id');
                                        
                                        if (!$variantId) {
                                            $addonId = $get('add_on_id');
                                            if ($addonId) {
                                                $addon = \App\Modules\Products\Models\AddOn::find($addonId);
                                                // If addon tracks inventory, show per-warehouse stock
                                                if ($addon && $addon->track_inventory) {
                                                    $inventories = \App\Modules\Inventory\Models\ProductInventory::where('add_on_id', $addonId)
                                                        ->with('warehouse')
                                                        ->get();
                                                    $options = [];
                                                    foreach ($inventories as $inv) {
                                                        if (!$inv->warehouse) continue;
                                                        $available = ($inv->quantity ?? 0) + ($inv->eta_qty ?? 0);
                                                        $options[$inv->warehouse->id] = sprintf(
                                                            '%s - %d available (%d in stock%s)',
                                                            $inv->warehouse->name,
                                                            $available,
                                                            $inv->quantity ?? 0,
                                                            ($inv->eta_qty ?? 0) > 0 ? ", {$inv->eta_qty} expected" : ''
                                                        );
                                                    }
                                                    return $options;
                                                }
                                                // Untracked addon = non-stock service item
                                                return ['' => '⚙️ Service / Non-Stock Item'];
                                            }
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
                                    ->required(fn ($get) => !$get('is_custom'))
                                    ->visible(fn ($get) => !$get('is_custom'))
                                    ->helperText(fn ($get) => $get('is_custom') ? '' : 'Select warehouse for this item')
                                    ->columnSpan(2),
                                
                                TextInput::make('quantity')
                                    ->label('Quantity')
                                    ->numeric()
                                    ->default(1)
                                    ->required()
                                    ->minValue(1)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, $get, $set) {
                                        $qty = floatval($state ?? 0);
                                        $price = floatval($get('unit_price') ?? 0);
                                        $discount = floatval($get('discount') ?? 0);
                                        $set('line_total', ($qty * $price) - $discount);
                                        
                                        $items = $get('../../items') ?? [];
                                        $shipping = floatval($get('../../shipping') ?? 0);
                                        $totals = self::calculateValues($items, $shipping, $get('../../is_zero_rated') ?? false);
                                        
                                        $set('../../sub_total', $totals['sub_total']);
                                        $set('../../vat', $totals['vat']);
                                        $set('../../total', $totals['total']);
                                    }),
                                
                                TextInput::make('unit_price')
                                    ->label('Unit Price')
                                    ->numeric()
                                    ->prefix(fn() => CurrencySetting::getBase()?->currency_symbol ?? 'AED')
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, $get, $set) {
                                        $qty = floatval($get('quantity') ?? 0);
                                        $price = floatval($state ?? 0);
                                        $discount = floatval($get('discount') ?? 0);
                                        $set('line_total', ($qty * $price) - $discount);
                                        
                                        $items = $get('../../items') ?? [];
                                        $shipping = floatval($get('../../shipping') ?? 0);
                                        $totals = self::calculateValues($items, $shipping, $get('../../is_zero_rated') ?? false);
                                        
                                        $set('../../sub_total', $totals['sub_total']);
                                        $set('../../vat', $totals['vat']);
                                        $set('../../total', $totals['total']);
                                    }),
                                
                                TextInput::make('discount')
                                    ->label('Discount')
                                    ->numeric()
                                    ->prefix(fn() => CurrencySetting::getBase()?->currency_symbol ?? 'AED')
                                    ->default(0)
                                    ->dehydrateStateUsing(fn ($state) => round((float) ($state ?? 0), 2))
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, $get, $set) {
                                        $qty = floatval($get('quantity') ?? 0);
                                        $price = floatval($get('unit_price') ?? 0);
                                        $discount = floatval($state ?? 0);
                                        $set('line_total', ($qty * $price) - $discount);
                                        
                                        $items = $get('../../items') ?? [];
                                        $shipping = floatval($get('../../shipping') ?? 0);
                                        $totals = self::calculateValues($items, $shipping, $get('../../is_zero_rated') ?? false);
                                        
                                        $set('../../sub_total', $totals['sub_total']);
                                        $set('../../vat', $totals['vat']);
                                        $set('../../total', $totals['total']);
                                    }),
                                
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
                                        $totals = self::calculateValues($items, $shipping, $get('../../is_zero_rated') ?? false);
                                        
                                        $set('../../sub_total', $totals['sub_total']);
                                        $set('../../vat', $totals['vat']);
                                        $set('../../total', $totals['total']);
                                    }),
                                
                                Hidden::make('line_total')
                                    ->default(0)
                                    ->dehydrated(),
                                
                                Placeholder::make('line_total_display')
                                    ->label('Line Total')
                                    ->content(function ($get) {
                                        $currency = CurrencySetting::getBase();
                                        $currencyCode = $currency ? $currency->currency_code : 'AED';
                                        $qty = floatval($get('quantity') ?? 0);
                                        $price = floatval($get('unit_price') ?? 0);
                                        $discount = floatval($get('discount') ?? 0);
                                        $total = ($qty * $price) - $discount;
                                        return Number::currency($total, $currencyCode);
                                    })
                                    ->extraAttributes(['wire:loading.class' => 'opacity-40 animate-pulse', 'class' => 'transition-opacity duration-150']),
                            ])
                            ->columns(2)
                            ->defaultItems(1)
                            ->addActionLabel('Add Line Item')
                            ->reorderable()
                            ->collapsible()
                            ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                                $qty = floatval($data['quantity'] ?? 0);
                                $price = floatval($data['unit_price'] ?? 0);
                                $discount = floatval($data['discount'] ?? 0);
                                $data['line_total'] = ($qty * $price) - $discount;
                                return $data;
                            })
                            ->mutateRelationshipDataBeforeSaveUsing(function (array $data): array {
                                $qty = floatval($data['quantity'] ?? 0);
                                $price = floatval($data['unit_price'] ?? 0);
                                $discount = floatval($data['discount'] ?? 0);
                                $data['line_total'] = ($qty * $price) - $discount;
                                return $data;
                            })
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

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
                                                $currency = CurrencySetting::getBase();
                                                $currencySymbol = $currency ? $currency->currency_symbol : 'AED';
                                                $items = $get('items') ?? [];
                                                $shipping = floatval($get('shipping') ?? 0);
                                                $totals = self::calculateValues($items, $shipping, $get('is_zero_rated') ?? false);
                                                return $currencySymbol . ' ' . number_format($totals['sub_total'], 2);
                                            })
                                            ->helperText('Items − Discounts + Shipping')
                                            ->extraAttributes(['wire:loading.class' => 'opacity-40 animate-pulse', 'class' => 'transition-opacity duration-150']),

                                        Placeholder::make('vat_display')
                                            ->label(function () {
                                                $taxSetting = TaxSetting::getDefault();
                                                $taxName = $taxSetting ? $taxSetting->name : 'VAT';
                                                $taxRate = $taxSetting ? $taxSetting->rate : 5;
                                                return "{$taxName} ({$taxRate}%)";
                                            })
                                            ->content(function ($get, $record) {
                                                $currency = CurrencySetting::getBase();
                                                $currencySymbol = $currency ? $currency->currency_symbol : 'AED';
                                                $items = $get('items') ?? [];
                                                $shipping = floatval($get('shipping') ?? 0);
                                                $totals = self::calculateValues($items, $shipping, $get('is_zero_rated') ?? false);
                                                return $currencySymbol . ' ' . number_format($totals['vat'], 2);
                                            })
                                            ->helperText('Subtotal × rate%')
                                            ->extraAttributes(['wire:loading.class' => 'opacity-40 animate-pulse', 'class' => 'transition-opacity duration-150']),
                                        
                                        Hidden::make('vat')
                                            ->default(0),
                                        
                                        Hidden::make('tax_inclusive')
                                            ->default(function () {
                                                $taxSetting = \App\Modules\Settings\Models\TaxSetting::getDefault();
                                                return $taxSetting ? $taxSetting->tax_inclusive_default : true;
                                            }),
                                        
                                        TextInput::make('shipping')
                                            ->label('Shipping')
                                            ->numeric()
                                            ->prefix(fn() => CurrencySetting::getBase()?->currency_symbol ?? 'AED')
                                            ->default(0)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function ($get, $set) {
                                                $items = $get('items') ?? [];
                                                $shipping = floatval($get('shipping') ?? 0);
                                                $totals = self::calculateValues($items, $shipping, $get('is_zero_rated') ?? false);
                                                
                                                $set('sub_total', $totals['sub_total']);
                                                $set('vat', $totals['vat']);
                                                $set('total', $totals['total']);
                                            }),
                                        
                                        Placeholder::make('total_display')
                                            ->label('Total')
                                            ->content(function ($get, $record) {
                                                $currency = CurrencySetting::getBase();
                                                $currencySymbol = $currency ? $currency->currency_symbol : 'AED';
                                                
                                                $items = $get('items') ?? [];
                                                $shipping = floatval($get('shipping') ?? 0);
                                                $totals = self::calculateValues($items, $shipping, $get('is_zero_rated') ?? false);
                                                
                                                return $currencySymbol . ' ' . number_format($totals['total'], 2);
                                            })
                                            ->extraAttributes(['class' => 'font-bold text-lg transition-opacity duration-150', 'wire:loading.class' => 'opacity-40 animate-pulse']),
                                        
                                        Hidden::make('sub_total')->default(0),
                                        Hidden::make('total')->default(0),
                                    ])
                                    ->columnSpan(1),
                            ]),
                    ]),
                    
                        Hidden::make('tax_type')
                    ->default('standard'),
                    
                Toggle::make('is_zero_rated')
                    ->label('Zero Rated VAT (0%)')
                    ->helperText('Enable this if the sale is exempt from VAT (e.g., export or zero-rated supply).')
                    ->default(false)
                    ->inline(false)
                    ->live()
                    ->dehydrated()
                    ->afterStateUpdated(function ($get, $set) {
                        // Trigger totals recalculation
                        $items = $get('items') ?? [];
                        $shipping = floatval($get('shipping') ?? 0);
                        $totals = self::calculateValues($items, $shipping, $get('is_zero_rated') ?? false);
                        $set('sub_total', $totals['sub_total']);
                        $set('vat', $totals['vat']);
                        $set('total', $totals['total']);
                    }),
                    
                Hidden::make('tax_inclusive')
                    ->default(function () {
                        $taxSetting = \App\Modules\Settings\Models\TaxSetting::getDefault();
                        return $taxSetting ? $taxSetting->tax_inclusive_default : true;
                    }),
                    
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
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy(
                            \DB::raw('COALESCE(customers.business_name, CONCAT(customers.first_name, " ", customers.last_name))'),
                            $direction
                        );
                    }),
                
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
                    ->money(fn() => CurrencySetting::getBase()?->currency_code ?? 'AED')
                    ->sortable(),
                
                TextColumn::make('valid_until')
                    ->label('Valid Until')
                    ->date()
                    ->sortable(),
                
                TextColumn::make('warehouses')
                    ->label('Warehouse')
                    ->getStateUsing(function ($record) {
                        $warehouses = $record->items
                            ->pluck('warehouse.name')
                            ->filter()
                            ->unique()
                            ->values();
                        
                        if ($warehouses->isEmpty()) {
                            return 'Non-Stock';
                        }
                        
                        return $warehouses->count() > 1 
                            ? "Multiple ({$warehouses->count()})" 
                            : $warehouses->first();
                    })
                    ->sortable(false)
                    ->toggleable(),
                
                BadgeColumn::make('channel')
                    ->label('Channel')
                    ->colors([
                        'primary' => 'wholesale',
                        'secondary' => 'retail',
                    ])
                    ->formatStateUsing(fn ($state) => $state ? ucfirst($state) : 'Retail')
                    ->toggleable(),

                TextColumn::make('procurementQuoteRequest.request_number')
                    ->label('Procurement #')
                    ->placeholder('Direct quote')
                    ->toggleable(),

                TextColumn::make('procurementQuoteRequest.retailerAccount.name')
                    ->label('Retailer')
                    ->placeholder('Direct quote')
                    ->toggleable(isToggledHiddenByDefault: true),

                BadgeColumn::make('procurementQuoteRequest.current_stage')
                    ->label('Procurement')
                    ->formatStateUsing(fn (?ProcurementWorkflowStage $state): string => $state?->label() ?? 'Direct quote')
                    ->colors([
                        'gray' => static fn (?ProcurementWorkflowStage $state): bool => in_array($state, [
                            null,
                            ProcurementWorkflowStage::DRAFT,
                            ProcurementWorkflowStage::SUBMITTED,
                        ], true),
                        'warning' => static fn (?ProcurementWorkflowStage $state): bool => in_array($state, [
                            ProcurementWorkflowStage::SUPPLIER_REVIEW,
                            ProcurementWorkflowStage::QUOTED,
                            ProcurementWorkflowStage::APPROVED,
                        ], true),
                        'success' => static fn (?ProcurementWorkflowStage $state): bool => in_array($state, [
                            ProcurementWorkflowStage::INVOICED,
                            ProcurementWorkflowStage::STOCK_RESERVED,
                            ProcurementWorkflowStage::STOCK_DEDUCTED,
                            ProcurementWorkflowStage::FULFILLED,
                        ], true),
                        'danger' => static fn (?ProcurementWorkflowStage $state): bool => $state === ProcurementWorkflowStage::CANCELLED,
                    ]),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('channel')
                    ->label('Channel')
                    ->options([
                        'retail' => 'Retail',
                        'wholesale' => 'Wholesale',
                    ])
                    ->placeholder('All Channels'),

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

                Filter::make('procurement_linked')
                    ->label('Procurement linked')
                    ->query(fn (Builder $query): Builder => $query->whereHas('procurementQuoteRequest'))
                    ->toggle(),

                SelectFilter::make('procurement_stage')
                    ->label('Procurement Stage')
                    ->options(collect(ProcurementWorkflowStage::ordered())
                        ->mapWithKeys(fn (ProcurementWorkflowStage $stage): array => [$stage->value => $stage->label()])
                        ->all())
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if (! $value) {
                            return $query;
                        }

                        return $query->whereHas('procurementQuoteRequest', function (Builder $procurementQuery) use ($value): void {
                            $procurementQuery->where('current_stage', $value);
                        });
                    }),
                
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
                Action::make('view_procurement')
                    ->label('Procurement')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->color('gray')
                    ->visible(fn (Order $record): bool => $record->procurementQuoteRequest !== null)
                    ->url(fn (Order $record): ?string => $record->procurementQuoteRequest
                        ? route('filament.admin.resources.procurement-requests.view', ['record' => $record->procurementQuoteRequest])
                        : null),

                Action::make('approve_procurement')
                    ->label('Approve Procurement')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (Order $record): bool => static::canApproveProcurement($record))
                    ->requiresConfirmation()
                    ->modalHeading('Approve Procurement Quote')
                    ->modalDescription('This approves the linked procurement request and converts the supplier quote into an invoice using the existing CRM order workflow.')
                    ->action(function (Order $record) {
                        $procurementRequest = $record->procurementQuoteRequest;

                        if (! $procurementRequest) {
                            return;
                        }

                        $approvedRequest = app(ApproveProcurementRequestAction::class)->execute($procurementRequest);

                        \Filament\Notifications\Notification::make()
                            ->title('Procurement approved')
                            ->body(($approvedRequest->request_number ?? 'Procurement request').' moved into invoice flow.')
                            ->success()
                            ->send();

                        if ($approvedRequest->invoiceOrder) {
                            return redirect()->route('filament.admin.resources.invoices.view', ['record' => $approvedRequest->invoiceOrder]);
                        }
                    }),

                Action::make('start_supplier_review')
                    ->label('Start Review')
                    ->icon('heroicon-o-eye')
                    ->color('warning')
                    ->visible(fn (Order $record): bool => static::canStartSupplierReview($record))
                    ->action(function (Order $record) {
                        $request = app(ProcurementQuoteLifecycle::class)->startSupplierReview($record);

                        if (! $request) {
                            return;
                        }

                        \Filament\Notifications\Notification::make()
                            ->title('Supplier review started')
                            ->body(($request->request_number ?? 'Procurement request').' moved into supplier review.')
                            ->success()
                            ->send();
                    }),

                Action::make('preview')
                    ->label('Preview')
                    ->icon('heroicon-o-eye')
                    ->slideOver()
                    ->modalWidth('7xl')
                    ->modalSubmitAction(false) // Hide Submit button
                    ->modalCancelActionLabel('Close') // Change Cancel to Close
                    ->modalFooterActionsAlignment('end')
                    ->modalContent(function ($record) {
                        // Get settings
                        $companyBranding = \App\Modules\Settings\Models\CompanyBranding::getActive();
                        $taxSetting = \App\Modules\Settings\Models\TaxSetting::getDefault();
                        $currency = \App\Modules\Settings\Models\CurrencySetting::getBase();
                        
                        return view('templates.invoice-preview', [
                            'record' => $record,
                            'documentType' => 'quote',
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
                
                Action::make('send')
                    ->label('Send')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('primary')
                    ->visible(fn($record) => $record->quote_status?->value === 'draft')
                    ->requiresConfirmation()
                    ->modalHeading('Send Quote to Customer')
                    ->modalDescription('This will send the quote to the customer via email and mark it as SENT.')
                    ->form([
                        TextInput::make('email')
                            ->label('Customer Email')
                            ->email()
                            ->required()
                            ->default(fn($record) => $record->customer->email ?? '')
                            ->helperText('Quote will be sent to this email address'),
                    ])
                    ->action(function ($record, array $data) {
                        app(ProcurementQuoteLifecycle::class)->markQuoted($record);

                        // Send quote and update status
                        $record->update([
                            'quote_status' => QuoteStatus::SENT,
                            'sent_at' => now(),
                        ]);
                        
                        // Send email with PDF attachment
                        try {
                            $mailStatus = app(\App\Support\TransactionalCustomerMail::class)->send(
                                $data['email'],
                                new \App\Mail\QuoteSentMail($record),
                                [
                                    'trigger' => 'quote.send',
                                    'quote_id' => $record->id,
                                    'quote_number' => $record->quote_number,
                                ]
                            );
                            $emailNote = $mailStatus === 'suppressed'
                                ? ' Email suppressed by system setting.'
                                : " Email sent to {$data['email']}.";
                        } catch (\Exception $emailEx) {
                            \Illuminate\Support\Facades\Log::warning('Failed to send quote email', [
                                'quote_id' => $record->id,
                                'email' => $data['email'],
                                'error' => $emailEx->getMessage(),
                            ]);
                            $emailNote = ' (Email delivery failed — check mail settings.)';
                        }
                        
                        \Filament\Notifications\Notification::make()
                            ->title('Quote Sent Successfully')
                            ->body("Quote {$record->quote_number} has been sent.{$emailNote}")
                            ->success()
                            ->send();
                    }),
                
                Action::make('mark_as_sent')
                    ->label('Mark as Sent')
                    ->icon('heroicon-o-check')
                    ->color('gray')
                    ->visible(fn($record) => $record->quote_status?->value === 'draft')
                    ->requiresConfirmation()
                    ->modalHeading('Mark Quote as Sent')
                    ->modalDescription('This will mark the quote as sent without sending an email. Use this if you delivered the quote manually or via another channel.')
                    ->modalSubmitActionLabel('Yes, Mark as Sent')
                    ->action(function ($record) {
                        app(ProcurementQuoteLifecycle::class)->markQuoted($record);

                        $record->update([
                            'quote_status' => QuoteStatus::SENT,
                            'sent_at' => now(),
                        ]);

                        \Filament\Notifications\Notification::make()
                            ->title('Quote Marked as Sent')
                            ->body("Quote {$record->quote_number} has been marked as sent.")
                            ->success()
                            ->send();
                    }),

                Action::make('convert')
                    ->label('Convert to Invoice')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('warning')
                    ->visible(fn(Order $record) => $record->canConvertToInvoice() && $record->procurementQuoteRequest === null)
                    ->requiresConfirmation()
                    ->modalHeading('Convert Quote to Invoice')
                    ->modalDescription('This will convert this quote into an invoice and start the order workflow.')
                    ->action(function ($record) {
                        $invoice = app(QuoteConversionService::class)->convertQuoteToInvoice($record);
                        
                        \Filament\Notifications\Notification::make()
                            ->title('Quote Converted to Invoice')
                            ->body("Invoice {$invoice->order_number} has been created from quote {$record->quote_number}")
                            ->success()
                            ->send();
                        
                        // Redirect to invoice edit page
                        return redirect()->route('filament.admin.resources.invoices.edit', ['record' => $invoice]);
                    }),
                
                Action::make('duplicate')
                    ->label('Duplicate')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Duplicate Quote')
                    ->modalDescription('This will create a new quote with the same customer, vehicle info, and line items.')
                    ->action(function ($record) {
                        // Create new quote
                        $newQuote = $record->replicate();
                        $newQuote->quote_status = QuoteStatus::DRAFT;
                        $newQuote->quote_number = null; // Will be auto-generated
                        $newQuote->order_number = null; // Will be auto-generated
                        $newQuote->sent_at = null;
                        $newQuote->approved_at = null;
                        $newQuote->issue_date = now();
                        $newQuote->valid_until = now()->addDays(30);
                        $newQuote->save();
                        
                        // Duplicate line items
                        foreach ($record->items as $item) {
                            $newItem = $item->replicate();
                            $newItem->order_id = $newQuote->id;
                            $newItem->allocated_quantity = 0;
                            $newItem->shipped_quantity = 0;
                            $newItem->save();
                        }
                        
                        \Filament\Notifications\Notification::make()
                            ->title('Quote Duplicated')
                            ->body("New quote {$newQuote->quote_number} has been created from {$record->quote_number}")
                            ->success()
                            ->send();
                        
                        return redirect()->route('filament.admin.resources.quotes.edit', ['record' => $newQuote]);
                    }),
                
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn ($record) => auth()->user()?->can('delete_quotes') ?? false),
            ])
            ->defaultSort('issue_date', 'desc');
    }

    private static function canApproveProcurement(Order $record): bool
    {
        if (! (auth()->user()?->can('edit_quotes') ?? false)) {
            return false;
        }

        $request = $record->procurementQuoteRequest;

        if (! $request instanceof ProcurementRequest) {
            return false;
        }

        if (! static::isActiveSupplierForRequest($request)) {
            return false;
        }

        return in_array($request->current_stage, [
            ProcurementWorkflowStage::QUOTED,
            ProcurementWorkflowStage::APPROVED,
        ], true);
    }

    private static function canStartSupplierReview(Order $record): bool
    {
        if (! (auth()->user()?->can('edit_quotes') ?? false)) {
            return false;
        }

        $request = $record->procurementQuoteRequest;

        if (! $request instanceof ProcurementRequest) {
            return false;
        }

        if (! static::isActiveSupplierForRequest($request)) {
            return false;
        }

        return in_array($request->current_stage, [
            ProcurementWorkflowStage::DRAFT,
            ProcurementWorkflowStage::SUBMITTED,
        ], true);
    }

    private static function isActiveSupplierForRequest(ProcurementRequest $request): bool
    {
        $user = auth()->user();

        if (! $user || $user->hasRole('super_admin')) {
            return false;
        }

        $currentAccount = app(CurrentAccountResolver::class)
            ->resolve(request(), $user)
            ->currentAccount;

        return $currentAccount !== null
            && (int) $request->supplier_account_id === (int) $currentAccount->id;
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
