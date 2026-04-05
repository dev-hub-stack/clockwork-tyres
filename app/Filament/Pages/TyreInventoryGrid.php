<?php

namespace App\Filament\Pages;

use App\Filament\Support\PanelAccess;
use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Support\AccountEntitlements;
use App\Modules\Accounts\Support\CurrentAccountResolver;
use App\Modules\Inventory\Models\Warehouse;
use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

class TyreInventoryGrid extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-circle-stack';

    protected static ?string $navigationLabel = 'Tyre Inventory Grid';

    protected static ?string $slug = 'tyre-inventory-grid';

    protected static UnitEnum|string|null $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.tyre-inventory-grid';

    public array $tyres_data = [];

    public $warehouses = [];

    public bool $canEditCells = false;

    public bool $canBulkTransfer = false;

    public bool $canAddInventory = false;

    public bool $hasInventoryEntitlement = false;

    public ?int $currentAccountId = null;

    public array $currentAccountSummary = [];

    public static function shouldRegisterNavigation(): bool
    {
        return PanelAccess::canAccessOperationalSurface('view_inventory');
    }

    public static function canAccess(): bool
    {
        return PanelAccess::canAccessOperationalSurface('view_inventory');
    }

    public function mount(): void
    {
        $user = auth()->user();
        $this->canEditCells = $user?->can('edit_inventory_grid') ?? false;
        $this->canBulkTransfer = $user?->can('view_bulk_transfer') ?? false;
        $this->canAddInventory = $user?->can('view_add_inventory') ?? false;

        $currentAccount = ($user && request())
            ? app(CurrentAccountResolver::class)->resolve(request(), $user)->currentAccount
            : null;

        $this->currentAccountId = $currentAccount?->id;
        $this->hasInventoryEntitlement = $currentAccount instanceof Account
            ? AccountEntitlements::for($currentAccount)->canManageOwnProductsAndInventory()
            : false;

        $this->currentAccountSummary = $this->buildCurrentAccountSummary($currentAccount);

        $this->warehouses = $currentAccount instanceof Account
            ? Warehouse::query()
                ->where('status', 1)
                ->where('account_id', $currentAccount->id)
                ->where('is_system', false)
                ->orderBy('code')
                ->get()
            : collect();
    }

    private function buildCurrentAccountSummary(?Account $account): array
    {
        if (! $account instanceof Account) {
            return [
                'name' => 'No active account',
                'plan' => null,
                'account_type' => null,
            ];
        }

        return [
            'name' => $account->name,
            'plan' => $account->base_subscription_plan?->value,
            'account_type' => $account->account_type?->value,
        ];
    }
}
