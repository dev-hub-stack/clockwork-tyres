<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasSupplierNetworkAccess;
use App\Modules\Accounts\Enums\AccountConnectionStatus;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Models\AccountConnection;
use App\Modules\Accounts\Support\AccountEntitlements;
use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

class ExploreSuppliers extends Page
{
    use HasSupplierNetworkAccess;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-magnifying-glass';

    protected static ?string $navigationLabel = 'Explore Suppliers';

    protected static ?string $title = 'Explore Suppliers';

    protected static UnitEnum|string|null $navigationGroup = 'Suppliers';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'explore-suppliers';

    protected string $view = 'filament.pages.explore-suppliers';

    public array $currentAccountSummary = [];
    public array $entitlementSummary = [];
    public array $supplierRows = [];

    public function mount(): void
    {
        $currentAccount = $this->resolveCurrentRetailAccount();

        abort_unless($currentAccount instanceof Account, 403);

        $entitlements = AccountEntitlements::for($currentAccount);
        $currentConnections = AccountConnection::query()
            ->where('retailer_account_id', $currentAccount->id)
            ->get()
            ->keyBy('supplier_account_id');

        $approvedCount = AccountConnection::query()
            ->where('retailer_account_id', $currentAccount->id)
            ->approved()
            ->count();

        $this->currentAccountSummary = [
            'name' => $currentAccount->name,
            'account_type' => $currentAccount->account_type?->label(),
            'base_plan' => $currentAccount->base_subscription_plan?->label(),
            'supplier_count' => $approvedCount,
        ];

        $limit = $entitlements->supplierConnectionLimit();
        $remainingSlots = $limit === null ? 'Unlimited' : max(0, $limit - $approvedCount);

        $this->entitlementSummary = [
            'supplier_limit' => $limit ?? 'Unlimited',
            'remaining_slots' => $remainingSlots,
            'reports_addon' => $entitlements->hasReportsAddon() ? 'Enabled' : 'Disabled',
            'can_add_more' => $entitlements->canAddSupplierConnection($approvedCount) ? 'Yes' : 'Upgrade required',
        ];

        $this->supplierRows = Account::query()
            ->where('status', AccountStatus::ACTIVE->value)
            ->where('wholesale_enabled', true)
            ->where('id', '!=', $currentAccount->id)
            ->orderBy('name')
            ->get()
            ->map(function (Account $supplier) use ($currentConnections): array {
                /** @var AccountConnection|null $connection */
                $connection = $currentConnections->get($supplier->id);
                $status = $connection?->status;

                return [
                    'supplier' => $supplier->name,
                    'type' => $supplier->account_type?->label(),
                    'base_plan' => $supplier->base_subscription_plan?->label(),
                    'reports_addon' => $supplier->reports_subscription_enabled ? 'Enabled' : 'Disabled',
                    'connected_retailers' => $supplier->approvedRetailerConnections()->count(),
                    'connection_status' => $status?->label() ?? 'Available',
                    'next_action' => match ($status) {
                        AccountConnectionStatus::APPROVED => 'Connected',
                        AccountConnectionStatus::PENDING => 'Request Pending',
                        AccountConnectionStatus::REJECTED => 'Review / Re-request',
                        AccountConnectionStatus::INACTIVE => 'Reconnect',
                        default => 'Add Supplier',
                    },
                ];
            })
            ->values()
            ->all();
    }
}
