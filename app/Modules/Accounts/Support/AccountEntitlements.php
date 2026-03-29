<?php

namespace App\Modules\Accounts\Support;

use App\Modules\Accounts\Enums\SubscriptionPlan;
use App\Modules\Accounts\Models\Account;

readonly class AccountEntitlements
{
    public function __construct(
        public Account $account,
    ) {
    }

    public static function for(Account $account): self
    {
        return new self($account);
    }

    public function hasWholesaleAccess(): bool
    {
        return $this->account->supportsWholesalePortal();
    }

    public function canManageOwnProductsAndInventory(): bool
    {
        return $this->hasWholesaleAccess();
    }

    public function hasReportsAddon(): bool
    {
        return (bool) $this->account->reports_subscription_enabled;
    }

    public function canAccessReports(): bool
    {
        return $this->hasReportsAddon();
    }

    public function reportsCustomerLimit(): ?int
    {
        $limit = (int) ($this->account->reports_customer_limit ?? 0);

        return $limit > 0 ? $limit : null;
    }

    public function supplierConnectionLimit(): ?int
    {
        if (! $this->account->isRetailEnabled()) {
            return null;
        }

        return $this->account->base_subscription_plan === SubscriptionPlan::BASIC
            ? 3
            : null;
    }

    public function canAddSupplierConnection(int $currentSupplierCount): bool
    {
        $limit = $this->supplierConnectionLimit();

        return $limit === null || $currentSupplierCount < $limit;
    }

    public function toArray(): array
    {
        return [
            'has_wholesale_access' => $this->hasWholesaleAccess(),
            'can_manage_own_products_and_inventory' => $this->canManageOwnProductsAndInventory(),
            'has_reports_addon' => $this->hasReportsAddon(),
            'can_access_reports' => $this->canAccessReports(),
            'reports_customer_limit' => $this->reportsCustomerLimit(),
            'supplier_connection_limit' => $this->supplierConnectionLimit(),
            'base_subscription_plan' => $this->account->base_subscription_plan?->value,
        ];
    }
}
