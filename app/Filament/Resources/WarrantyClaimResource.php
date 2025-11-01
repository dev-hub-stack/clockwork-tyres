<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WarrantyClaimResource\Pages;
use App\Filament\Resources\WarrantyClaimResource\Schemas\WarrantyClaimForm;
use App\Filament\Resources\WarrantyClaimResource\Tables\WarrantyClaimsTable;
use App\Modules\Warranties\Models\WarrantyClaim;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class WarrantyClaimResource extends Resource
{
    protected static ?string $model = WarrantyClaim::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    
    protected static string|\UnitEnum|null $navigationGroup = 'Sales';
    
    protected static ?int $navigationSort = 6;
    
    protected static ?string $recordTitleAttribute = 'claim_number';
    
    protected static ?string $navigationLabel = 'Warranty Claims';
    
    protected static ?string $modelLabel = 'Warranty Claim';
    
    protected static ?string $pluralModelLabel = 'Warranty Claims';

    public static function form(Schema $schema): Schema
    {
        return WarrantyClaimForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WarrantyClaimsTable::configure($table);
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
            'index' => Pages\ListWarrantyClaims::route('/'),
            'create' => Pages\CreateWarrantyClaim::route('/create'),
            'view' => Pages\ViewWarrantyClaim::route('/{record}'),
            'edit' => Pages\EditWarrantyClaim::route('/{record}/edit'),
        ];
    }
    
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['customer', 'warehouse', 'invoice', 'items.productVariant', 'representative'])
            ->withCount('items')
            ->latest('claim_date');
    }
}