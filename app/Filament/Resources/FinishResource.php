<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FinishResource\Pages;
use App\Filament\Support\PanelAccess;
use App\Modules\Products\Models\Finish;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;
use BackedEnum;
use UnitEnum;

class FinishResource extends Resource
{
    protected static ?string $model = Finish::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-swatch';

    protected static string|UnitEnum|null $navigationGroup = 'Products';

    public static function canViewAny(): bool
    {
        return PanelAccess::canAccessOperationalSurface('view_products');
    }

    public static function canCreate(): bool
    {
        return PanelAccess::canAccessOperationalSurface('create_products');
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return PanelAccess::canAccessOperationalSurface('edit_products');
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return PanelAccess::canAccessOperationalSurface('delete_products');
    }

    protected static ?int $navigationSort = 3;

    protected static ?string $pluralModelLabel = 'Finishes';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Finish Information')
                    ->schema([
                        TextInput::make('finish')
                            ->label('Finish Name')
                            ->required()
                            ->maxLength(255)
                            ->unique(Finish::class, 'finish', ignoreRecord: true)
                            ->helperText('e.g., Chrome, Matte Black, Gloss White, Bronze'),
                    ])->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('finish')
                    ->label('Finish Name')
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('products_count')
                    ->label('Products')
                    ->counts('products')
                    ->sortable(),
                
                TextColumn::make('productVariants_count')
                    ->label('Variants')
                    ->counts('productVariants')
                    ->sortable(),
                
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                
                TextColumn::make('external_id')
                    ->label('External ID')
                    ->toggleable(isToggledHiddenByDefault: true),
                
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // No filters needed for simple finish
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn ($record) => auth()->user()?->can('delete_products') ?? false),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ])
            ->defaultSort('finish');
    }

    public static function getRelations(): array
    {
        return [
            // We'll add ProductsRelationManager later if needed
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFinishes::route('/'),
            'create' => Pages\CreateFinish::route('/create'),
            'edit' => Pages\EditFinish::route('/{record}/edit'),
        ];
    }
}
