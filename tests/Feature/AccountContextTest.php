<?php

namespace Tests\Feature;

use App\Http\Middleware\ResolveCurrentAccount;
use App\Models\User;
use App\Modules\Accounts\Enums\AccountRole;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\SubscriptionPlan;
use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Support\CurrentAccountResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class AccountContextTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        cache()->flush();
    }

    public function test_it_resolves_the_users_default_account_context(): void
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

        $response = $this->resolveContextResponse($user, Request::create('/api/account-context', 'GET'));

        $this->assertSame(200, $response->getStatusCode());
        $responseData = $response->getData(true);

        $this->assertSame('fallback', $responseData['selection_source']);
        $this->assertSame($primaryAccount->id, $responseData['current_account']['id']);
        $this->assertSame('alpha-tyres', $responseData['current_account']['slug']);
        $this->assertCount(2, $responseData['available_accounts']);
    }

    public function test_it_persists_an_explicit_selection_for_follow_up_requests(): void
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

        $selectedResponse = $this->resolveContextResponse(
            $user,
            Request::create('/api/account-context/select', 'POST', [
                'account_slug' => 'beta-supply',
            ]),
        );

        $this->assertSame(200, $selectedResponse->getStatusCode());
        $selectedData = $selectedResponse->getData(true);

        $this->assertSame('explicit', $selectedData['selection_source']);
        $this->assertSame($selectedAccount->id, $selectedData['current_account']['id']);
        $this->assertSame('beta-supply', $selectedData['current_account']['slug']);

        $storedResponse = $this->resolveContextResponse($user, Request::create('/api/account-context', 'GET'));

        $this->assertSame(200, $storedResponse->getStatusCode());
        $storedData = $storedResponse->getData(true);

        $this->assertSame('stored', $storedData['selection_source']);
        $this->assertSame($selectedAccount->id, $storedData['current_account']['id']);
        $this->assertSame('beta-supply', $storedData['current_account']['slug']);
    }

    public function test_it_rejects_selecting_an_unrelated_account(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->createAccount($user, [
            'name' => 'Alpha Tyres',
            'slug' => 'alpha-tyres',
            'is_default' => true,
        ]);

        $foreignAccount = $this->createAccount($otherUser, [
            'name' => 'Foreign Supply',
            'slug' => 'foreign-supply',
        ]);

        $this->expectException(AuthorizationException::class);

        $this->resolveContextResponse(
            $user,
            Request::create('/api/account-context/select', 'POST', [
                'account_id' => $foreignAccount->id,
            ]),
        );
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

    private function resolveContextResponse(User $user, Request $request)
    {
        $request->setUserResolver(fn () => $user);

        $middleware = app(ResolveCurrentAccount::class);

        return $middleware->handle($request, function (Request $handledRequest) {
            $context = $handledRequest->attributes->get('currentAccountContext');

            return response()->json($context?->toArray() ?? []);
        });
    }
}
