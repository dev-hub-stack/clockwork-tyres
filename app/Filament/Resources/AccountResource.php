<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AccountResource\Pages;
use App\Filament\Support\PanelAccess;
use App\Modules\Accounts\Enums\AccountConnectionStatus;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\SubscriptionPlan;
use App\Modules\Accounts\Models\Account;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class AccountResource extends Resource
{
    protected static ?string $model = Account::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    protected static ?string $navigationLabel = 'Business Accounts';

    protected static ?string $modelLabel = 'Business Account';

    protected static ?string $pluralModelLabel = 'Business Accounts';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return PanelAccess::canAccessGovernanceSurface();
    }

    public static function canViewAny(): bool
    {
        return PanelAccess::canAccessGovernanceSurface();
    }

    public static function canCreate(): bool
    {
        return PanelAccess::canAccessGovernanceSurface();
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return PanelAccess::canAccessGovernanceSurface();
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Account Details')
                ->schema([
                    TextInput::make('name')
                        ->label('Account name')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('slug')
                        ->maxLength(255)
                        ->helperText('Leave blank to auto-generate from the account name.'),

                    Select::make('account_type')
                        ->label('Account type')
                        ->options(collect(AccountType::cases())
                            ->mapWithKeys(fn (AccountType $type): array => [$type->value => $type->label()])
                            ->all())
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state, callable $set, callable $get): void {
                            $availablePlans = static::planOptionLabels((string) $state);

                            if (! array_key_exists((string) $get('base_subscription_plan'), $availablePlans)) {
                                $set('base_subscription_plan', array_key_first($availablePlans));
                            }
                        })
                        ->default(AccountType::RETAILER->value),

                    Select::make('status')
                        ->options(collect(AccountStatus::cases())
                            ->mapWithKeys(fn (AccountStatus $status): array => [$status->value => $status->label()])
                            ->all())
                        ->required()
                        ->default(AccountStatus::ACTIVE->value),
                ])
                ->columns(2),

            Section::make('Capabilities')
                ->schema([
                    Toggle::make('retail_enabled')
                        ->label('Retail enabled')
                        ->default(true),

                    Toggle::make('wholesale_enabled')
                        ->label('Wholesale enabled')
                        ->default(false),
                ])
                ->columns(2),

            Section::make('Subscription')
                ->schema([
                    Select::make('base_subscription_plan')
                        ->label('Base plan')
                        ->options(fn ($get): array => static::planOptionLabels((string) ($get('account_type') ?? AccountType::RETAILER->value)))
                        ->required()
                        ->default(SubscriptionPlan::BASIC->value)
                        ->helperText(fn ($get): string => static::planHelperText((string) ($get('account_type') ?? AccountType::RETAILER->value))),

                    Toggle::make('reports_subscription_enabled')
                        ->label('Reports add-on enabled')
                        ->default(false),

                    TextInput::make('reports_customer_limit')
                        ->label('Reports customer limit')
                        ->numeric()
                        ->minValue(1)
                        ->helperText('Required only when the reports add-on is enabled.'),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Account')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Account $record): string => $record->slug),

                BadgeColumn::make('account_type')
                    ->label('Type')
                    ->formatStateUsing(fn (?AccountType $state): string => $state?->label() ?? 'Retailer')
                    ->colors([
                        'success' => AccountType::RETAILER->value,
                        'warning' => AccountType::SUPPLIER->value,
                        'primary' => AccountType::BOTH->value,
                    ]),

                BadgeColumn::make('status')
                    ->formatStateUsing(fn (?AccountStatus $state): string => $state?->label() ?? 'Active')
                    ->colors([
                        'success' => AccountStatus::ACTIVE->value,
                        'gray' => AccountStatus::INACTIVE->value,
                        'danger' => AccountStatus::SUSPENDED->value,
                    ]),

                BadgeColumn::make('base_subscription_plan')
                    ->label('Plan')
                    ->formatStateUsing(fn (?SubscriptionPlan $state, Account $record): string => static::displayPlanLabel($record))
                    ->description(fn (Account $record): string => static::planPriceSummary($record))
                    ->colors([
                        'gray' => SubscriptionPlan::BASIC->value,
                        'success' => SubscriptionPlan::PREMIUM->value,
                    ]),

                TextColumn::make('reports_summary')
                    ->label('Reports add-on')
                    ->state(function (Account $record): string {
                        if (! $record->reports_subscription_enabled) {
                            return 'Disabled';
                        }

                        return $record->reports_customer_limit
                            ? $record->reports_customer_limit.' customers'
                            : 'Enabled';
                    }),

                IconColumn::make('retail_enabled')
                    ->label('Retail')
                    ->boolean(),

                IconColumn::make('wholesale_enabled')
                    ->label('Wholesale')
                    ->boolean(),

                TextColumn::make('approved_connections')
                    ->label('Approved links')
                    ->state(fn (Account $record): int => (int) $record->approved_retailer_connections_count + (int) $record->approved_supplier_connections_count)
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('approved_retailer_connections_count', $direction)
                            ->orderBy('approved_supplier_connections_count', $direction);
                    }),

                TextColumn::make('customers_count')
                    ->label('Customers')
                    ->sortable(),

                TextColumn::make('createdBy.name')
                    ->label('Created by')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('account_type')
                    ->options(collect(AccountType::cases())
                        ->mapWithKeys(fn (AccountType $type): array => [$type->value => $type->label()])
                        ->all()),

                SelectFilter::make('status')
                    ->options(collect(AccountStatus::cases())
                        ->mapWithKeys(fn (AccountStatus $status): array => [$status->value => $status->label()])
                        ->all()),

                SelectFilter::make('base_subscription_plan')
                    ->label('Plan')
                    ->options(collect(SubscriptionPlan::cases())
                        ->mapWithKeys(fn (SubscriptionPlan $plan): array => [$plan->value => $plan->label()])
                        ->all()),

                TernaryFilter::make('reports_subscription_enabled')
                    ->label('Reports add-on')
                    ->placeholder('All accounts')
                    ->trueLabel('Reports enabled')
                    ->falseLabel('Reports disabled'),

                TernaryFilter::make('retail_enabled')
                    ->label('Retail enabled')
                    ->placeholder('All accounts'),

                TernaryFilter::make('wholesale_enabled')
                    ->label('Wholesale enabled')
                    ->placeholder('All accounts'),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->striped();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAccounts::route('/'),
            'create' => Pages\CreateAccount::route('/create'),
            'edit' => Pages\EditAccount::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('createdBy:id,name')
            ->withCount([
                'customers',
                'connectionsAsRetailer as approved_retailer_connections_count' => function (Builder $query): void {
                    $query->where('status', AccountConnectionStatus::APPROVED->value);
                },
                'connectionsAsSupplier as approved_supplier_connections_count' => function (Builder $query): void {
                    $query->where('status', AccountConnectionStatus::APPROVED->value);
                },
            ]);
    }

    /**
     * @return array<string, string>
     */
    protected static function planOptionLabels(?string $accountType): array
    {
        return match ($accountType) {
            AccountType::SUPPLIER->value => [
                SubscriptionPlan::BASIC->value => 'Starter (Free)',
                SubscriptionPlan::PREMIUM->value => 'Premium (199 AED / Month)',
            ],
            AccountType::BOTH->value => [
                SubscriptionPlan::PREMIUM->value => 'Premium (199 AED / Month)',
            ],
            default => [
                SubscriptionPlan::BASIC->value => 'Starter (Free)',
                SubscriptionPlan::PREMIUM->value => 'Plus (199 AED / Month)',
            ],
        };
    }

    protected static function planHelperText(?string $accountType): string
    {
        return match ($accountType) {
            AccountType::SUPPLIER->value => 'Wholesaler Starter stays free. Wholesaler Premium is AED 199/month. Enterprise/custom pricing is configured manually from super admin after account creation.',
            AccountType::BOTH->value => 'Combined retailer + wholesaler accounts must use the paid AED 199/month plan or a manual enterprise/custom setup from super admin.',
            default => 'Retailer Starter stays free. Retailer Plus is AED 199/month. Enterprise/custom pricing is configured manually from super admin after account creation.',
        };
    }

    protected static function displayPlanLabel(Account $record): string
    {
        if ($record->base_subscription_plan === SubscriptionPlan::BASIC) {
            return 'Starter';
        }

        return match ($record->account_type) {
            AccountType::RETAILER => 'Plus',
            default => 'Premium',
        };
    }

    protected static function planPriceSummary(Account $record): string
    {
        if ($record->base_subscription_plan === SubscriptionPlan::BASIC) {
            return 'Free';
        }

        return 'AED 199 / Month';
    }
}
