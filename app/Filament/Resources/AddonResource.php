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
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use UnitEnum;

class AddonResource extends Resource
{
    protected static ?string $model = Addon::class;

    protected static string|UnitEnum|null $navigationGroup = 'Products';

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_products') ?? false;
    }

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
                            ->afterStateUpdated(function ($state, $set, $get) {
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
                            ->maxSize(2048)
                            ->afterStateHydrated(function ($component, $state) {
                                if ($state && str_starts_with($state, 'http')) {
                                    $component->state(ltrim(parse_url($state, PHP_URL_PATH), '/'));
                                }
                            }),

                        FileUpload::make('image_2')
                            ->label('Image 2')
                            ->image()
                            ->disk('s3')
                            ->directory('addons')
                            ->maxSize(2048)
                            ->afterStateHydrated(function ($component, $state) {
                                if ($state && str_starts_with($state, 'http')) {
                                    $component->state(ltrim(parse_url($state, PHP_URL_PATH), '/'));
                                }
                            }),
                    ])
                    ->columns(2),

                Section::make('Inventory')
                    ->schema(function () {
                        $warehouses = \App\Modules\Inventory\Models\Warehouse::where('status', 1)
                            ->where('code', '!=', 'NON-STOCK')
                            ->orderBy('code')
                            ->get();

                        $fields = [
                            Toggle::make('track_inventory')
                                ->label('Track Inventory')
                                ->helperText('Enable to manage warehouse stock for this add-on. Leave off for service/non-stock items.')
                                ->default(false)
                                ->inline(false)
                                ->live()
                                ->columnSpanFull(),

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
                                ->required()
                                ->disabled()
                                ->helperText('Auto-calculated from warehouse quantities below')
                                ->visible(fn ($get) => (bool) $get('track_inventory')),
                        ];

                        foreach ($warehouses as $warehouse) {
                            $fields[] = TextInput::make('warehouse_qty_' . $warehouse->id)
                                ->label($warehouse->name)
                                ->numeric()
                                ->default(0)
                                ->minValue(0)
                                ->dehydrated(false)
                                ->visible(fn ($get) => (bool) $get('track_inventory'))
                                ->afterStateHydrated(function ($component, $state, $record) use ($warehouse) {
                                    if ($record) {
                                        $inv = $record->inventories
                                            ->where('warehouse_id', $warehouse->id)
                                            ->first();
                                        $component->state($inv ? $inv->quantity : 0);
                                    }
                                });
                        }

                        return $fields;
                    })
                    ->columns(2),


                Section::make('Category-Specific Fields')
                    ->schema(function () {
                        // Look up IDs by slug so the form works regardless of
                        // which auto-increment ID each category was assigned in the DB.
                        $cats         = AddonCategory::pluck('id', 'slug');
                        $lugNutsId    = $cats->get('lug-nuts');
                        $lugBoltsId   = $cats->get('lug-bolts');
                        $hubRingsId   = $cats->get('hub-rings');
                        $spacersId    = $cats->get('spacers');

                        $lugIds    = array_filter([$lugNutsId, $lugBoltsId]);
                        $hubIds    = array_filter([$hubRingsId]);
                        $spacerIds = array_filter([$spacersId]);

                        return [
                            // Lug Nuts / Lug Bolts / Spacers (thread_size shared)
                            TextInput::make('thread_size')
                                ->label('Thread Size')
                                ->maxLength(255)
                                ->visible(fn ($get) => in_array($get('addon_category_id'), array_merge($lugIds, $spacerIds))),

                            TextInput::make('color')
                                ->label('Color')
                                ->maxLength(255)
                                ->visible(fn ($get) => in_array($get('addon_category_id'), $lugIds)),

                            TextInput::make('lug_nut_length')
                                ->label('Lug Nut Length')
                                ->maxLength(255)
                                ->visible(fn ($get) => $get('addon_category_id') == $lugNutsId),

                            TextInput::make('lug_nut_diameter')
                                ->label('Lug Nut Diameter')
                                ->maxLength(255)
                                ->visible(fn ($get) => $get('addon_category_id') == $lugNutsId),

                            TextInput::make('thread_length')
                                ->label('Thread Length')
                                ->maxLength(255)
                                ->visible(fn ($get) => $get('addon_category_id') == $lugBoltsId),

                            TextInput::make('lug_bolt_diameter')
                                ->label('Lug Bolt Diameter')
                                ->maxLength(255)
                                ->visible(fn ($get) => $get('addon_category_id') == $lugBoltsId),

                            // Hub Rings
                            TextInput::make('ext_center_bore')
                                ->label('External Center Bore')
                                ->maxLength(255)
                                ->visible(fn ($get) => in_array($get('addon_category_id'), $hubIds)),

                            TextInput::make('center_bore')
                                ->label('Center Bore')
                                ->maxLength(255)
                                ->visible(fn ($get) => in_array($get('addon_category_id'), array_merge($hubIds, $spacerIds))),

                            // Spacers
                            TextInput::make('bolt_pattern')
                                ->label('Bolt Pattern')
                                ->maxLength(255)
                                ->visible(fn ($get) => in_array($get('addon_category_id'), $spacerIds)),

                            TextInput::make('width')
                                ->label('Width')
                                ->maxLength(255)
                                ->visible(fn ($get) => in_array($get('addon_category_id'), $spacerIds)),
                        ];
                    })
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        $warehouses = \App\Modules\Inventory\Models\Warehouse::where('status', 1)
            ->where('code', '!=', 'NON-STOCK')
            ->orderBy('code')
            ->get();

        $columns = [
            ImageColumn::make('image_1_url')
                ->label('Image')
                ->circular()
                ->defaultImageUrl(url('/images/placeholder.png')),

            TextColumn::make('full_details')
                ->label('Product Details')
                ->state(fn (Addon $record) => $record->title) // Ensure state exists
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
        ];

        foreach ($warehouses as $warehouse) {
            $columns[] = TextColumn::make('warehouse_' . $warehouse->id)
                ->label($warehouse->name)
                ->default('0')
                ->alignCenter()
                ->state(function (Addon $record) use ($warehouse) {
                    $inventory = $record->inventories->where('warehouse_id', $warehouse->id)->first();
                    return $inventory ? $inventory->quantity : 0;
                });
        }

        $columns = array_merge($columns, [
            TextColumn::make('category.name')
                ->badge()
                ->searchable()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('price')
                ->money('USD')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('total_quantity')
                ->label('Qty')
                ->alignCenter()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('stock_status')
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
        ]);

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('inventories'))
            ->columns($columns)
            ->filters([
                SelectFilter::make('addon_category_id')
                    ->label('Category')
                    ->relationship('category', 'name')
                    ->preload(),

                SelectFilter::make('stock_status')
                    ->options([
                        1 => 'In Stock',
                        2 => 'Out of Stock',
                        3 => 'Backorder',
                        4 => 'Discontinued',
                    ]),

                TernaryFilter::make('tax_inclusive')
                    ->label('Tax Inclusive'),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->url(fn (Addon $record): string => route('filament.admin.resources.addons.view', $record)),
                
                EditAction::make()
                    ->label('Edit')
                    ->icon('heroicon-o-pencil'),
                
                DeleteAction::make()
                    ->label('Delete')
                    ->visible(fn ($record) => auth()->user()?->can('delete_products') ?? false),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
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
