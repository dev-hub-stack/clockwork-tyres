<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages;
use App\Filament\Resources\CustomerResource\RelationManagers;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Enums\CustomerType;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;
use BackedEnum;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static string|UnitEnum|null $navigationGroup = 'Customers';

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_customers') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create_customers') ?? false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->can('edit_customers') ?? false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->can('delete_customers') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Customer Information')
                    ->schema([
                        Select::make('customer_type')
                            ->label('Customer Type')
                            ->options([
                                'retail'    => 'Retail',
                                'wholesale' => 'Wholesale',
                            ])
                            ->required()
                            ->default('retail'),
                        
                        TextInput::make('business_name')
                            ->label('Customer name')
                            ->maxLength(255)
                            ->required(),
                        
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        
                        TextInput::make('phone')
                            ->label('Phone')
                            ->tel()
                            ->maxLength(50),
                    ])->columns(2),

                Section::make('Address Information')
                    ->schema([
                        TextInput::make('address')
                            ->label('Address')
                            ->columnSpanFull(),
                        
                        TextInput::make('city')
                            ->label('City')
                            ->maxLength(100),
                        
                        TextInput::make('state')
                            ->label('State')
                            ->maxLength(100),
                        
                        Select::make('country_id')
                            ->label('Country')
                            ->relationship('country', 'name')
                            ->searchable()
                            ->preload(),
                    ])->columns(2),

                Section::make('Business Information')
                    ->schema([
                        TextInput::make('trn')
                            ->label('TRN (Tax Registration Number)')
                            ->maxLength(100),
                        
                        TextInput::make('license_no')
                            ->label('License Number')
                            ->maxLength(100),
                        
                        DatePicker::make('expiry')
                            ->label('License Expiry Date'),
                        
                        TextInput::make('trade_license_number')
                            ->label('Trade License Number')
                            ->maxLength(255),
                        
                        TextInput::make('website')
                            ->label('Website')
                            ->url()
                            ->maxLength(255),
                        
                        TextInput::make('instagram')
                            ->label('Instagram')
                            ->maxLength(100)
                            ->prefix('@'),

                        FileUpload::make('trade_license_path')
                            ->label('Trade License File')
                            ->disk('s3')
                            ->directory('dealers/documents')
                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                            ->maxSize(5120)
                            ->downloadable()
                            ->openable()
                            ->columnSpanFull(),

                        FileUpload::make('profile_image')
                            ->label('Business Logo')
                            ->disk('s3')
                            ->directory('dealers/images')
                            ->image()
                            ->imagePreviewHeight('80')
                            ->maxSize(2048)
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('System Information')
                    ->schema([
                        Select::make('representative_id')
                            ->label('Sales Representative')
                            ->relationship('representative', 'name')
                            ->searchable()
                            ->preload(),
                        
                        Select::make('status')
                            ->label('Status')
                            ->options([
                                'active' => 'Active',
                                'inactive' => 'Inactive',
                            ])
                            ->default('active')
                            ->required(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable(['first_name', 'last_name', 'business_name'])
                    ->sortable(),
                
                Tables\Columns\BadgeColumn::make('customer_type')
                    ->label('Type')
                    ->colors([
                        'success' => 'retail',
                        'primary' => 'wholesale',
                    ]),
                
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->copyable(),
                
                Tables\Columns\TextColumn::make('phone')
                    ->label('Phone')
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('city')
                    ->label('City')
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('representative.name')
                    ->label('Sales Rep')
                    ->sortable(),
                
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'success' => 'active',
                        'danger' => 'inactive',
                    ]),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('customer_type')
                    ->label('Type')
                    ->options([
                        'retail'    => 'Retail',
                        'wholesale' => 'Wholesale',
                    ]),
                
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
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
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\AddressesRelationManager::class,
            RelationManagers\OrderHistoryRelationManager::class,
            RelationManagers\BrandPricingRulesRelationManager::class,
            RelationManagers\ModelPricingRulesRelationManager::class,
            RelationManagers\AddonCategoryPricingRulesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
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
