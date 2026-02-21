<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

class OrderHistoryRelationManager extends RelationManager
{
    protected static bool $isLazy = true;

    protected static string $relationship = 'orders';

    protected static ?string $title = 'Order History';

    public function placeholder(): View
    {
        return view('components.loading-placeholder');
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('reference')
                    ->label('Reference')
                    ->getStateUsing(fn ($record) => $record->order_number ?: $record->quote_number ?: '—')
                    ->searchable(query: function (Builder $query, string $search) {
                        $query->where('order_number', 'like', "%{$search}%")
                              ->orWhere('quote_number', 'like', "%{$search}%");
                    })
                    ->url(fn ($record) => $record->order_number
                        ? route('filament.admin.resources.invoices.view', $record)
                        : route('filament.admin.resources.quotes.view', $record))
                    ->color('primary')
                    ->weight('medium'),

                Tables\Columns\BadgeColumn::make('type')
                    ->label('Type')
                    ->getStateUsing(fn ($record) => $record->order_number ? 'Invoice' : 'Quote')
                    ->colors([
                        'success' => 'Invoice',
                        'warning' => 'Quote',
                    ]),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->getStateUsing(function ($record) {
                        if ($record->order_number && $record->order_status) {
                            return $record->order_status->label();
                        }
                        if ($record->quote_number && $record->quote_status) {
                            return $record->quote_status->label();
                        }
                        return '—';
                    })
                    ->badge(),

                Tables\Columns\TextColumn::make('payment_status')
                    ->label('Payment')
                    ->formatStateUsing(fn ($state) => $state?->label() ?? '—')
                    ->badge()
                    ->color(fn ($state) => match ($state?->value ?? '') {
                        'paid'           => 'success',
                        'partial'        => 'warning',
                        'pending'        => 'danger',
                        'refunded'       => 'info',
                        default          => 'gray',
                    }),

                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->formatStateUsing(fn ($state) => 'AED ' . number_format($state, 2))
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Type')
                    ->options(['quote' => 'Quotes', 'invoice' => 'Invoices'])
                    ->query(function (Builder $query, array $data) {
                        if ($data['value'] === 'quote') {
                            $query->whereNotNull('quote_number')->whereNull('order_number');
                        } elseif ($data['value'] === 'invoice') {
                            $query->whereNotNull('order_number');
                        }
                    }),
            ])
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateHeading('No orders yet')
            ->emptyStateDescription('This customer has no quotes or invoices.');
    }
}
