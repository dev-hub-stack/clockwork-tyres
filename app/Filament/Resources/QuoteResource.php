<?php

namespace App\Filament\Resources;

use App\Filament\Resources\QuoteResource\Pages;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Enums\QuoteStatus;
use App\Modules\Orders\Services\QuoteConversionService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Actions\Action;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

class QuoteResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    
    protected static ?string $navigationLabel = 'Quotes & Proformas';
    
    protected static ?string $navigationGroup = 'Sales';
    
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

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Quote Information')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Select::make('customer_id')
                                    ->label('Customer')
                                    ->relationship('customer', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('name')
                                            ->required()
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('phone')
                                            ->tel()
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('email')
                                            ->email()
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('tax_registration_number')
                                            ->maxLength(255),
                                    ])
                                    ->createOptionModalHeading('Add New Customer')
                                    ->columnSpan(1),

                                Forms\Components\Select::make('warehouse_id')
                                    ->label('Warehouse')
                                    ->relationship('warehouse', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->columnSpan(1),

                                Forms\Components\DatePicker::make('issue_date')
                                    ->label('Quote Date')
                                    ->default(now())
                                    ->required()
                                    ->columnSpan(1),
                            ]),

                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\DatePicker::make('valid_until')
                                    ->label('Valid Until')
                                    ->default(now()->addDays(30))
                                    ->columnSpan(1),

                                Forms\Components\Select::make('currency')
                                    ->options([
                                        'AED' => 'AED (د.إ)',
                                        'USD' => 'USD ($)',
                                        'EUR' => 'EUR (€)',
                                    ])
                                    ->default('AED')
                                    ->required()
                                    ->columnSpan(1),

                                Forms\Components\Radio::make('tax_inclusive')
                                    ->label('Tax Type')
                                    ->boolean()
                                    ->options([
                                        false => 'VAT on Sales (5%)',
                                        true => 'Zero rated sales (0%)',
                                    ])
                                    ->default(false)
                                    ->inline()
                                    ->columnSpan(1),
                            ]),
                    ])
                    ->columns(1),

                Forms\Components\Section::make('Line Items')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->relationship('items')
                            ->schema([
                                Forms\Components\Grid::make(12)
                                    ->schema([
                                        Forms\Components\Select::make('product_variant_id')
                                            ->label('Product')
                                            ->options(function () {
                                                return \App\Modules\Products\Models\ProductVariant::query()
                                                    ->with(['product.brand', 'product.model', 'product.finish'])
                                                    ->get()
                                                    ->mapWithKeys(function ($variant) {
                                                        $label = trim(sprintf(
                                                            '%s - %s | %s | %s | Size: %s | Bolt: %s | Offset: %s',
                                                            $variant->sku ?? 'No SKU',
                                                            $variant->product->brand->name ?? '',
                                                            $variant->product->model->name ?? '',
                                                            $variant->product->finish->name ?? '',
                                                            $variant->size ?? 'N/A',
                                                            $variant->bolt_pattern ?? 'N/A',
                                                            $variant->offset ?? 'N/A'
                                                        ));
                                                        return [$variant->id => $label];
                                                    })
                                                    ->toArray();
                                            })
                                            ->searchable()
                                            ->preload()
                                            ->reactive()
                                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                                if ($state) {
                                                    $variant = \App\Modules\Products\Models\ProductVariant::with('product')->find($state);
                                                    if ($variant) {
                                                        $set('unit_price', $variant->product->retail_price ?? 0);
                                                        $set('quantity', 1);
                                                    }
                                                }
                                            })
                                            ->columnSpan(5),

                                        Forms\Components\TextInput::make('quantity')
                                            ->numeric()
                                            ->default(1)
                                            ->required()
                                            ->minValue(1)
                                            ->reactive()
                                            ->columnSpan(2),

                                        Forms\Components\TextInput::make('unit_price')
                                            ->numeric()
                                            ->prefix(fn (Forms\Get $get) => $get('../../currency') ?? 'AED')
                                            ->required()
                                            ->reactive()
                                            ->columnSpan(2),

                                        Forms\Components\TextInput::make('discount')
                                            ->numeric()
                                            ->prefix(fn (Forms\Get $get) => $get('../../currency') ?? 'AED')
                                            ->default(0)
                                            ->reactive()
                                            ->columnSpan(2),

                                        Forms\Components\Placeholder::make('line_total')
                                            ->label('Total')
                                            ->content(function (Forms\Get $get) {
                                                $quantity = (float) ($get('quantity') ?? 0);
                                                $price = (float) ($get('unit_price') ?? 0);
                                                $discount = (float) ($get('discount') ?? 0);
                                                $total = ($quantity * $price) - $discount;
                                                $currency = $get('../../currency') ?? 'AED';
                                                return Number::currency($total, $currency);
                                            })
                                            ->columnSpan(1),
                                    ]),
                            ])
                            ->addActionLabel('Add Line Item')
                            ->columns(1)
                            ->defaultItems(0)
                            ->reorderable(false)
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => 
                                isset($state['product_variant_id']) 
                                    ? \App\Modules\Products\Models\ProductVariant::find($state['product_variant_id'])?->sku ?? 'New Item'
                                    : 'New Item'
                            ),
                    ]),

                Forms\Components\Section::make('Additional Details')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('shipping')
                                    ->numeric()
                                    ->prefix(fn (Forms\Get $get) => $get('currency') ?? 'AED')
                                    ->default(0)
                                    ->columnSpan(1),

                                Forms\Components\Textarea::make('order_notes')
                                    ->label('Notes')
                                    ->rows(3)
                                    ->columnSpan(1),
                            ]),
                    ])
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('issue_date')
                    ->label('Date')
                    ->date('Y-m-d')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('quote_number')
                    ->label('Number')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Quote number copied')
                    ->weight('bold')
                    ->color('primary'),

                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable()
                    ->limit(30)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        if (strlen($state) <= 30) {
                            return null;
                        }
                        return $state;
                    }),

                BadgeColumn::make('quote_status')
                    ->label('Status')
                    ->formatStateUsing(fn (QuoteStatus $state): string => $state->label())
                    ->colors([
                        'secondary' => QuoteStatus::DRAFT->value,
                        'primary' => QuoteStatus::SENT->value,
                        'success' => QuoteStatus::APPROVED->value,
                        'danger' => QuoteStatus::REJECTED->value,
                        'info' => QuoteStatus::CONVERTED->value,
                    ]),

                TextColumn::make('total')
                    ->label('Amount')
                    ->money(fn (Order $record) => $record->currency ?? 'AED')
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('valid_until')
                    ->label('Valid Until')
                    ->date('Y-m-d')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('warehouse.name')
                    ->label('Warehouse')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('quote_status')
                    ->label('Status')
                    ->options([
                        QuoteStatus::DRAFT->value => 'Draft',
                        QuoteStatus::SENT->value => 'Sent',
                        QuoteStatus::APPROVED->value => 'Approved',
                        QuoteStatus::REJECTED->value => 'Rejected',
                        QuoteStatus::CONVERTED->value => 'Converted',
                    ])
                    ->multiple(),

                Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('Date From'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Date Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('issue_date', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('issue_date', '<=', $date),
                            );
                    }),

                SelectFilter::make('customer')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple(),
            ])
            ->actions([
                // Preview action with slide-over
                Action::make('preview')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->modalContent(fn (Order $record) => view(
                        'filament.resources.quote-resource.preview',
                        ['record' => $record]
                    ))
                    ->modalWidth('7xl')
                    ->slideOver()
                    ->modalHeading(fn (Order $record) => "Quote {$record->quote_number}")
                    ->modalActions([
                        Action::make('send')
                            ->label('Send Quote')
                            ->icon('heroicon-o-paper-airplane')
                            ->visible(fn (Order $record) => $record->quote_status?->canSend() ?? false)
                            ->requiresConfirmation()
                            ->action(function (Order $record) {
                                // Will implement email sending later
                                $record->update([
                                    'quote_status' => QuoteStatus::SENT,
                                    'sent_at' => now(),
                                ]);
                            })
                            ->successNotificationTitle('Quote sent successfully!'),

                        Action::make('approve')
                            ->label('Approve Quote')
                            ->icon('heroicon-o-check-circle')
                            ->visible(fn (Order $record) => 
                                $record->quote_status === QuoteStatus::SENT
                            )
                            ->requiresConfirmation()
                            ->action(function (Order $record) {
                                $record->update([
                                    'quote_status' => QuoteStatus::APPROVED,
                                    'approved_at' => now(),
                                ]);
                            })
                            ->successNotificationTitle('Quote approved!'),

                        Action::make('convert')
                            ->label('Convert to Invoice')
                            ->icon('heroicon-o-arrow-right-circle')
                            ->color('success')
                            ->visible(fn (Order $record) => $record->canConvertToInvoice())
                            ->requiresConfirmation()
                            ->modalHeading('Convert Quote to Invoice')
                            ->modalDescription('This will convert the quote to an invoice. This action cannot be undone.')
                            ->action(function (Order $record) {
                                $conversionService = app(QuoteConversionService::class);
                                $invoice = $conversionService->convertQuoteToInvoice($record);
                                
                                return redirect()->route('filament.admin.resources.invoices.view', ['record' => $invoice]);
                            })
                            ->successNotificationTitle('Quote converted to invoice successfully!')
                            ->successNotificationBody(fn (Order $record) => "Invoice #{$record->order_number} created"),

                        Tables\Actions\Action::make('close')
                            ->label('Close')
                            ->color('gray')
                            ->close(),
                    ]),

                Tables\Actions\EditAction::make(),
                
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (Order $record) => 
                        $record->quote_status === QuoteStatus::DRAFT
                    ),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    
                    Tables\Actions\BulkAction::make('sendQuotes')
                        ->label('Send Selected Quotes')
                        ->icon('heroicon-o-paper-airplane')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                if ($record->quote_status?->canSend()) {
                                    $record->update([
                                        'quote_status' => QuoteStatus::SENT,
                                        'sent_at' => now(),
                                    ]);
                                }
                            }
                        })
                        ->successNotificationTitle('Selected quotes sent successfully!')
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->emptyStateHeading('No quotes yet')
            ->emptyStateDescription('Create your first quote to get started.')
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->label('Create Quote')
                    ->icon('heroicon-o-plus'),
            ])
            ->defaultSort('issue_date', 'desc')
            ->poll('30s'); // Auto-refresh every 30 seconds
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
            'edit' => Pages\EditQuote::route('/{record}/edit'),
            'view' => Pages\ViewQuote::route('/{record}'),
        ];
    }
    
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::quotes()
            ->whereIn('quote_status', [QuoteStatus::DRAFT, QuoteStatus::SENT])
            ->count();
    }
    
    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
