<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductModelResource\Pages;
use App\Modules\Products\Models\ProductModel;
use App\Modules\Products\Models\Brand;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
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

    protected static ?string $navigationLabel = 'Models';

    protected static ?string $modelLabel = 'Product Model';

    protected static ?string $pluralModelLabel = 'Product Models';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Model Information')
                    ->schema([
                        Select::make('brand_id')
                            ->label('Brand')
                            ->relationship('brand', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('Select the brand for this model'),
                        
                        TextInput::make('name')
                            ->label('Model Name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, callable $set) => 
                                $set('slug', Str::slug($state))
                            ),
                        
                        TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ProductModel::class, 'slug', ignoreRecord: true)
                            ->helperText('URL-friendly version of the name'),
                        
                        Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('Status & External Data')
                    ->schema([
                        Select::make('status')
                            ->label('Status')
                            ->options([
                                1 => 'Active',
                                0 => 'Inactive',
                            ])
                            ->default(1)
                            ->required(),
                        
                        TextInput::make('external_id')
                            ->label('External ID')
                            ->maxLength(255)
                            ->helperText('ID from external system (e.g., old database)'),
                        
                        TextInput::make('external_source')
                            ->label('External Source')
                            ->maxLength(100)
                            ->helperText('Source system name (e.g., "old_reporting")'),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('brand.name')
                    ->label('Brand')
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('name')
                    ->label('Model Name')
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                
                TextColumn::make('products_count')
                    ->label('Products')
                    ->counts('products')
                    ->sortable(),
                
                BadgeColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn (int $state): string => $state === 1 ? 'Active' : 'Inactive')
                    ->colors([
                        'success' => 1,
                        'danger' => 0,
                    ]),
                
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
                SelectFilter::make('brand_id')
                    ->label('Brand')
                    ->relationship('brand', 'name')
                    ->searchable()
                    ->preload(),
                
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        1 => 'Active',
                        0 => 'Inactive',
                    ]),
                
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
                ForceDeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
                RestoreBulkAction::make(),
                ForceDeleteBulkAction::make(),
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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
