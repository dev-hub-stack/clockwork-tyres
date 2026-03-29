<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Accounts\Enums\AccountRole;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\SubscriptionPlan;
use App\Modules\Accounts\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountContextTokenAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        cache()->flush();
    }

    public function test_business_owner_token_can_load_account_context(): void
    {
        $user = User::factory()->create();
        $primaryAccount = $this->createAccount($user, [
            'name' => 'Alpha Tyres',
            'slug' => 'alpha-tyres',
            'is_default' => true,
        ]);

        $this->createAccount($user, [
            'name' => 'Beta Supply',
            'slug' => 'beta-supply',
        ]);

        $token = $user->createToken('clockwork-business-app')->plainTextToken;

        $response = $this
            ->withToken($token)
            ->getJson('/api/account-context');

        $response
            ->assertOk()
            ->assertJsonPath('selection_source', 'fallback')
            ->assertJsonPath('current_account.id', $primaryAccount->id)
            ->assertJsonPath('current_account.slug', 'alpha-tyres')
            ->assertJsonCount(2, 'available_accounts');
    }

    public function test_business_owner_token_can_change_the_selected_account(): void
    {
        $user = User::factory()->create();

        $this->createAccount($user, [
            'name' => 'Alpha Tyres',
            'slug' => 'alpha-tyres',
            'is_default' => true,
        ]);

        $selectedAccount = $this->createAccount($user, [
            'name' => 'Beta Supply',
            'slug' => 'beta-supply',
        ]);

        $token = $user->createToken('clockwork-business-app')->plainTextToken;

        $selectedResponse = $this
            ->withToken($token)
            ->postJson('/api/account-context/select', [
                'account_slug' => 'beta-supply',
            ]);

        $selectedResponse
            ->assertOk()
            ->assertJsonPath('selection_source', 'explicit')
            ->assertJsonPath('current_account.id', $selectedAccount->id)
            ->assertJsonPath('current_account.slug', 'beta-supply');

        $storedResponse = $this
            ->withToken($token)
            ->getJson('/api/account-context');

        $storedResponse
            ->assertOk()
            ->assertJsonPath('selection_source', 'stored')
            ->assertJsonPath('current_account.id', $selectedAccount->id)
            ->assertJsonPath('current_account.slug', 'beta-supply');
    }

    /**
     * @param  array{name: string, slug: string, is_default?: bool}  $attributes
     */
    private function createAccount(User $user, array $attributes): Account
    {
        $account = Account::create([
            'name' => $attributes['name'],
            'slug' => $attributes['slug'],
            'account_type' => AccountType::BOTH,
            'retail_enabled' => true,
            'wholesale_enabled' => true,
            'status' => AccountStatus::ACTIVE,
            'base_subscription_plan' => SubscriptionPlan::PREMIUM,
            'reports_subscription_enabled' => true,
            'reports_customer_limit' => 250,
            'created_by_user_id' => $user->id,
        ]);

        $account->users()->attach($user->id, [
            'role' => AccountRole::OWNER->value,
            'is_default' => $attributes['is_default'] ?? false,
        ]);

        return $account;
    }
}
