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
                        
                        Select::make('warehouse_id')
                            ->label('Warehouse')
                            ->relationship('warehouse', 'warehouse_name')
                            ->required()
                            ->preload()
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->warehouse_name ?? $record->code ?? 'Unknown Warehouse'),
                        
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
                            ->default(fn($record) => $record->outstanding_amount),
                        
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
                            ->success()
                            ->send();
                    }),
                
                Action::make('recordExpense')
                    ->label('Record Expense')
                    ->icon('heroicon-o-receipt-percent')
                    ->color('warning')
                    ->form([
                        Select::make('expense_type')
                            ->label('Expense Type')
                            ->options(Expense::getExpenseTypes())
                            ->required(),
                        
                        TextInput::make('amount')
                            ->label('Amount')
                            ->numeric()
                            ->prefix('AED')
                            ->required(),
                        
                        DatePicker::make('expense_date')
                            ->label('Expense Date')
                            ->default(now())
                            ->required(),
                        
                        TextInput::make('vendor_name')
                            ->label('Vendor Name')
                            ->maxLength(255),
                        
                        TextInput::make('vendor_reference')
                            ->label('Vendor Invoice/Receipt #')
                            ->maxLength(255),
                        
                        FileUpload::make('receipt_path')
                            ->label('Receipt/Invoice')
                            ->disk('public')
                            ->directory('expenses')
                            ->maxSize(10240),
                        
                        Textarea::make('description')
                            ->label('Description')
                            ->rows(2),
                        
                        Select::make('payment_status')
                            ->label('Payment Status')
                            ->options([
                                'unpaid' => 'Unpaid',
                                'paid' => 'Paid',
                                'pending' => 'Pending',
                            ])
                            ->default('unpaid')
                            ->required(),
                    ])
                    ->action(function ($record, array $data) {
                        Expense::create([
                            'order_id' => $record->id,
                            'customer_id' => $record->customer_id,
                            'recorded_by' => auth()->id(),
                            'expense_type' => $data['expense_type'],
                            'amount' => $data['amount'],
                            'expense_date' => $data['expense_date'],
                            'vendor_name' => $data['vendor_name'] ?? null,
                            'vendor_reference' => $data['vendor_reference'] ?? null,
                            'receipt_path' => $data['receipt_path'] ?? null,
                            'description' => $data['description'] ?? null,
                            'payment_status' => $data['payment_status'],
                        ]);
                        
                        Notification::make()
                            ->title('Expense Recorded')
                            ->success()
                            ->send();
                    }),
                
                Action::make('addTracking')
                    ->label('Add Tracking')
                    ->icon('heroicon-o-truck')
                    ->color('primary')
                    ->visible(fn($record) => !$record->isShipped())
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
                            ->maxLength(255),
                    ])
                    ->action(function ($record, array $data) {
                        $record->markAsShipped(
                            $data['tracking_number'],
                            $data['shipping_carrier'],
                            $data['tracking_url'] ?? null
                        );
                        
                        Notification::make()
                            ->title('Tracking Added')
                            ->body('Invoice marked as shipped')
                            ->success()
                            ->send();
                    }),
                
                Action::make('markCompleted')
                    ->label('Mark Completed')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn($record) => $record->order_status->value === 'shipped')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->markAsCompleted();
                        
                        Notification::make()
                            ->title('Invoice Completed')
                            ->success()
                            ->send();
                    }),
                
                EditAction::make(),
                DeleteAction::make(),
            ]);
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
            'edit' => Pages\EditInvoice::route('/{record}/edit'),
        ];
    }
}
