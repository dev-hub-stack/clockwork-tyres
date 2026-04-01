<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Accounts\Enums\AccountRole;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\SubscriptionPlan;
use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Support\CurrentAccountResolver;
use App\Filament\Pages\SwitchBusinessAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SwitchBusinessAccountPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    }

    public function test_multi_account_user_can_open_switch_business_account_page(): void
    {
        [$user, $defaultAccount, $secondaryAccount] = $this->createMultiAccountUser();

        $this->actingAs($user)
            ->get('/admin/switch-business-account')
            ->assertOk()
            ->assertSee('Switch Business Account')
            ->assertSee($defaultAccount->name)
            ->assertSee($secondaryAccount->name);
    }

    public function test_switch_business_account_page_updates_selected_account_context(): void
    {
        [$user, $defaultAccount, $secondaryAccount] = $this->createMultiAccountUser();

        Livewire::actingAs($user)
            ->test(SwitchBusinessAccount::class)
            ->set('data.account_id', $secondaryAccount->id)
            ->call('save')
            ->assertHasNoErrors();

        $request = Request::create('/admin/quotes', 'GET');
        $request->setLaravelSession(app('session.store'));
        $request->setUserResolver(fn () => $user);

        $context = app(CurrentAccountResolver::class)->resolve($request, $user);

        $this->assertSame('stored', $context->selectionSource);
        $this->assertSame($secondaryAccount->id, $context->currentAccount?->id);
        $this->assertNotSame($defaultAccount->id, $context->currentAccount?->id);
    }

    public function test_super_admin_cannot_access_switch_business_account_page(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $this->actingAs($superAdmin)
            ->get('/admin/switch-business-account')
            ->assertForbidden();
    }

    /**
     * @return array{0: User, 1: Account, 2: Account}
     */
    private function createMultiAccountUser(): array
    {
        $user = User::factory()->create();

        $defaultAccount = Account::query()->create([
            'name' => 'Retail One',
            'slug' => 'retail-one',
            'account_type' => AccountType::RETAILER,
            'retail_enabled' => true,
            'wholesale_enabled' => false,
            'status' => AccountStatus::ACTIVE,
            'base_subscription_plan' => SubscriptionPlan::BASIC,
            'reports_subscription_enabled' => false,
            'created_by_user_id' => $user->id,
        ]);

        $secondaryAccount = Account::query()->create([
            'name' => 'Supply Two',
            'slug' => 'supply-two',
            'account_type' => AccountType::BOTH,
            'retail_enabled' => true,
            'wholesale_enabled' => true,
            'status' => AccountStatus::ACTIVE,
            'base_subscription_plan' => SubscriptionPlan::PREMIUM,
            'reports_subscription_enabled' => true,
            'reports_customer_limit' => 250,
            'created_by_user_id' => $user->id,
        ]);

        $user->accounts()->attach($defaultAccount->id, [
            'role' => AccountRole::OWNER->value,
            'is_default' => true,
        ]);

        $user->accounts()->attach($secondaryAccount->id, [
            'role' => AccountRole::OWNER->value,
            'is_default' => false,
        ]);

        return [$user, $defaultAccount, $secondaryAccount];
    }
}
