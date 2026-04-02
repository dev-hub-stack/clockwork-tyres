<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\SubscriptionPlan;
use App\Modules\Accounts\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AccountResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    }

    public function test_accounts_resource_is_gated_to_super_admin_users(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $otherUser = User::factory()->create();

        $account = Account::query()->create([
            'name' => 'Tyre Hub',
            'slug' => 'tyre-hub',
            'account_type' => AccountType::SUPPLIER,
            'retail_enabled' => false,
            'wholesale_enabled' => true,
            'status' => AccountStatus::ACTIVE,
            'base_subscription_plan' => SubscriptionPlan::PREMIUM,
            'reports_subscription_enabled' => true,
            'reports_customer_limit' => 250,
            'created_by_user_id' => $superAdmin->id,
        ]);

        $this->actingAs($otherUser)
            ->get('/admin/accounts')
            ->assertForbidden();

        $this->actingAs($superAdmin)
            ->get('/admin/accounts')
            ->assertOk()
            ->assertSee('Business Accounts')
            ->assertSee('Tyre Hub')
            ->assertSee('Premium');

        $this->actingAs($superAdmin)
            ->get('/admin/accounts/create')
            ->assertOk()
            ->assertSee('Account Details')
            ->assertSee('Subscription');

        $this->actingAs($superAdmin)
            ->get('/admin/accounts/'.$account->slug.'/edit')
            ->assertOk()
            ->assertSee('Tyre Hub');
    }
}
