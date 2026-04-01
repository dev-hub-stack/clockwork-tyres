<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\SubscriptionPlan;
use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Support\AccountGovernanceManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountGovernanceManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_account_and_active_subscription_from_governance_payload(): void
    {
        $actor = User::factory()->create();

        $account = app(AccountGovernanceManager::class)->create([
            'name' => 'Tyre Hub',
            'slug' => '',
            'account_type' => AccountType::SUPPLIER->value,
            'retail_enabled' => false,
            'wholesale_enabled' => false,
            'status' => AccountStatus::ACTIVE->value,
            'base_subscription_plan' => SubscriptionPlan::PREMIUM->value,
            'reports_subscription_enabled' => true,
            'reports_customer_limit' => 250,
        ], $actor->id, 'accounts_resource_test');

        $this->assertSame('tyre-hub', $account->slug);
        $this->assertSame(AccountType::SUPPLIER, $account->account_type);
        $this->assertFalse($account->retail_enabled);
        $this->assertTrue($account->wholesale_enabled);
        $this->assertSame(SubscriptionPlan::PREMIUM, $account->base_subscription_plan);
        $this->assertTrue($account->reports_subscription_enabled);
        $this->assertSame(250, $account->reports_customer_limit);

        $subscription = $account->subscriptions()->where('status', 'active')->first();

        $this->assertNotNull($subscription);
        $this->assertSame(SubscriptionPlan::PREMIUM, $subscription->plan_code);
        $this->assertTrue($subscription->reports_enabled);
        $this->assertSame(250, $subscription->reports_customer_limit);
        $this->assertSame('accounts_resource_test', $subscription->meta['source']);
    }

    public function test_it_updates_account_capabilities_and_syncs_the_active_subscription(): void
    {
        $actor = User::factory()->create();

        $account = Account::query()->create([
            'name' => 'Road Retail',
            'slug' => 'road-retail',
            'account_type' => AccountType::RETAILER,
            'retail_enabled' => true,
            'wholesale_enabled' => false,
            'status' => AccountStatus::ACTIVE,
            'base_subscription_plan' => SubscriptionPlan::BASIC,
            'reports_subscription_enabled' => false,
            'reports_customer_limit' => null,
            'created_by_user_id' => $actor->id,
        ]);

        $account->subscriptions()->create([
            'plan_code' => SubscriptionPlan::BASIC,
            'status' => 'active',
            'reports_enabled' => false,
            'reports_customer_limit' => null,
            'starts_at' => now(),
            'meta' => ['source' => 'seed'],
            'created_by_user_id' => $actor->id,
        ]);

        $updated = app(AccountGovernanceManager::class)->update($account, [
            'name' => 'Road Retail Pro',
            'slug' => 'road-retail-pro',
            'account_type' => AccountType::BOTH->value,
            'retail_enabled' => true,
            'wholesale_enabled' => false,
            'status' => AccountStatus::SUSPENDED->value,
            'base_subscription_plan' => SubscriptionPlan::PREMIUM->value,
            'reports_subscription_enabled' => true,
            'reports_customer_limit' => 500,
        ], $actor->id, 'accounts_resource_test');

        $this->assertSame('Road Retail Pro', $updated->name);
        $this->assertSame('road-retail-pro', $updated->slug);
        $this->assertSame(AccountType::BOTH, $updated->account_type);
        $this->assertTrue($updated->retail_enabled);
        $this->assertTrue($updated->wholesale_enabled);
        $this->assertSame(AccountStatus::SUSPENDED, $updated->status);
        $this->assertSame(SubscriptionPlan::PREMIUM, $updated->base_subscription_plan);
        $this->assertTrue($updated->reports_subscription_enabled);
        $this->assertSame(500, $updated->reports_customer_limit);

        $subscription = $updated->subscriptions()->where('status', 'active')->first();

        $this->assertNotNull($subscription);
        $this->assertSame(SubscriptionPlan::PREMIUM, $subscription->plan_code);
        $this->assertTrue($subscription->reports_enabled);
        $this->assertSame(500, $subscription->reports_customer_limit);
        $this->assertSame('accounts_resource_test', $subscription->meta['source']);
    }
}
