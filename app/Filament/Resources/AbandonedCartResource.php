<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AbandonedCartResource\Pages;
use App\Modules\Settings\Models\CurrencySetting;
use App\Modules\Wholesale\Cart\Models\Cart;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;
use BackedEnum;

class AbandonedCartResource extends Resource
{
    protected static ?string $model = Cart::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationLabel = 'Abandoned Carts';

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Abandoned Cart';

    protected static ?string $pluralModelLabel = 'Abandoned Carts';

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_quotes') ?? false;
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

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()->count();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->abandoned()
            ->with(['dealer', 'shippingAddress', 'items.variant.product.brand', 'addons.addon'])
            ->withCount(['items', 'addons'])
            ->withSum('items as items_quantity_sum', 'quantity')
            ->withSum('addons as addons_quantity_sum', 'quantity')
            ->latest('updated_at');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('dealer_name')
                    ->label('Dealer')
                    ->getStateUsing(function (Cart $record): string {
                        $dealer = $record->dealer;

                        if (! $dealer) {
                            return 'Guest';
                        }

                        return $dealer->business_name
                            ?? trim(($dealer->first_name ?? '') . ' ' . ($dealer->last_name ?? ''))
                            ?: 'Unknown Dealer';
                    })
                    ->searchable(['dealer.business_name', 'dealer.first_name', 'dealer.last_name', 'dealer.email'])
                    ->sortable(false),

                TextColumn::make('session_id')
                    ->label('Session')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('cart_details')
                    ->label('Cart Details')
                    ->getStateUsing(function (Cart $record): string {
                        $details = [];

                        foreach ($record->items as $item) {
                            $variant = $item->variant;
                            $product = $variant?->product;
                            $brandName = $product?->brand?->name;
                            $variantName = $variant?->full_name ?: $product?->product_full_name ?: $product?->name ?: 'Wheel';
                            $details[] = trim(implode(' ', array_filter([$brandName, $variantName]))) . ' x' . (int) $item->quantity;
                        }

                        foreach ($record->addons as $addonItem) {
                            $addonName = $addonItem->addon?->title ?: 'Addon';
                            $details[] = $addonName . ' x' . (int) $addonItem->quantity;
                        }

                        return implode(', ', $details);
                    })
                    ->wrap()
                    ->searchable(false)
                    ->sortable(false),

                TextColumn::make('items_summary')
                    ->label('Cart Qty')
                    ->getStateUsing(fn (Cart $record): int => (int) (($record->items_quantity_sum ?? 0) + ($record->addons_quantity_sum ?? 0)))
                    ->sortable(false),

                TextColumn::make('total_lines')
                    ->label('Lines')
                    ->getStateUsing(fn (Cart $record): int => (int) $record->items_count + (int) $record->addons_count)
                    ->sortable(false),

                TextColumn::make('shippingAddress.country')
                    ->label('Country')
                    ->toggleable(),

                TextColumn::make('total')
                    ->label('Cart Total')
                    ->money(fn () => CurrencySetting::getBase()?->currency_code ?? 'AED')
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label('Last Activity')
                    ->since()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('dealer_id')
                    ->label('Dealer')
                    ->relationship('dealer', 'business_name')
                    ->searchable()
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->business_name ?? $record->name ?? 'Unknown Dealer')
                    ->preload(),

                Filter::make('updated_at')
                    ->label('Last Activity')
                    ->form([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('updated_at', '>=', $date),
                            )
                            ->when(
                                $data['until'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('updated_at', '<=', $date),
                            );
                    }),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAbandonedCarts::route('/'),
        ];
    }
}