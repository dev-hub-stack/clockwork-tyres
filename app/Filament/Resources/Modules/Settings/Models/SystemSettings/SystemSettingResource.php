<?php

namespace App\Filament\Resources\Modules\Settings\Models\SystemSettings;

use App\Filament\Resources\Modules\Settings\Models\SystemSettings\Pages\CreateSystemSetting;
use App\Filament\Resources\Modules\Settings\Models\SystemSettings\Pages\EditSystemSetting;
use App\Filament\Resources\Modules\Settings\Models\SystemSettings\Pages\ListSystemSettings;
use App\Filament\Resources\Modules\Settings\Models\SystemSettings\Schemas\SystemSettingForm;
use App\Filament\Resources\Modules\Settings\Models\SystemSettings\Tables\SystemSettingsTable;
use App\Models\Modules\Settings\Models\SystemSetting;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SystemSettingResource extends Resource
{
    protected static ?string $model = SystemSetting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'key';
    
    protected static ?string $navigationLabel = 'System Configuration';
    
    protected static string|\UnitEnum|null $navigationGroup = 'Administration';
    
    protected static ?int $navigationSort = 99;

    public static function canViewAny(): bool
    {
        return auth()->user()->can('view_settings');
    }

    public static function canCreate(): bool
    {
        return auth()->user()->can('edit_settings');
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()->can('edit_settings');
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()->can('edit_settings');
    }

    public static function form(Schema $schema): Schema
    {
        return SystemSettingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SystemSettingsTable::configure($table);
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
            'index' => ListSystemSettings::route('/'),
            'create' => CreateSystemSetting::route('/create'),
            'edit' => EditSystemSetting::route('/{record}/edit'),
        ];
    }
}
