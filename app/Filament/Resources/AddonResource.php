<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AddonResource\Pages;
use App\Models\Addon;
use App\Models\AddonCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class AddonResource extends Resource
{
    protected static ?string $model = Addon::class;

    protected static ?string $navigationIcon = 'heroicon-o-puzzle-piece';

    protected static ?string $navigationGroup = 'Products';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Add Ons';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Category Information')
                    ->schema([
                        Forms\Components\Select::make('addon_category_id')
                            ->label('Category')
                            ->relationship('category', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
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

                Forms\Components\Section::make('Product Details')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('Title')
                            ->required()
                            ->maxLength(180)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('part_number')
                            ->label('Part Number')
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),

                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Pricing')
                    ->schema([
                        Forms\Components\TextInput::make('price')
                            ->label('Retail Price')
                            ->numeric()
                            ->prefix('$')
                            ->required()
                            ->default(0),

                        Forms\Components\TextInput::make('wholesale_price')
                            ->label('Wholesale Price')
                            ->numeric()
                            ->prefix('$'),

                        Forms\Components\Toggle::make('tax_inclusive')
                            ->label('Tax Inclusive')
                            ->default(false),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Images')
                    ->schema([
                        Forms\Components\FileUpload::make('image_1')
                            ->label('Image 1')
                            ->image()
                            ->disk('s3')
                            ->directory('addons')
                            ->visibility('public')
                            ->maxSize(2048),

                        Forms\Components\FileUpload::make('image_2')
                            ->label('Image 2')
                            ->image()
                            ->disk('s3')
                            ->directory('addons')
                            ->visibility('public')
                            ->maxSize(2048),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Inventory')
                    ->schema([
                        Forms\Components\Select::make('stock_status')
                            ->label('Stock Status')
                            ->options([
                                1 => 'In Stock',
                                2 => 'Out of Stock',
                                3 => 'Backorder',
                                4 => 'Discontinued',
                            ])
                            ->default(1)
                            ->required(),

                        Forms\Components\TextInput::make('total_quantity')
                            ->label('Total Quantity')
                            ->numeric()
                            ->default(0)
                            ->required(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Category-Specific Fields')
                    ->schema([
                        // Lug Nuts / Lug Bolts
                        Forms\Components\TextInput::make('thread_size')
                            ->label('Thread Size')
                            ->maxLength(255)
                            ->visible(fn (Forms\Get $get) => in_array($get('addon_category_id'), [2, 3])), // Lug Nuts, Lug Bolts

                        Forms\Components\TextInput::make('color')
                            ->label('Color')
                            ->maxLength(255)
                            ->visible(fn (Forms\Get $get) => in_array($get('addon_category_id'), [2, 3])),

                        Forms\Components\TextInput::make('lug_nut_length')
                            ->label('Lug Nut Length')
                            ->maxLength(255)
                            ->visible(fn (Forms\Get $get) => $get('addon_category_id') == 2),

                        Forms\Components\TextInput::make('lug_nut_diameter')
                            ->label('Lug Nut Diameter')
                            ->maxLength(255)
                            ->visible(fn (Forms\Get $get) => $get('addon_category_id') == 2),

                        Forms\Components\TextInput::make('thread_length')
                            ->label('Thread Length')
                            ->maxLength(255)
                            ->visible(fn (Forms\Get $get) => $get('addon_category_id') == 3), // Lug Bolts

                        Forms\Components\TextInput::make('lug_bolt_diameter')
                            ->label('Lug Bolt Diameter')
                            ->maxLength(255)
                            ->visible(fn (Forms\Get $get) => $get('addon_category_id') == 3),

                        // Hub Rings
                        Forms\Components\TextInput::make('ext_center_bore')
                            ->label('External Center Bore')
                            ->maxLength(255)
                            ->visible(fn (Forms\Get $get) => $get('addon_category_id') == 4), // Hub Rings

                        Forms\Components\TextInput::make('center_bore')
                            ->label('Center Bore')
                            ->maxLength(255)
                            ->visible(fn (Forms\Get $get) => in_array($get('addon_category_id'), [4, 5])), // Hub Rings, Spacers

                        // Spacers
                        Forms\Components\TextInput::make('bolt_pattern')
                            ->label('Bolt Pattern')
                            ->maxLength(255)
                            ->visible(fn (Forms\Get $get) => $get('addon_category_id') == 5), // Spacers

                        Forms\Components\TextInput::make('width')
                            ->label('Width')
                            ->maxLength(255)
                            ->visible(fn (Forms\Get $get) => $get('addon_category_id') == 5),
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
