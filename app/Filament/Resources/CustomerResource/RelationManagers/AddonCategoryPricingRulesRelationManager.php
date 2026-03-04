<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Illuminate\Contracts\View\View;

class AddonCategoryPricingRulesRelationManager extends RelationManager
{
    protected static bool $isLazy = true;

    public function placeholder(): View
    {
        return view('components.loading-placeholder');
    }

    protected static string $relationship = 'addonCategoryPricingRules';

    protected static ?string $title = 'Addon Category Pricing';

    public static function canViewForRecord(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord->customer_type === 'wholesale';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('add_on_category_id')
                    ->label('Addon Category')
                    ->relationship('addonCategory', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->placeholder('Select an addon category'),

                Select::make('discount_type')
                    ->label('Discount Type')
                    ->options([
                        'percentage' => 'Percentage',
                        'fixed'      => 'Fixed Amount',
                    ])
                    ->required()
                    ->default('percentage')
                    ->live(),

                TextInput::make('discount_percentage')
                    ->label('Discount Percentage')
                    ->numeric()
                    ->suffix('%')
                    ->minValue(0)
                    ->maxValue(100)
                    ->default(0)
                    ->visible(fn (Get $get) => $get('discount_type') === 'percentage'),

                TextInput::make('discount_value')
                    ->label('Fixed Discount Amount')
                    ->numeric()
                    ->prefix('AED')
                    ->minValue(0)
                    ->default(0)
                    ->visible(fn (Get $get) => $get('discount_type') === 'fixed'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('addonCategory.name')
                    ->label('Addon Category')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('discount_type')
                    ->label('Type')
                    ->colors([
                        'primary' => 'percentage',
                        'success' => 'fixed',
                    ]),

                Tables\Columns\TextColumn::make('discount_percentage')
                    ->label('Discount')
                    ->formatStateUsing(function ($record) {
                        if ($record->discount_type === 'percentage') {
                            return $record->discount_percentage . '%';
                        }
                        return 'AED ' . number_format($record->discount_value, 2);
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }
}
