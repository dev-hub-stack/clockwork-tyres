<?php

namespace App\Filament\Resources\Settings\CurrencySettings;

use App\Filament\Resources\Settings\CurrencySettings\Pages\CreateCurrencySetting;
use App\Filament\Resources\Settings\CurrencySettings\Pages\EditCurrencySetting;
use App\Filament\Resources\Settings\CurrencySettings\Pages\ListCurrencySettings;
use App\Filament\Resources\Settings\CurrencySettings\Schemas\CurrencySettingForm;
use App\Filament\Resources\Settings\CurrencySettings\Tables\CurrencySettingsTable;
use App\Modules\Settings\Models\CurrencySetting;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class CurrencySettingResource extends Resource
{
    protected static ?string $model = CurrencySetting::class;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_settings') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('edit_settings') ?? false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->can('edit_settings') ?? false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->can('edit_settings') ?? false;
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'currency_name';
    
    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return CurrencySettingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CurrencySettingsTable::configure($table);
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
            'index' => ListCurrencySettings::route('/'),
            'create' => CreateCurrencySetting::route('/create'),
            'edit' => EditCurrencySetting::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
