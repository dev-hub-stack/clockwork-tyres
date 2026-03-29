<?php

namespace App\Modules\Accounts\Support;

use App\Modules\Accounts\Enums\AccountRole;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\SubscriptionPlan;

readonly class SuperAdminAccountCreationBlueprint
{
    public function canCreateSupplierAccounts(): bool
    {
        return true;
    }

    public function canManageSupplierAccounts(): bool
    {
        return true;
    }

    public function canImpersonateAccounts(): bool
    {
        return false;
    }

    public function usesManualApprovalQueue(): bool
    {
        return false;
    }

    /**
     * @return array<int, string>
     */
    public function accountCreationFlow(): array
    {
        return [
            'Create supplier account directly',
            'Assign wholesale capability and account type',
            'Configure the base subscription and reports add-on',
            'Publish the account without an approval queue',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function creationFields(): array
    {
        return [
            [
                'key' => 'name',
                'label' => 'Account name',
                'required' => true,
            ],
            [
                'key' => 'slug',
                'label' => 'Account slug',
                'required' => true,
            ],
            [
                'key' => 'account_type',
                'label' => 'Account type',
                'required' => true,
                'options' => AccountType::values(),
            ],
            [
                'key' => 'retail_enabled',
                'label' => 'Retail enabled',
                'required' => true,
            ],
            [
                'key' => 'wholesale_enabled',
                'label' => 'Wholesale enabled',
                'required' => true,
            ],
            [
                'key' => 'status',
                'label' => 'Status',
                'required' => true,
                'options' => AccountStatus::values(),
            ],
            [
                'key' => 'base_subscription_plan',
                'label' => 'Base subscription plan',
                'required' => true,
                'options' => array_column(SubscriptionPlan::cases(), 'value'),
            ],
            [
                'key' => 'reports_subscription_enabled',
                'label' => 'Reports add-on enabled',
                'required' => true,
            ],
            [
                'key' => 'reports_customer_limit',
                'label' => 'Reports customer limit',
                'required' => false,
            ],
            [
                'key' => 'default_membership_role',
                'label' => 'Default membership role',
                'required' => true,
                'options' => array_column(AccountRole::cases(), 'value'),
            ],
        ];
    }

    /**
     * @return array<string, bool|array<int, string>>
     */
    public function toArray(): array
    {
        return [
            'can_create_supplier_accounts' => $this->canCreateSupplierAccounts(),
            'can_manage_supplier_accounts' => $this->canManageSupplierAccounts(),
            'can_impersonate_accounts' => $this->canImpersonateAccounts(),
            'uses_manual_approval_queue' => $this->usesManualApprovalQueue(),
            'account_creation_flow' => $this->accountCreationFlow(),
            'creation_fields' => $this->creationFields(),
        ];
    }
}
