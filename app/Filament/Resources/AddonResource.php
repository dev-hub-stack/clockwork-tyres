<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AddonResource\Pages;
use App\Models\Addon;
use App\Models\AddonCategory;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Schemas\Get;
use Filament\Schemas\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use BackedEnum;
use UnitEnum;

class AddonResource extends Resource
{
    protected static ?string $model = Addon::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-puzzle-piece';

    protected static UnitEnum|string|null $navigationGroup = 'Products';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Add Ons';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Category Information')
                    ->schema([
                        Select::make('addon_category_id')
                            ->label('Category')
                            ->relationship('category', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                // Auto-generate part number prefix based on category
                                if ($state) {
                                    $category = AddonCategory::find($state);
                                    if ($category && !$get('part_number')) {
                                        $prefix = strtoupper(substr($category->slug, 0, 3));
                                        $set('part_number', $prefix . '-');
                                    }
                                }
                            }),
                    ]),

                Section::make('Product Details')
                    ->schema([
                        TextInput::make('title')
                            ->label('Title')
                            ->required()
                            ->maxLength(180)
                            ->columnSpanFull(),

                        TextInput::make('part_number')
                            ->label('Part Number')
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),

                        Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Pricing')
                    ->schema([
                        TextInput::make('price')
                            ->label('Retail Price')
                            ->numeric()
                            ->prefix('$')
                            ->required()
                            ->default(0),

                        TextInput::make('wholesale_price')
                            ->label('Wholesale Price')
                            ->numeric()
                            ->prefix('$'),

                        Toggle::make('tax_inclusive')
                            ->label('Tax Inclusive')
                            ->default(false),
                    ])
                    ->columns(3),

                Section::make('Images')
                    ->schema([
                        FileUpload::make('image_1')
                            ->label('Image 1')
                            ->image()
                            ->disk('s3')
                            ->directory('addons')
                            ->visibility('public')
                            ->maxSize(2048),

                        FileUpload::make('image_2')
                            ->label('Image 2')
                            ->image()
                            ->disk('s3')
                            ->directory('addons')
                            ->visibility('public')
                            ->maxSize(2048),
                    ])
                    ->columns(2),

                Section::make('Inventory')
                    ->schema([
                        Select::make('stock_status')
                            ->label('Stock Status')
                            ->options([
                                1 => 'In Stock',
                                2 => 'Out of Stock',
                                3 => 'Backorder',
                                4 => 'Discontinued',
                            ])
                            ->default(1)
                            ->required(),

                        TextInput::make('total_quantity')
                            ->label('Total Quantity')
                            ->numeric()
                            ->default(0)
                            ->required(),
                    ])
                    ->columns(2),

                Section::make('Category-Specific Fields')
                    ->schema([
                        // Lug Nuts / Lug Bolts
                        TextInput::make('thread_size')
                            ->label('Thread Size')
                            ->maxLength(255)
                            ->visible(fn (Get $get) => in_array($get('addon_category_id'), [2, 3])), // Lug Nuts, Lug Bolts

                        TextInput::make('color')
                            ->label('Color')
                            ->maxLength(255)
                            ->visible(fn (Get $get) => in_array($get('addon_category_id'), [2, 3])),

                        TextInput::make('lug_nut_length')
                            ->label('Lug Nut Length')
                            ->maxLength(255)
                            ->visible(fn (Get $get) => $get('addon_category_id') == 2),

                        TextInput::make('lug_nut_diameter')
                            ->label('Lug Nut Diameter')
                            ->maxLength(255)
                            ->visible(fn (Get $get) => $get('addon_category_id') == 2),

                        TextInput::make('thread_length')
                            ->label('Thread Length')
                            ->maxLength(255)
                            ->visible(fn (Get $get) => $get('addon_category_id') == 3), // Lug Bolts

                        TextInput::make('lug_bolt_diameter')
                            ->label('Lug Bolt Diameter')
                            ->maxLength(255)
                            ->visible(fn (Get $get) => $get('addon_category_id') == 3),

                        // Hub Rings
                        TextInput::make('ext_center_bore')
                            ->label('External Center Bore')
                            ->maxLength(255)
                            ->visible(fn (Get $get) => $get('addon_category_id') == 4), // Hub Rings

                        TextInput::make('center_bore')
                            ->label('Center Bore')
                            ->maxLength(255)
                            ->visible(fn (Get $get) => in_array($get('addon_category_id'), [4, 5])), // Hub Rings, Spacers

                        // Spacers
                        TextInput::make('bolt_pattern')
                            ->label('Bolt Pattern')
                            ->maxLength(255)
                            ->visible(fn (Get $get) => $get('addon_category_id') == 5), // Spacers

                        TextInput::make('width')
                            ->label('Width')
                            ->maxLength(255)
                            ->visible(fn (Get $get) => $get('addon_category_id') == 5),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image_1')
                    ->label('Image')
                    ->disk('s3')
                    ->circular()
                    ->defaultImageUrl(url('/images/placeholder.png')),

                Tables\Columns\TextColumn::make('full_details')
                    ->label('Product Details')
                    ->searchable(['title', 'part_number', 'description'])
                    ->html()
                    ->formatStateUsing(function (Addon $record) {
                        $html = '<div class="space-y-1">';
                        $html .= '<div class="font-semibold text-gray-900 dark:text-white">' . e($record->title) . '</div>';
                        if ($record->part_number) {
                            $html .= '<div class="text-sm text-gray-500">' . e($record->part_number) . '</div>';
                        }
                        if ($record->description) {
                            $html .= '<div class="text-xs text-gray-400">' . Str::limit(e($record->description), 100) . '</div>';
                        }
                        return $html . '</div>';
                    })
                    ->wrap(),

                Tables\Columns\TextColumn::make('wh2_california')
                    ->label('WH-2 California')
                    ->default(500)
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('wh1_chicago')
                    ->label('WH-1 Chicago')
                    ->default(0)
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('category.name')
                    ->badge()
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('price')
                    ->money('USD')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('total_quantity')
                    ->label('Qty')
                    ->alignCenter()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('stock_status')
                    ->badge()
                    ->color(fn (int $state): string => match ($state) {
                        1 => 'success',
                        2 => 'danger',
                        3 => 'warning',
                        4 => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (int $state): string => match ($state) {
                        1 => 'In Stock',
                        2 => 'Out of Stock',
                        3 => 'Backorder',
                        4 => 'Discontinued',
                        default => 'Unknown',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('addon_category_id')
                    ->label('Category')
                    ->relationship('category', 'name')
                    ->preload(),

                Tables\Filters\SelectFilter::make('stock_status')
                    ->options([
                        1 => 'In Stock',
                        2 => 'Out of Stock',
                        3 => 'Backorder',
                        4 => 'Discontinued',
                    ]),

                Tables\Filters\TernaryFilter::make('tax_inclusive')
                    ->label('Tax Inclusive'),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->url(fn (Addon $record): string => route('filament.admin.resources.addons.view', $record)),
                
                Tables\Actions\EditAction::make()
                    ->label('Edit')
                    ->icon('heroicon-o-pencil'),
                
                Tables\Actions\DeleteAction::make()
                    ->label('Delete'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('30s');
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
            'index' => Pages\ListAddons::route('/'),
            'create' => Pages\CreateAddon::route('/create'),
            'view' => Pages\ViewAddon::route('/{record}'),
            'edit' => Pages\EditAddon::route('/{record}/edit'),
        ];
    }
}
