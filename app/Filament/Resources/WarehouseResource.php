<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WarehouseResource\Pages;
use App\Filament\Support\PanelAccess;
use App\Modules\Accounts\Support\CurrentAccountResolver;
use App\Modules\Inventory\Models\Warehouse;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;
use BackedEnum;

class WarehouseResource extends Resource
{
    protected static ?string $model = Warehouse::class;

    protected static string|UnitEnum|null $navigationGroup = 'Inventory';

    public static function canViewAny(): bool
    {
        return PanelAccess::canAccessOperationalSurface('view_warehouses');
    }

    public static function canCreate(): bool
    {
        return PanelAccess::canAccessOperationalSurface('create_warehouses');
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return PanelAccess::canAccessOperationalSurface('edit_warehouses');
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return PanelAccess::canAccessOperationalSurface('delete_warehouses');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Warehouse Information')
                    ->schema([
                        TextInput::make('warehouse_name')
                            ->required()
                            ->maxLength(255)
                            ->label('Warehouse Name'),

                        TextInput::make('code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(50)
                            ->label('Warehouse Code')
                            ->helperText('Unique code for this warehouse (e.g., WH-MAIN, WH-EU)'),

                        Toggle::make('status')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Active warehouses appear in inventory grids'),

                        Toggle::make('is_primary')
                            ->label('Primary Warehouse')
                            ->default(false)
                            ->helperText('Only one warehouse can be primary'),
                    ])->columns(2),

                Section::make('Location Information')
                    ->schema([
                        TextInput::make('lat')
                            ->label('Latitude')
                            ->numeric()
                            ->nullable()
                            ->helperText('Latitude coordinate'),

                        TextInput::make('lng')
                            ->label('Longitude')
                            ->numeric()
                            ->nullable()
                            ->helperText('Longitude coordinate'),
                    ])->columns(2)
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('warehouse_name')
                    ->label('Warehouse Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info'),

                IconColumn::make('status')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                IconColumn::make('is_primary')
                    ->label('Primary')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('inventories_count')
                    ->counts('inventories')
                    ->label('Inventory Items')
                    ->sortable()
                    ->badge()
                    ->color('success'),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('status')
                    ->label('Active Status')
                    ->boolean()
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only')
                    ->native(false),

                TernaryFilter::make('is_primary')
                    ->label('Primary Warehouse')
                    ->boolean()
                    ->trueLabel('Primary only')
                    ->falseLabel('Non-primary only')
                    ->native(false),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn ($record) => auth()->user()?->can('delete_warehouses') ?? false),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (! auth()->check() || ! request()) {
            return $query->whereRaw('1 = 0');
        }

        $currentAccountId = app(CurrentAccountResolver::class)
            ->resolve(request(), auth()->user())
            ->currentAccount?->id;

        if (! $currentAccountId) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('account_id', $currentAccountId);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWarehouses::route('/'),
            'create' => Pages\CreateWarehouse::route('/create'),
            'edit' => Pages\EditWarehouse::route('/{record}/edit'),
        ];
    }
}
