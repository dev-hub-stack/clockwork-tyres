<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasSupplierNetworkAccess;
use App\Modules\Accounts\Enums\AccountConnectionStatus;
use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Models\AccountConnection;
use App\Modules\Accounts\Support\AccountEntitlements;
use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

class MySuppliers extends Page
{
    use HasSupplierNetworkAccess;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationLabel = 'My Suppliers';

    protected static ?string $title = 'My Suppliers';

    protected static UnitEnum|string|null $navigationGroup = 'Suppliers';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'my-suppliers';

    protected string $view = 'filament.pages.my-suppliers';

    public array $currentAccountSummary = [];
    public array $connectionSummary = [];
    public array $supplierRows = [];
    public string $search = '';
    public string $statusFilter = 'all';

    public function mount(): void
    {
        $this->loadSupplierNetwork();
    }

    public function getFilteredSupplierRowsProperty(): array
    {
        return collect($this->supplierRows)
            ->filter(function (array $row): bool {
                $matchesSearch = $this->search === ''
                    || str_contains(strtolower($row['supplier'] ?? ''), strtolower($this->search))
                    || str_contains(strtolower($row['type'] ?? ''), strtolower($this->search))
                    || str_contains(strtolower($row['status'] ?? ''), strtolower($this->search));

                $matchesStatus = $this->statusFilter === 'all'
                    || ($row['status_value'] ?? '') === $this->statusFilter;

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

    protected function loadSupplierNetwork(): void
    {
        $currentAccount = $this->resolveCurrentRetailAccount();

        abort_unless($currentAccount instanceof Account, 403);

        $entitlements = AccountEntitlements::for($currentAccount);
        $connections = AccountConnection::query()
            ->with('supplierAccount')
            ->where('retailer_account_id', $currentAccount->id)
            ->latest()
            ->get();

        $approvedCount = $connections->filter(fn (AccountConnection $connection) => $connection->isApproved())->count();
        $limit = $entitlements->supplierConnectionLimit();

        $this->currentAccountSummary = [
            'name' => $currentAccount->name,
            'account_type' => $currentAccount->account_type?->label(),
            'base_plan' => $currentAccount->base_subscription_plan?->label(),
        ];

        $this->connectionSummary = [
            'approved_suppliers' => $approvedCount,
            'pending_requests' => $connections->where('status', AccountConnectionStatus::PENDING)->count(),
            'supplier_limit' => $limit ?? 'Unlimited',
            'remaining_slots' => $limit === null ? 'Unlimited' : max(0, $limit - $approvedCount),
        ];

        $this->supplierRows = $connections
            ->map(function (AccountConnection $connection): array {
                $supplier = $connection->supplierAccount;

                return [
                    'supplier_id' => $supplier?->id,
                    'supplier' => $supplier?->name ?? 'Unknown supplier',
                    'type' => $supplier?->account_type?->label() ?? 'Unknown',
                    'status' => $connection->status?->label() ?? 'Unknown',
                    'status_value' => $connection->status?->value ?? 'unknown',
                    'approved_at' => $connection->approved_at?->format('d M Y') ?? '-',
                    'reports_addon' => $supplier?->reports_subscription_enabled ? 'Enabled' : 'Disabled',
                    'warehouse_note' => 'Ship-to location will come from retailer warehouse selection during procurement.',
                    'note' => $connection->notes ?: 'Supplier relationship placeholder for admin-side procurement.',
                ];
            })
            ->values()
            ->all();
    }
}
