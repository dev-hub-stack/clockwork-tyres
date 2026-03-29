<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Accounts\Enums\AccountRole;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Models\Account;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessAuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_logs_in_a_business_owner_and_returns_account_context(): void
    {
        $owner = User::query()->create([
            'name' => 'Alpha Tyres Trading',
            'email' => 'owner@alpha.test',
            'password' => 'clockwork123',
        ]);

        $account = Account::query()->create([
            'name' => 'Alpha Tyres Trading',
            'slug' => 'alpha-tyres-trading',
            'account_type' => AccountType::BOTH,
            'retail_enabled' => true,
            'wholesale_enabled' => true,
            'status' => AccountStatus::ACTIVE,
            'created_by_user_id' => $owner->id,
        ]);

        $account->users()->attach($owner->id, [
            'role' => AccountRole::OWNER->value,
            'is_default' => true,
        ]);

        $response = $this->postJson('/api/auth/business-login', [
            'email' => 'owner@alpha.test',
            'password' => 'clockwork123',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.owner.email', 'owner@alpha.test')
            ->assertJsonPath('data.account_context.current_account.slug', 'alpha-tyres-trading')
            ->assertJsonPath('data.account_context.current_account.account_type', 'both')
            ->assertJsonPath('data.account_context.available_accounts.0.slug', 'alpha-tyres-trading');

        $token = $response->json('data.access_token');

        $this->assertIsString($token);
        $this->assertNotEmpty($token);
        $this->assertNotNull(PersonalAccessToken::findToken($token));
    }

    public function test_it_rejects_invalid_business_owner_credentials(): void
    {
        User::query()->create([
            'name' => 'Alpha Tyres Trading',
            'email' => 'owner@alpha.test',
            'password' => 'clockwork123',
        ]);

        $this->postJson('/api/auth/business-login', [
            'email' => 'owner@alpha.test',
            'password' => 'wrong-password',
        ])
            ->assertStatus(401)
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', 'Invalid email or password.');
    }
}
