<?php

namespace App\Filament\Resources\Settings\TaxSettings;

use App\Filament\Resources\Settings\TaxSettings\Pages\CreateTaxSetting;
use App\Filament\Resources\Settings\TaxSettings\Pages\EditTaxSetting;
use App\Filament\Resources\Settings\TaxSettings\Pages\ListTaxSettings;
use App\Filament\Resources\Settings\TaxSettings\Schemas\TaxSettingForm;
use App\Filament\Resources\Settings\TaxSettings\Tables\TaxSettingsTable;
use App\Modules\Settings\Models\Settings\TaxSetting;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TaxSettingResource extends Resource
{
    protected static ?string $model = TaxSetting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return TaxSettingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TaxSettingsTable::configure($table);
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
            'index' => ListTaxSettings::route('/'),
            'create' => CreateTaxSetting::route('/create'),
            'edit' => EditTaxSetting::route('/{record}/edit'),
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
