<?php

namespace App\Filament\Resources;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Filament\Resources\RoleResource\Pages;
use BackedEnum;
use UnitEnum;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected const PERMISSION_GROUP_ORDER = [
        'Dashboard',
        'Reports',
        'Inventory',
        'Categories',
        'Consignments',
        'Customers',
        'Invoices',
        'Products',
        'Quotes',
        'Users',
        'Warehouses',
        'Warranty Claims',
        'Settings',
        'Notifications',
    ];

    protected static function getGroupedPermissionOptions(): array
    {
        $grouped = [];

        foreach (Permission::orderBy('name')->get() as $permission) {
            $group = static::getPermissionGroup($permission->name);

            $grouped[$group][$permission->id] = static::getPermissionOptionLabel($permission->name, $group);
        }

        $sorted = [];

        foreach (static::PERMISSION_GROUP_ORDER as $group) {
            if (! isset($grouped[$group])) {
                continue;
            }

            $sorted[$group] = $grouped[$group];
            unset($grouped[$group]);
        }

        foreach ($grouped as $group => $options) {
            $sorted[$group] = $options;
        }

        return $sorted;
    }

    public static function getPermissionGroupStatePath(string $group): string
    {
        return 'permissions_by_group.' . Str::slug($group, '_');
    }

    public static function getPermissionCheckboxLists(): array
    {
        $components = [];

        foreach (static::getGroupedPermissionOptions() as $group => $options) {
            $components[] = CheckboxList::make(static::getPermissionGroupStatePath($group))
                ->label($group)
                ->options($options)
                ->columns(count($options) >= 4 ? 2 : 1)
                ->gridDirection('row')
                ->bulkToggleable();
        }

        return $components;
    }

    public static function extractPermissionIds(array $data): array
    {
        return collect($data['permissions_by_group'] ?? [])
            ->flatten()
            ->filter()
            ->map(fn (mixed $id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public static function mapPermissionIdsToGroups(array $permissionIds): array
    {
        $groupedState = [];

        foreach (static::getGroupedPermissionOptions() as $group => $options) {
            $groupedState[Str::slug($group, '_')] = collect(array_keys($options))
                ->map(fn (int|string $id) => (int) $id)
                ->intersect($permissionIds)
                ->values()
                ->all();
        }

        return $groupedState;
    }

    protected static function getPermissionGroup(string $permissionName): string
    {
        return match ($permissionName) {
            'receive_wholesale_inquiries' => 'Notifications',
            'view_dashboard', 'view_monthly_revenue_card', 'view_pending_orders_card', 'view_pending_warranty_card', 'view_today_orders_card' => 'Dashboard',
            'export_reports', 'view_dealer_reports', 'view_expenses', 'view_profit_reports', 'view_reports', 'view_sales_reports', 'view_team_reports' => 'Reports',
            'edit_inventory_grid', 'view_add_inventory', 'view_bulk_transfer', 'view_inventory', 'view_inventory_reports' => 'Inventory',
            'edit_settings', 'view_settings' => 'Settings',
            default => static::getPermissionResourceLabel($permissionName),
        };
    }

    protected static function getPermissionOptionLabel(string $permissionName, string $group): string
    {
        return match ($permissionName) {
            'receive_wholesale_inquiries' => 'Receive wholesale signup inquiry emails',
            'view_dashboard' => 'View dashboard',
            'view_monthly_revenue_card' => 'View monthly revenue card',
            'view_pending_orders_card' => 'View pending orders card',
            'view_pending_warranty_card' => 'View pending warranty card',
            'view_today_orders_card' => 'View today orders card',
            'export_reports' => 'Export reports',
            'view_dealer_reports' => 'View dealer reports',
            'view_expenses' => 'View expenses',
            'view_profit_reports' => 'View profit reports',
            'view_reports' => 'View reports overview',
            'view_sales_reports' => 'View sales reports',
            'view_team_reports' => 'View team reports',
            'edit_inventory_grid' => 'Edit inventory grid',
            'view_add_inventory' => 'View add inventory',
            'view_bulk_transfer' => 'View bulk transfer',
            'view_inventory' => 'View inventory',
            'view_inventory_reports' => 'View inventory reports',
            'edit_settings' => 'Edit settings',
            'view_settings' => 'View settings',
            default => static::getPermissionActionLabel($permissionName, $group),
        };
    }

    protected static function getPermissionResourceLabel(string $permissionName): string
    {
        $resource = preg_replace('/^(create|edit|delete|view|export|receive)_/', '', $permissionName) ?? $permissionName;

        return Str::title(str_replace('_', ' ', $resource));
    }

    protected static function getPermissionActionLabel(string $permissionName, string $group): string
    {
        [$action] = array_pad(explode('_', $permissionName, 2), 2, null);

        $actionLabel = Str::title((string) $action);
        $resourceLabel = static::getPermissionResourceLabel($permissionName);

        if ($resourceLabel === $group) {
            return $actionLabel;
        }

        return trim("{$actionLabel} {$resourceLabel}");
    }

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-shield-check';

    protected static UnitEnum|string|null $navigationGroup = 'Administration';

    protected static ?string $navigationLabel = 'Roles & Permissions';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Role Details')->schema([
                TextInput::make('name')
                    ->label('Role Name')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(50)
                    ->helperText('Lowercase, underscores only (e.g. sales_rep)'),
            ]),

            Section::make('Permissions')
                ->description('Check the permissions this role grants to all users assigned to it. Use "Receives wholesale signup inquiry emails" for roles that should get dealer signup inquiry notifications.')
                ->columns(2)
                ->schema(static::getPermissionCheckboxLists()),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Role')
                    ->formatStateUsing(fn ($state) => ucwords(str_replace('_', ' ', $state)))
                    ->sortable()
                    ->searchable(),

                TextColumn::make('permissions_count')
                    ->label('Permissions')
                    ->counts('permissions')
                    ->badge()
                    ->color('success'),

                TextColumn::make('users_count')
                    ->label('Users')
                    ->counts('users')
                    ->badge()
                    ->color('info'),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->striped();
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit'   => Pages\EditRole::route('/{record}/edit'),
        ];
    }
}
