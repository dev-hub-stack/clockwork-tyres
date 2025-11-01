<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ConsignmentResource\Pages\CreateConsignment;
use App\Filament\Resources\ConsignmentResource\Pages\EditConsignment;
use App\Filament\Resources\ConsignmentResource\Pages\ListConsignments;
use App\Filament\Resources\ConsignmentResource\Pages\ViewConsignment;
use App\Filament\Resources\ConsignmentResource\Schemas\ConsignmentForm;
use App\Filament\Resources\ConsignmentResource\Schemas\ConsignmentInfolist;
use App\Filament\Resources\ConsignmentResource\Tables\ConsignmentsTable;
use App\Modules\Consignments\Models\Consignment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ConsignmentResource extends Resource
{
    protected static ?string $model = Consignment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'consignment_number';

    /**
     * Eager load relationships for better performance
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['customer', 'warehouse', 'representative', 'items'])
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function form(Schema $schema): Schema
    {
        return ConsignmentForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ConsignmentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ConsignmentsTable::configure($table);
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
            'index' => ListConsignments::route('/'),
            'create' => CreateConsignment::route('/create'),
            'view' => ViewConsignment::route('/{record}'),
            'edit' => EditConsignment::route('/{record}/edit'),
        ];
    }
}
