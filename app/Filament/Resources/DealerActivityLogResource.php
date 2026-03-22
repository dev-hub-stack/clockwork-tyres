<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DealerActivityLogResource\Pages;
use App\Models\ActivityLog;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;
use BackedEnum;

class DealerActivityLogResource extends Resource
{
    protected static ?string $model = ActivityLog::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationLabel = 'Dealer Activity Log';

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
            ->whereNotNull('customer_id')
            ->with('customer')
            ->latest('created_at');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('customer.name')
                    ->label('Dealer')
                    ->placeholder('Unknown Dealer')
                    ->sortable()
                    ->searchable(['customer.business_name', 'customer.first_name', 'customer.last_name', 'customer.email']),

                TextColumn::make('customer.email')
                    ->label('Email')
                    ->toggleable()
                    ->searchable(),

                TextColumn::make('action_label')
                    ->label('Action')
                    ->badge(),

                TextColumn::make('model_label')
                    ->label('Entity')
                    ->toggleable(),

                TextColumn::make('description')
                    ->label('Description')
                    ->wrap()
                    ->searchable(),

                TextColumn::make('ip_address')
                    ->label('IP Address')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('customer_id')
                    ->label('Dealer')
                    ->relationship('customer', 'business_name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->name)
                    ->searchable()
                    ->preload(),

                SelectFilter::make('action')
                    ->label('Action')
                    ->options(ActivityLog::DEALER_ACTION_LABELS),

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
            'index' => Pages\ListDealerActivityLogs::route('/'),
        ];
    }
}