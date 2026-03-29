<?php

namespace App\Modules\Accounts\Support;

use App\Modules\Accounts\Enums\AccountConnectionStatus;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\SubscriptionPlan;
use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Models\AccountConnection;
use App\Modules\Accounts\Models\AccountSubscription;
use Illuminate\Support\Collection;

readonly class SuperAdminOverviewData
{
    public function buildAccountDirectoryColumns(): array
    {
        return [
            'Account',
            'Type',
            'Status',
            'Base plan',
            'Reports add-on',
            'Wholesale',
            'Retail',
            'Approved connections',
        ];
    }

    public function buildAccountRows(): array
    {
        return Account::query()
            ->with([
                'subscriptions' => function ($query): void {
                    $query->where('status', 'active')
                        ->orderByDesc('starts_at')
                        ->orderByDesc('id');
                },
            ])
            ->withCount([
                'connectionsAsRetailer as approved_retailer_connections_count' => function ($query): void {
                    $query->where('status', AccountConnectionStatus::APPROVED->value);
                },
                'connectionsAsSupplier as approved_supplier_connections_count' => function ($query): void {
                    $query->where('status', AccountConnectionStatus::APPROVED->value);
                },
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (Account $account) => $this->accountRow($account))
            ->all();
    }

    public function buildReportAddOnTiers(): array
    {
        $tiers = AccountSubscription::query()
            ->where('status', 'active')
            ->where('reports_enabled', true)
            ->selectRaw('plan_code, reports_customer_limit, COUNT(*) AS subscription_count')
            ->groupBy('plan_code', 'reports_customer_limit')
            ->orderByRaw('reports_customer_limit IS NULL, reports_customer_limit ASC')
            ->orderBy('plan_code')
            ->get();

        if ($tiers->isEmpty()) {
            return [[
                'label' => 'No active reports tiers',
                'summary' => 'Pending live data',
                'note' => 'No active reports-enabled subscriptions were found.',
            ]];
        }

        return $tiers->map(function ($tier): array {
            $plan = $tier->plan_code instanceof SubscriptionPlan
                ? $tier->plan_code
                : SubscriptionPlan::from((string) $tier->plan_code);
            $subscriptionCount = (int) $tier->subscription_count;
            $limit = $tier->reports_customer_limit === null ? null : (int) $tier->reports_customer_limit;

            return [
                'label' => $limit === null ? 'Custom tier' : number_format($limit).' customer limit',
                'summary' => sprintf(
                    '%s plan, %d active subscription%s',
                    $plan->label(),
                    $subscriptionCount,
                    $subscriptionCount === 1 ? '' : 's',
                ),
                'note' => $this->tierAccountNote($plan, $limit),
            ];
        })->values()->all();
    }

    public function buildConnectionSummary(): array
    {
        $approvedConnections = AccountConnection::query()
            ->where('status', AccountConnectionStatus::APPROVED->value)
            ->count();

        $pendingConnections = AccountConnection::query()
            ->where('status', AccountConnectionStatus::PENDING->value)
            ->count();

        return [
            [
                'label' => 'Approved connections',
                'value' => $approvedConnections,
                'note' => 'Live retailer-to-supplier relationships.',
            ],
            [
                'label' => 'Pending connections',
                'value' => $pendingConnections,
                'note' => 'Supplier links still awaiting review or approval.',
            ],
        ];
    }

    public function toArray(): array
    {
        return [
            'account_directory_columns' => $this->buildAccountDirectoryColumns(),
            'account_rows' => $this->buildAccountRows(),
            'report_add_on_tiers' => $this->buildReportAddOnTiers(),
            'connection_summary' => $this->buildConnectionSummary(),
        ];
    }

    private function accountRow(Account $account): array
    {
        return [
            'account' => $account->name,
            'type' => $this->accountTypeLabel($account),
            'status' => $this->accountStatusLabel($account),
            'base_plan' => $account->base_subscription_plan?->label() ?? 'Unknown',
            'reports_addon' => $this->reportsAddonLabel($account),
            'wholesale' => $account->wholesale_enabled ? 'Yes' : 'No',
            'retail' => $account->retail_enabled ? 'Yes' : 'No',
            'approved_connections' => (string) ($account->approved_retailer_connections_count + $account->approved_supplier_connections_count),
        ];
    }

    private function accountTypeLabel(Account $account): string
    {
        return $account->account_type?->label() ?? AccountType::RETAILER->label();
    }

    private function accountStatusLabel(Account $account): string
    {
        return $account->status?->label() ?? AccountStatus::ACTIVE->label();
    }

    private function reportsAddonLabel(Account $account): string
    {
        $latestSubscription = $this->latestReportsSubscription($account);

        if ($latestSubscription instanceof AccountSubscription) {
            return $this->formatReportsAddon($latestSubscription);
        }

        if (! $account->reports_subscription_enabled) {
            return 'Disabled';
        }

        return $account->reports_customer_limit === null
            ? 'Enabled'
            : $account->reports_customer_limit.' customers';
    }

    private function latestReportsSubscription(Account $account): ?AccountSubscription
    {
        return $account->subscriptions
            ->first(fn (AccountSubscription $subscription) => $subscription->reports_enabled);
    }

    private function formatReportsAddon(AccountSubscription $subscription): string
    {
        return $subscription->reports_customer_limit === null
            ? 'Enabled'
            : $subscription->reports_customer_limit.' customers';
    }

    private function tierAccountNote(SubscriptionPlan $plan, ?int $limit): string
    {
        $accounts = AccountSubscription::query()
            ->with('account:id,name')
            ->where('status', 'active')
            ->where('reports_enabled', true)
            ->where('plan_code', $plan->value)
            ->where(function ($query) use ($limit): void {
                if ($limit === null) {
                    $query->whereNull('reports_customer_limit');

                    return;
                }

                $query->where('reports_customer_limit', $limit);
            })
            ->get()
            ->pluck('account.name')
            ->filter()
            ->sort()
            ->values();

        return $accounts->isNotEmpty()
            ? 'Accounts: '.$accounts->implode(', ')
            : 'No linked accounts';
    }
}
