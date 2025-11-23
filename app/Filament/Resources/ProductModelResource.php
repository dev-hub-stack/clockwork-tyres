<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductModelResource\Pages;
use App\Modules\Products\Models\ProductModel;
use App\Modules\Products\Models\Brand;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
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

class ProductModelResource extends Resource
{
    protected static ?string $model = ProductModel::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-cube';

    protected static string|UnitEnum|null $navigationGroup = 'Products';

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return auth()->user()->can('view_products');
    }

    public static function canCreate(): bool
    {
        return auth()->user()->can('create_products');
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()->can('edit_products');
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()->can('delete_products');
    }

    protected static ?string $navigationLabel = 'Models'; // Changed from ?string to string|UnitEnum|null

    protected static ?string $modelLabel = 'Product Model';

    protected static ?string $pluralModelLabel = 'Product Models';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Model Information')
                    ->schema([
                        TextInput::make('name')
                            ->label('Model Name')
                            ->required()
                            ->maxLength(255)
                            ->unique(ProductModel::class, 'name', ignoreRecord: true)
                            ->helperText('Unique model name (e.g., "D554", "Force F01")'),
                        
                        FileUpload::make('image')
                            ->label('Model Image')
                            ->image()
                            ->directory('models/images')
                            ->maxSize(2048)
                            ->helperText('Upload model image (max 2MB)'),
                    ])->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')
                    ->label('Image')
                    ->circular()
                    ->defaultImageUrl(url('/images/placeholder-model.png')),
                
                TextColumn::make('name')
                    ->label('Model Name')
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('products_count')
                    ->label('Products')
                    ->counts('products')
                    ->sortable(),
                
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
                // No filters needed for simple model
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ])
            ->defaultSort('name');
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
            'index' => Pages\ListProductModels::route('/'),
            'create' => Pages\CreateProductModel::route('/create'),
            'edit' => Pages\EditProductModel::route('/{record}/edit'),
        ];
    }
}
