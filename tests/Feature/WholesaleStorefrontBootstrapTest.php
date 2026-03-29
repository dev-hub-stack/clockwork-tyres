<?php

namespace Tests\Feature;

use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\SubscriptionPlan;
use App\Modules\Accounts\Models\Account;
use App\Modules\Customers\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WholesaleStorefrontBootstrapTest extends TestCase
{
    use RefreshDatabase;

    public function test_storefront_bootstrap_exposes_launch_categories_and_default_retail_capabilities(): void
    {
        $response = $this->getJson('/api/storefront/bootstrap');

        $response
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.storefront_mode', 'retail-store')
            ->assertJsonPath('data.storefront.cart_enabled', true)
            ->assertJsonPath('data.storefront.checkout_enabled', true)
            ->assertJsonPath('data.categories.0.id', 'tyres')
            ->assertJsonPath('data.categories.1.id', 'wheels')
            ->assertJsonPath('data.categories.1.enabled', false);
    }

    public function test_storefront_bootstrap_includes_account_context_for_authenticated_wholesale_customer(): void
    {
        $account = Account::create([
            'name' => 'Alpha Tyres',
            'slug' => 'alpha-tyres',
            'account_type' => AccountType::BOTH,
            'retail_enabled' => true,
            'wholesale_enabled' => true,
            'status' => AccountStatus::ACTIVE,
            'base_subscription_plan' => SubscriptionPlan::PREMIUM,
            'reports_subscription_enabled' => true,
            'reports_customer_limit' => 500,
        ]);

        $dealer = Customer::create([
            'customer_type' => 'wholesale',
            'business_name' => 'Alpha Tyres Dealer',
            'email' => 'bootstrap-test@example.com',
            'password' => 'password',
            'phone' => '0500000999',
            'status' => 1,
            'account_id' => $account->id,
        ]);

        $token = $dealer->createToken('storefront-bootstrap')->plainTextToken;

        $response = $this
            ->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/storefront/bootstrap?mode=supplier-preview');

        $response
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.storefront_mode', 'supplier-preview')
            ->assertJsonPath('data.account.slug', 'alpha-tyres')
            ->assertJsonPath('data.account.account_type', 'both')
            ->assertJsonPath('data.account.supports_retail_storefront', true)
            ->assertJsonPath('data.account.supports_wholesale_portal', true)
            ->assertJsonPath('data.account.has_reports_subscription', true)
            ->assertJsonPath('data.storefront.cart_enabled', false)
            ->assertJsonPath('data.storefront.checkout_enabled', false);
    }
}
