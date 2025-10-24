<?php

namespace App\Filament\Resources\Settings\CompanyBrandings;

use App\Filament\Resources\Settings\CompanyBrandings\Pages\CreateCompanyBranding;
use App\Filament\Resources\Settings\CompanyBrandings\Pages\EditCompanyBranding;
use App\Filament\Resources\Settings\CompanyBrandings\Pages\ListCompanyBrandings;
use App\Filament\Resources\Settings\CompanyBrandings\Schemas\CompanyBrandingForm;
use App\Filament\Resources\Settings\CompanyBrandings\Tables\CompanyBrandingsTable;
use App\Modules\Settings\Models\CompanyBranding;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class CompanyBrandingResource extends Resource
{
    protected static ?string $model = CompanyBranding::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'company_name';
    
    protected static string|UnitEnum|null $navigationGroup = 'Settings';
    
    protected static ?string $navigationLabel = 'Company Branding';
    
    protected static ?int $navigationSort = 1;
    
    // Hidden from navigation - using consolidated Settings page instead
    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return CompanyBrandingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CompanyBrandingsTable::configure($table);
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
            'index' => ListCompanyBrandings::route('/'),
            'create' => CreateCompanyBranding::route('/create'),
            'edit' => EditCompanyBranding::route('/{record}/edit'),
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
