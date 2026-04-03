<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasSupplierNetworkAccess;
use App\Modules\Accounts\Enums\AccountConnectionStatus;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Models\AccountConnection;
use App\Modules\Accounts\Support\AccountEntitlements;
use BackedEnum;
use Filament\Notifications\Notification;
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
    public string $search = '';
    public string $statusFilter = 'all';
    public bool $canAddSuppliers = true;

    public function mount(): void
    {
        $this->loadSupplierDirectory();
    }

    public function requestSupplier(int $supplierId): void
    {
        $currentAccount = $this->resolveCurrentRetailAccount();

        abort_unless($currentAccount instanceof Account, 403);

        $supplier = Account::query()
            ->whereKey($supplierId)
            ->where('status', AccountStatus::ACTIVE)
            ->where('wholesale_enabled', true)
            ->first();

        if (! $supplier instanceof Account) {
            Notification::make()
                ->title('Supplier not available')
                ->body('That supplier is no longer available to connect.')
                ->warning()
                ->send();

            return;
        }

        /** @var AccountConnection|null $existingConnection */
        $existingConnection = AccountConnection::withTrashed()
            ->where('retailer_account_id', $currentAccount->id)
            ->where('supplier_account_id', $supplier->id)
            ->first();

        if ($existingConnection?->status === AccountConnectionStatus::APPROVED) {
            Notification::make()
                ->title('Supplier already connected')
                ->body($supplier->name.' is already in your approved supplier network.')
                ->success()
                ->send();

            return;
        }

        if ($existingConnection?->status === AccountConnectionStatus::PENDING) {
            Notification::make()
                ->title('Request already pending')
                ->body('A supplier request for '.$supplier->name.' is already waiting for review.')
                ->warning()
                ->send();

            return;
        }

        $approvedCount = AccountConnection::query()
            ->where('retailer_account_id', $currentAccount->id)
            ->approved()
            ->count();

        if (! AccountEntitlements::for($currentAccount)->canAddSupplierConnection($approvedCount)) {
            Notification::make()
                ->title('Supplier limit reached')
                ->body('This account has reached its supplier limit. Upgrade the plan to add more suppliers.')
                ->danger()
                ->send();

            return;
        }

        $connection = $existingConnection ?? new AccountConnection([
            'retailer_account_id' => $currentAccount->id,
            'supplier_account_id' => $supplier->id,
        ]);

        if ($connection->trashed()) {
            $connection->restore();
        }

        $connection->forceFill([
            'status' => AccountConnectionStatus::PENDING->value,
            'approved_at' => null,
            'notes' => 'Requested from Explore Suppliers on '.now()->toDateTimeString(),
        ])->save();

        $this->loadSupplierDirectory();

        Notification::make()
            ->title('Supplier request sent')
            ->body('A supplier connection request for '.$supplier->name.' has been created.')
            ->success()
            ->send();
    }

    public function getFilteredSupplierRowsProperty(): array
    {
        return collect($this->supplierRows)
            ->filter(function (array $row): bool {
                $matchesSearch = $this->search === ''
                    || str_contains(strtolower($row['supplier'] ?? ''), strtolower($this->search))
                    || str_contains(strtolower($row['type'] ?? ''), strtolower($this->search))
                    || str_contains(strtolower($row['base_plan'] ?? ''), strtolower($this->search));

                $matchesStatus = $this->statusFilter === 'all'
                    || ($row['connection_status_value'] ?? 'available') === $this->statusFilter;

                return $matchesSearch && $matchesStatus;
            })
            ->values()
            ->all();
    }

    public function statusBadgeClasses(string $status): string
    {
        return match ($status) {
            AccountConnectionStatus::APPROVED->value => 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200',
            AccountConnectionStatus::PENDING->value => 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-200',
            AccountConnectionStatus::REJECTED->value => 'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-200',
            AccountConnectionStatus::INACTIVE->value => 'bg-slate-100 text-slate-700 ring-1 ring-inset ring-slate-200',
            default => 'bg-sky-50 text-sky-700 ring-1 ring-inset ring-sky-200',
        };
    }

    public function actionButtonClasses(string $action): string
    {
        return match ($action) {
            'connected' => 'inline-flex items-center rounded-xl bg-emerald-600 px-3 py-2 text-sm font-semibold text-white shadow-sm',
            'pending' => 'inline-flex items-center rounded-xl bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-700 ring-1 ring-inset ring-amber-200',
            'reconnect' => 'inline-flex items-center rounded-xl bg-slate-900 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-800',
            default => 'inline-flex items-center rounded-xl bg-primary-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-500',
        };
    }

    protected function loadSupplierDirectory(): void
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
        $this->canAddSuppliers = $entitlements->canAddSupplierConnection($approvedCount);

        $this->entitlementSummary = [
            'supplier_limit' => $limit ?? 'Unlimited',
            'remaining_slots' => $remainingSlots,
            'reports_addon' => $entitlements->hasReportsAddon() ? 'Enabled' : 'Disabled',
            'can_add_more' => $this->canAddSuppliers ? 'Yes' : 'Upgrade required',
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
                    'supplier_id' => $supplier->id,
                    'supplier' => $supplier->name,
                    'type' => $supplier->account_type?->label(),
                    'base_plan' => $supplier->base_subscription_plan?->label(),
                    'reports_addon' => $supplier->reports_subscription_enabled ? 'Enabled' : 'Disabled',
                    'connected_retailers' => $supplier->approvedRetailerConnections()->count(),
                    'connection_status' => $status?->label() ?? 'Available',
                    'connection_status_value' => $status?->value ?? 'available',
                    'next_action' => match ($status) {
                        AccountConnectionStatus::APPROVED => 'Connected',
                        AccountConnectionStatus::PENDING => 'Request Pending',
                        AccountConnectionStatus::REJECTED => 'Review / Re-request',
                        AccountConnectionStatus::INACTIVE => 'Reconnect',
                        default => 'Add Supplier',
                    },
                    'action_key' => match ($status) {
                        AccountConnectionStatus::APPROVED => 'connected',
                        AccountConnectionStatus::PENDING => 'pending',
                        AccountConnectionStatus::REJECTED,
                        AccountConnectionStatus::INACTIVE => 'reconnect',
                        default => 'connect',
                    },
                ];
            })
            ->values()
            ->all();
    }
}
