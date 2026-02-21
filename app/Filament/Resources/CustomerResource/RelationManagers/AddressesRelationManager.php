<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Illuminate\Contracts\View\View;

class AddressesRelationManager extends RelationManager
{
    protected static bool $isLazy = true;

    public function placeholder(): View
    {
        return view('components.loading-placeholder');
    }

    protected static string $relationship = 'addresses';

    protected static ?string $title = 'Addresses';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('address_type')
                    ->label('Address Type')
                    ->options([
                        1 => 'Billing Address',
                        2 => 'Shipping Address',
                    ])
                    ->required()
                    ->default(1),
                
                TextInput::make('nickname')
                    ->label('Nickname')
                    ->maxLength(100),
                
                TextInput::make('first_name')
                    ->label('Contact First Name')
                    ->maxLength(255),
                
                TextInput::make('last_name')
                    ->label('Contact Last Name')
                    ->maxLength(255),
                
                Textarea::make('address')
                    ->label('Street Address')
                    ->rows(2)
                    ->columnSpanFull(),
                
                TextInput::make('city')
                    ->label('City')
                    ->maxLength(100),
                
                TextInput::make('state')
                    ->label('State/Province')
                    ->maxLength(100),
                
                TextInput::make('country')
                    ->label('Country')
                    ->maxLength(100),
                
                TextInput::make('zip')
                    ->label('Postal Code')
                    ->maxLength(20),
                
                TextInput::make('phone_no')
                    ->label('Contact Phone')
                    ->tel()
                    ->maxLength(50),
                
                TextInput::make('email')
                    ->label('Contact Email')
                    ->email()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\BadgeColumn::make('address_type')
                    ->label('Type')
                    ->formatStateUsing(fn ($state) => $state === 1 ? 'Billing' : 'Shipping')
                    ->colors([
                        'primary' => 1,
                        'success' => 2,
                    ]),
                
                Tables\Columns\TextColumn::make('nickname')
                    ->label('Nickname'),
                
                Tables\Columns\TextColumn::make('formatted_address')
                    ->label('Address')
                    ->limit(50),
                
                Tables\Columns\TextColumn::make('phone_no')
                    ->label('Phone'),
                
                Tables\Columns\TextColumn::make('email')
                    ->label('Email'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('address_type')
                    ->options([
                        1 => 'Billing',
                        2 => 'Shipping',
                    ]),
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
