<?php

namespace Tests\Unit;

use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\SubscriptionPlan;
use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Models\AccountSubscription;
use App\Modules\Accounts\Support\AccountEntitlements;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccountEntitlementsTest extends TestCase
{
    #[Test]
    public function it_exposes_the_basic_retail_limits(): void
    {
        $account = $this->account([
            'account_type' => AccountType::RETAILER,
            'retail_enabled' => true,
            'wholesale_enabled' => false,
            'base_subscription_plan' => SubscriptionPlan::BASIC,
            'reports_subscription_enabled' => false,
            'reports_customer_limit' => null,
        ]);

        $entitlements = AccountEntitlements::for($account);

        $this->assertFalse($entitlements->hasWholesaleAccess());
        $this->assertFalse($entitlements->canManageOwnProductsAndInventory());
        $this->assertFalse($entitlements->canAccessReports());
        $this->assertSame(3, $entitlements->supplierConnectionLimit());
        $this->assertFalse($entitlements->canAddSupplierConnection(3));
        $this->assertTrue($entitlements->canAddSupplierConnection(2));
    }

    #[Test]
    public function it_exposes_wholesale_permissions_without_reports(): void
    {
        $account = $this->account([
            'account_type' => AccountType::SUPPLIER,
            'retail_enabled' => false,
            'wholesale_enabled' => true,
            'base_subscription_plan' => SubscriptionPlan::BASIC,
            'reports_subscription_enabled' => false,
            'reports_customer_limit' => null,
        ]);

        $entitlements = AccountEntitlements::for($account);

        $this->assertTrue($entitlements->hasWholesaleAccess());
        $this->assertTrue($entitlements->canManageOwnProductsAndInventory());
        $this->assertFalse($entitlements->canAccessReports());
        $this->assertNull($entitlements->supplierConnectionLimit());
    }

    #[Test]
    public function it_supports_reports_addon_and_unlimited_supplier_connections_for_premium_accounts(): void
    {
        $account = $this->account([
            'account_type' => AccountType::BOTH,
            'retail_enabled' => true,
            'wholesale_enabled' => true,
            'base_subscription_plan' => SubscriptionPlan::PREMIUM,
            'reports_subscription_enabled' => true,
            'reports_customer_limit' => 500,
        ]);

        $subscription = new AccountSubscription();
        $subscription->forceFill([
            'plan_code' => SubscriptionPlan::PREMIUM,
            'status' => 'active',
            'reports_enabled' => true,
            'reports_customer_limit' => 500,
        ]);

        $account->setRelation('currentSubscription', $subscription);

        $entitlements = AccountEntitlements::for($account);

        $this->assertTrue($entitlements->hasWholesaleAccess());
        $this->assertTrue($entitlements->canManageOwnProductsAndInventory());
        $this->assertTrue($entitlements->canAccessReports());
        $this->assertSame(500, $entitlements->reportsCustomerLimit());
        $this->assertNull($entitlements->supplierConnectionLimit());
        $this->assertTrue($entitlements->canAddSupplierConnection(10));
    }

    #[Test]
    public function it_allows_premium_retailers_to_manage_their_own_products_and_inventory(): void
    {
        $account = $this->account([
            'account_type' => AccountType::RETAILER,
            'retail_enabled' => true,
            'wholesale_enabled' => false,
            'base_subscription_plan' => SubscriptionPlan::PREMIUM,
            'reports_subscription_enabled' => false,
            'reports_customer_limit' => null,
        ]);

        $subscription = new AccountSubscription();
        $subscription->forceFill([
            'plan_code' => SubscriptionPlan::PREMIUM,
            'status' => 'active',
            'reports_enabled' => false,
            'reports_customer_limit' => null,
        ]);

        $account->setRelation('currentSubscription', $subscription);

        $entitlements = AccountEntitlements::for($account);

        $this->assertFalse($entitlements->hasWholesaleAccess());
        $this->assertTrue($entitlements->canManageOwnProductsAndInventory());
        $this->assertNull($entitlements->supplierConnectionLimit());
    }

    #[Test]
    public function it_treats_trialing_paid_accounts_as_fully_enabled(): void
    {
        $account = $this->account([
            'account_type' => AccountType::RETAILER,
            'retail_enabled' => true,
            'wholesale_enabled' => false,
            'base_subscription_plan' => SubscriptionPlan::PREMIUM,
            'reports_subscription_enabled' => false,
            'reports_customer_limit' => null,
        ]);

        $subscription = new AccountSubscription();
        $subscription->forceFill([
            'plan_code' => SubscriptionPlan::PREMIUM,
            'status' => 'trialing',
            'reports_enabled' => false,
            'reports_customer_limit' => null,
        ]);

        $account->setRelation('currentSubscription', $subscription);

        $entitlements = AccountEntitlements::for($account);

        $this->assertTrue($entitlements->hasActivePaidPlan());
        $this->assertTrue($entitlements->canManageOwnProductsAndInventory());
        $this->assertNull($entitlements->supplierConnectionLimit());
        $this->assertSame('trialing', $entitlements->billingStatus());
    }

    private function account(array $attributes): Account
    {
        $account = new Account();
        $account->forceFill(array_merge([
            'name' => 'Entitlement Test Account',
            'slug' => 'entitlement-test-account',
        ], $attributes));

        return $account;
    }
}
