<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Accounts\Enums\AccountConnectionStatus;
use App\Modules\Accounts\Enums\AccountRole;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\SubscriptionPlan;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Models\AccountConnection;
use App\Modules\Accounts\Models\AccountSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountsModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_accounts_module_supports_business_roles_and_connections(): void
    {
        $creator = User::factory()->create();

        $retailer = Account::create([
            'name' => 'Alpha Tyres',
            'slug' => 'alpha-tyres',
            'account_type' => AccountType::BOTH,
            'retail_enabled' => true,
            'wholesale_enabled' => true,
            'status' => AccountStatus::ACTIVE,
            'base_subscription_plan' => SubscriptionPlan::PREMIUM,
            'reports_subscription_enabled' => true,
            'reports_customer_limit' => 500,
            'created_by_user_id' => $creator->id,
        ]);

        $supplier = Account::create([
            'name' => 'Bravo Supply',
            'slug' => 'bravo-supply',
            'account_type' => AccountType::SUPPLIER,
            'retail_enabled' => false,
            'wholesale_enabled' => true,
            'status' => AccountStatus::ACTIVE,
            'base_subscription_plan' => SubscriptionPlan::BASIC,
            'reports_subscription_enabled' => false,
            'created_by_user_id' => $creator->id,
        ]);

        $retailer->users()->attach($creator->id, [
            'role' => AccountRole::OWNER->value,
            'is_default' => true,
        ]);

        $subscription = AccountSubscription::create([
            'account_id' => $retailer->id,
            'plan_code' => SubscriptionPlan::PREMIUM,
            'status' => 'active',
            'reports_enabled' => true,
            'reports_customer_limit' => 500,
            'starts_at' => now(),
            'created_by_user_id' => $creator->id,
        ]);

        $connection = AccountConnection::create([
            'retailer_account_id' => $retailer->id,
            'supplier_account_id' => $supplier->id,
            'status' => AccountConnectionStatus::APPROVED,
            'approved_at' => now(),
            'notes' => 'Approved for shared tyre stock.',
        ]);

        $this->assertSame(AccountType::BOTH, $retailer->fresh()->account_type);
        $this->assertSame(SubscriptionPlan::PREMIUM, $retailer->fresh()->base_subscription_plan);
        $this->assertTrue($retailer->fresh()->supportsRetailStorefront());
        $this->assertTrue($retailer->fresh()->supportsWholesalePortal());
        $this->assertTrue($retailer->fresh()->hasReportsSubscription());
        $this->assertSame($retailer->id, $creator->fresh()->defaultAccount()?->id);
        $this->assertCount(2, $creator->fresh()->createdAccounts);
        $this->assertTrue($retailer->fresh()->approvedSupplierConnections()->exists());
        $this->assertTrue($connection->fresh()->isApproved());
        $this->assertSame($retailer->id, $subscription->fresh()->account_id);

        $this->assertDatabaseHas('account_user', [
            'account_id' => $retailer->id,
            'user_id' => $creator->id,
            'role' => AccountRole::OWNER->value,
            'is_default' => 1,
        ]);

        $this->assertDatabaseHas('account_connections', [
            'retailer_account_id' => $retailer->id,
            'supplier_account_id' => $supplier->id,
            'status' => AccountConnectionStatus::APPROVED->value,
        ]);

        $this->assertDatabaseHas('account_subscriptions', [
            'account_id' => $retailer->id,
            'plan_code' => SubscriptionPlan::PREMIUM->value,
            'reports_enabled' => 1,
            'reports_customer_limit' => 500,
        ]);
    }
}
