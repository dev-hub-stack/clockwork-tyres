<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InvoiceResource\Pages;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\Payment;
use App\Modules\Orders\Models\Expense;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Customers\Models\Customer;
use App\Modules\Products\Models\ProductVariant;
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
use Filament\Actions\DeleteAction;
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

    /**
     * Global scope to only show invoices
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->invoices() // Uses the scope from Order model
            ->with(['customer', 'warehouse', 'payments', 'expenses'])
            ->latest('issue_date');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Invoice Information')
                    ->schema([
                        Select::make('customer_id')
                            ->label('Customer')
                            ->relationship('customer', 'business_name')
                            ->searchable(['business_name', 'first_name', 'last_name', 'email'])
                            ->required()
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->business_name ?? $record->name ?? 'Unknown Customer')
                            ->disabled(fn ($record) => $record !== null), // Can't change customer on existing invoice
                        
                        DatePicker::make('issue_date')
                            ->label('Issue Date')
                            ->required()
                            ->default(now()),
                        
                        DatePicker::make('valid_until')
                            ->label('Due Date')
                            ->required()
                            ->default(now()->addDays(30)),
                        
                        Select::make('order_status')
                            ->label('Order Status')
                            ->options([
                                'pending' => 'Pending',
                                'processing' => 'Processing',
                                'shipped' => 'Shipped',
                                'completed' => 'Completed',
                                'cancelled' => 'Cancelled',
                            ])
                            ->default('pending')
                            ->required(),
                        
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
                            ->dehydrated(false),
                    ])->columns(2),

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
                                    ->afterStateUpdated(function ($state, Set $set) {
                                        if ($state) {
                                            $variant = ProductVariant::with('product')->find($state);
                                            $set('unit_price', $variant->price ?? $variant->product->retail_price ?? 0);
                                            $set('quantity', 1);
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
                    
                Hidden::make('document_type')
                    ->default('invoice'),
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
                    ->searchable(['business_name', 'first_name', 'last_name'])
                    ->sortable(),
                
                BadgeColumn::make('payment_status')
                    ->label('Payment')
                    ->colors([
                        'warning' => 'pending',
                        'info' => 'partial',
                        'success' => 'paid',
                        'danger' => 'failed',
                        'secondary' => 'refunded',
                    ]),
                
                BadgeColumn::make('order_status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'pending',
                        'info' => 'processing',
                        'primary' => 'shipped',
                        'success' => 'completed',
                        'danger' => 'cancelled',
                    ]),
                
                TextColumn::make('total')
                    ->label('Amount')
                    ->money('AED')
                    ->sortable(),
                
                TextColumn::make('balance')
                    ->label('Balance')
                    ->money('AED')
                    ->getStateUsing(fn($record) => $record->outstanding_amount)
                    ->color(fn($state) => $state > 0 ? 'danger' : 'success')
                    ->sortable(),
                
                TextColumn::make('gross_profit')
                    ->label('Profit')
                    ->money('AED')
                    ->color(fn($state) => $state >= 0 ? 'success' : 'danger')
                    ->sortable()
                    ->toggleable()
                    ->tooltip(fn($record) => $record->hasExpensesRecorded() 
                        ? "Margin: {$record->profit_margin}% | Expenses: AED " . number_format($record->total_expenses, 2)
                        : 'Expenses not recorded yet'),
                
                TextColumn::make('profit_margin')
                    ->label('Margin %')
                    ->suffix('%')
                    ->color(fn($state) => $state >= 20 ? 'success' : ($state >= 10 ? 'warning' : 'danger'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                
                TextColumn::make('total_expenses')
                    ->label('Expenses')
                    ->money('AED')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                
                TextColumn::make('valid_until')
                    ->label('Due Date')
                    ->date()
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
                        'pending' => 'Pending',
                        'processing' => 'Processing',
                        'shipped' => 'Shipped',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ]),
                
                SelectFilter::make('customer_id')
                    ->label('Customer')
                    ->relationship('customer', 'business_name')
                    ->searchable()
                    ->preload()
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->business_name ?? $record->name ?? 'Unknown Customer'),
                
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
                            'documentType' => 'invoice',
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
                
                Action::make('recordPayment')
                    ->label('Record Payment')
                    ->icon('heroicon-o-currency-dollar')
                    ->color('success')
                    ->visible(fn($record) => !$record->isFullyPaid())
                    ->form([
                        TextInput::make('amount')
                            ->label('Amount')
                            ->numeric()
                            ->prefix('AED')
                            ->required()
                            ->default(fn($record) => $record->outstanding_amount)
                            ->helperText(fn($record) => "Outstanding amount: AED " . number_format($record->outstanding_amount, 2)),
                        
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
                            'payment_method' => $data['payment_method'],
                            'payment_date' => $data['payment_date'],
                            'reference_number' => $data['reference_number'] ?? null,
                            'bank_name' => $data['bank_name'] ?? null,
                            'cheque_number' => $data['cheque_number'] ?? null,
                            'notes' => $data['notes'] ?? null,
                            'status' => 'completed',
                        ]);
                        
                        Notification::make()
                            ->title('Payment Recorded')
                            ->body('Payment of AED ' . number_format($data['amount'], 2) . ' has been recorded')
                            ->success()
                            ->send();
                    }),
                
                Action::make('startProcessing')
                    ->label('Start Processing')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->color('primary')
                    ->visible(fn($record) => $record->order_status->value === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading('Start Processing Order')
                    ->modalDescription('This will mark the order as processing and allocate inventory from the selected warehouses.')
                    ->modalContent(function ($record) {
                        // Show stock availability summary
                        $items = $record->items;
                        $stockInfo = collect($items)->map(function ($item) {
                            if (!$item->warehouse_id) {
                                return [
                                    'product' => $item->product_name,
                                    'quantity' => $item->quantity,
                                    'status' => 'Non-Stock',
                                    'warning' => false,
                                ];
                            }
                            
                            $inventory = \App\Modules\Products\Models\ProductInventory::where('product_variant_id', $item->product_variant_id)
                                ->where('warehouse_id', $item->warehouse_id)
                                ->first();
                            
                            $available = $inventory ? $inventory->quantity : 0;
                            $hasStock = $available >= $item->quantity;
                            
                            return [
                                'product' => $item->product_name,
                                'quantity' => $item->quantity,
                                'available' => $available,
                                'warehouse' => $item->warehouse?->name ?? 'Unknown',
                                'status' => $hasStock ? 'In Stock' : 'Insufficient Stock',
                                'warning' => !$hasStock,
                            ];
                        });
                        
                        return view('filament.components.stock-availability', [
                            'stockInfo' => $stockInfo,
                        ]);
                    })
                    ->action(function ($record) {
                        $record->update([
                            'order_status' => OrderStatus::PROCESSING,
                        ]);
                        
                        // Inventory will be allocated via OrderObserver
                        
                        Notification::make()
                            ->title('Order Processing Started')
                            ->body("Order {$record->order_number} is now being processed. Inventory has been allocated.")
                            ->success()
                            ->send();
                    }),
                
                Action::make('recordExpenses')
                    ->label('Record Expenses & Calculate Profit')
                    ->icon('heroicon-o-calculator')
                    ->color('warning')
                    ->visible(fn($record) => $record->order_status->value === 'completed')
                    ->form([
                        \Filament\Forms\Components\Section::make('Expense Breakdown')
                            ->description('Enter all expenses related to this order to calculate profit')
                            ->schema([
                                \Filament\Forms\Components\Grid::make(2)
                                    ->schema([
                                        TextInput::make('cost_of_goods')
                                            ->label('Cost of Goods')
                                            ->numeric()
                                            ->prefix('AED')
                                            ->default(0)
                                            ->helperText('Direct product costs')
                                            ->reactive(),
                                        
                                        TextInput::make('shipping_cost')
                                            ->label('Shipping Cost')
                                            ->numeric()
                                            ->prefix('AED')
                                            ->default(0)
                                            ->helperText('Freight and shipping charges')
                                            ->reactive(),
                                        
                                        TextInput::make('duty_amount')
                                            ->label('Customs Duty')
                                            ->numeric()
                                            ->prefix('AED')
                                            ->default(0)
                                            ->helperText('Import duties and taxes')
                                            ->reactive(),
                                        
                                        TextInput::make('delivery_fee')
                                            ->label('Delivery Fee')
                                            ->numeric()
                                            ->prefix('AED')
                                            ->default(0)
                                            ->helperText('Last-mile delivery charges')
                                            ->reactive(),
                                        
                                        TextInput::make('installation_cost')
                                            ->label('Installation Cost')
                                            ->numeric()
                                            ->prefix('AED')
                                            ->default(0)
                                            ->helperText('Setup and installation fees')
                                            ->reactive(),
                                        
                                        TextInput::make('bank_fee')
                                            ->label('Bank Fee')
                                            ->numeric()
                                            ->prefix('AED')
                                            ->default(0)
                                            ->helperText('Wire transfer and banking fees')
                                            ->reactive(),
                                        
                                        TextInput::make('credit_card_fee')
                                            ->label('Credit Card Fee')
                                            ->numeric()
                                            ->prefix('AED')
                                            ->default(0)
                                            ->helperText('Payment processing fees')
                                            ->reactive(),
                                    ]),
                                
                                \Filament\Forms\Components\Placeholder::make('profit_preview')
                                    ->label('Profit Preview')
                                    ->content(function ($get, $record) {
                                        $revenue = $record->total ?? 0;
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
                                                        <p class='text-sm text-gray-600 dark:text-gray-400'>Revenue</p>
                                                        <p class='text-lg font-semibold'>AED " . number_format($revenue, 2) . "</p>
                                                    </div>
                                                    <div>
                                                        <p class='text-sm text-gray-600 dark:text-gray-400'>Total Expenses</p>
                                                        <p class='text-lg font-semibold text-red-600'>AED " . number_format($expenses, 2) . "</p>
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
                    ->label('Complete Order')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn($record) => $record->order_status->value === 'shipped')
                    ->requiresConfirmation()
                    ->modalHeading('Complete Order')
                    ->modalDescription('Mark this order as completed. Ensure payment has been received and customer is satisfied.')
                    ->form([
                        Select::make('payment_status')
                            ->label('Payment Status')
                            ->options([
                                'paid' => 'Paid in Full',
                                'partial' => 'Partially Paid',
                                'pending' => 'Payment Pending',
                            ])
                            ->default(fn($record) => $record->isFullyPaid() ? 'paid' : 'pending')
                            ->required(),
                        
                        Textarea::make('completion_notes')
                            ->label('Completion Notes (Optional)')
                            ->rows(2)
                            ->placeholder('Any final notes about this order...'),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update([
                            'order_status' => OrderStatus::COMPLETED,
                            'payment_status' => PaymentStatus::from($data['payment_status']),
                        ]);
                        
                        if (!empty($data['completion_notes'])) {
                            $record->update([
                                'notes' => $record->notes . "\n\nCompletion Notes: " . $data['completion_notes'],
                            ]);
                        }
                        
                        // TODO: Send completion email to customer
                        // Mail::to($record->customer->email)->send(new OrderCompletedMail($record));
                        
                        Notification::make()
                            ->title('Order Completed')
                            ->body("Order {$record->order_number} has been marked as completed")
                            ->success()
                            ->send();
                    }),
                
                Action::make('cancelOrder')
                    ->label('Cancel Order')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
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
                        // Deallocate inventory if order was processing
                        if ($record->order_status->value === 'processing') {
                            foreach ($record->items as $item) {
                                if ($item->allocated_quantity > 0 && $item->warehouse_id) {
                                    $inventory = \App\Modules\Products\Models\ProductInventory::where('product_variant_id', $item->product_variant_id)
                                        ->where('warehouse_id', $item->warehouse_id)
                                        ->first();
                                    
                                    if ($inventory) {
                                        $inventory->increment('quantity', $item->allocated_quantity);
                                    }
                                }
                                
                                // Reset allocated quantity
                                $item->update(['allocated_quantity' => 0]);
                            }
                            
                            // Delete OrderItemQuantity records
                            \App\Modules\Orders\Models\OrderItemQuantity::where('order_id', $record->id)->delete();
                        }
                        
                        $record->update([
                            'order_status' => OrderStatus::CANCELLED,
                            'notes' => $record->notes . "\n\nCancellation Reason: " . $data['cancellation_reason'],
                        ]);
                        
                        Notification::make()
                            ->title('Order Cancelled')
                            ->body("Order {$record->order_number} has been cancelled and inventory deallocated")
                            ->warning()
                            ->send();
                    }),
                
                EditAction::make(),
                DeleteAction::make(),
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
