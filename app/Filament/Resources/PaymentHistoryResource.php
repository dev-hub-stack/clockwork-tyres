<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentHistoryResource\Pages;
use App\Modules\Orders\Enums\DocumentType;
use App\Modules\Orders\Models\Payment;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;
use BackedEnum;

class PaymentHistoryResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Payment History Log';

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 21;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_reports') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['order', 'customer', 'recordedBy'])
            ->whereHas('order', fn (Builder $query) => $query->where('document_type', DocumentType::INVOICE))
            ->latest('created_at');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Recorded At')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('order.order_number')
                    ->label('Invoice #')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->placeholder('N/A')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('amount')
                    ->money('AED')
                    ->sortable(),

                TextColumn::make('payment_method')
                    ->label('Method')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ucfirst(str_replace('_', ' ', data_get($state, 'value', $state ?? 'unknown')))),

                TextColumn::make('payment_date')
                    ->label('Payment Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('recordedBy.name')
                    ->label('Recorded By')
                    ->placeholder('System')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('notes')
                    ->label('Notes')
                    ->limit(50)
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('recorded_by')
                    ->label('Recorded By')
                    ->relationship('recordedBy', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('payment_method')
                    ->label('Method')
                    ->options([
                        'cash' => 'Cash',
                        'card' => 'Credit/Debit Card',
                        'credit_card' => 'Credit Card',
                        'bank_transfer' => 'Bank Transfer',
                        'cheque' => 'Cheque',
                        'check' => 'Check',
                        'online' => 'Online Payment',
                        'paypal' => 'PayPal',
                        'stripe' => 'Stripe',
                    ]),

                Filter::make('created_at')
                    ->label('Date Range')
                    ->form([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['until'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPaymentHistories::route('/'),
        ];
    }
}