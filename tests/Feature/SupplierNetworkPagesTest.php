<?php

namespace Tests\Feature;

use App\Filament\Pages\ExploreSuppliers;
use App\Filament\Pages\MySuppliers;
use App\Models\User;
use App\Modules\Accounts\Enums\AccountConnectionStatus;
use App\Modules\Accounts\Enums\AccountRole;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\SubscriptionPlan;
use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Models\AccountConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SupplierNetworkPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::firstOrCreate(['name' => 'view_customers', 'guard_name' => 'web']);
    }

    public function test_explore_suppliers_requires_customer_visibility_and_renders_supplier_discovery(): void
    {
        $retailerUser = User::factory()->create();
        $retailerUser->givePermissionTo('view_customers');

        $currentAccount = $this->createAccount($retailerUser, [
            'name' => 'Retail Admin',
            'slug' => 'retail-admin',
            'account_type' => AccountType::RETAILER,
            'retail_enabled' => true,
            'wholesale_enabled' => false,
            'base_subscription_plan' => SubscriptionPlan::BASIC,
        ], true);

        $approvedSupplier = Account::create([
            'name' => 'Approved Supplier',
            'slug' => 'approved-supplier',
            'account_type' => AccountType::SUPPLIER,
            'retail_enabled' => false,
            'wholesale_enabled' => true,
            'status' => AccountStatus::ACTIVE,
            'base_subscription_plan' => SubscriptionPlan::BASIC,
            'reports_subscription_enabled' => false,
        ]);

        $pendingSupplier = Account::create([
            'name' => 'Pending Supplier',
            'slug' => 'pending-supplier',
            'account_type' => AccountType::SUPPLIER,
            'retail_enabled' => false,
            'wholesale_enabled' => true,
            'status' => AccountStatus::ACTIVE,
            'base_subscription_plan' => SubscriptionPlan::PREMIUM,
            'reports_subscription_enabled' => true,
        ]);

        AccountConnection::create([
            'retailer_account_id' => $currentAccount->id,
            'supplier_account_id' => $approvedSupplier->id,
            'status' => AccountConnectionStatus::APPROVED,
            'approved_at' => now(),
        ]);

        AccountConnection::create([
            'retailer_account_id' => $currentAccount->id,
            'supplier_account_id' => $pendingSupplier->id,
            'status' => AccountConnectionStatus::PENDING,
        ]);

        $otherUser = User::factory()->create();

        $this->actingAs($otherUser)
            ->get('/admin/explore-suppliers')
            ->assertForbidden();

        $this->actingAs($retailerUser)
            ->get('/admin/explore-suppliers')
            ->assertOk()
            ->assertSee('Explore Suppliers')
            ->assertSee('Approved Supplier')
            ->assertSee('Pending Supplier')
            ->assertSee('Connected')
            ->assertSee('Request Pending');

        $page = app(ExploreSuppliers::class);
        $page->mount();

        $this->assertTrue(ExploreSuppliers::canAccess());
        $this->assertSame('Retail Admin', $page->currentAccountSummary['name']);
        $this->assertSame(3, $page->entitlementSummary['supplier_limit']);
        $this->assertCount(2, $page->supplierRows);
    }

    public function test_my_suppliers_renders_connected_relationships_for_retail_accounts(): void
    {
        $retailerUser = User::factory()->create();
        $retailerUser->givePermissionTo('view_customers');

        $currentAccount = $this->createAccount($retailerUser, [
            'name' => 'Mixed Retailer',
            'slug' => 'mixed-retailer',
            'account_type' => AccountType::BOTH,
            'retail_enabled' => true,
            'wholesale_enabled' => true,
            'base_subscription_plan' => SubscriptionPlan::PREMIUM,
            'reports_subscription_enabled' => true,
            'reports_customer_limit' => 500,
        ], true);

        $supplier = Account::create([
            'name' => 'Network Supplier',
            'slug' => 'network-supplier',
            'account_type' => AccountType::SUPPLIER,
            'retail_enabled' => false,
            'wholesale_enabled' => true,
            'status' => AccountStatus::ACTIVE,
            'base_subscription_plan' => SubscriptionPlan::PREMIUM,
            'reports_subscription_enabled' => true,
            'reports_customer_limit' => 250,
        ]);

        AccountConnection::create([
            'retailer_account_id' => $currentAccount->id,
            'supplier_account_id' => $supplier->id,
            'status' => AccountConnectionStatus::APPROVED,
            'approved_at' => now(),
            'notes' => 'Connected from supplier discovery flow.',
        ]);

        $this->actingAs($retailerUser)
            ->get('/admin/my-suppliers')
            ->assertOk()
            ->assertSee('My Suppliers')
            ->assertSee('Network Supplier')
            ->assertSee('Connected from supplier discovery flow.')
            ->assertSee('Unlimited');

        $page = app(MySuppliers::class);
        $page->mount();

        $this->assertTrue(MySuppliers::canAccess());
        $this->assertSame('Mixed Retailer', $page->currentAccountSummary['name']);
        $this->assertSame(1, $page->connectionSummary['approved_suppliers']);
        $this->assertCount(1, $page->supplierRows);
    }

    private function createAccount(User $user, array $attributes, bool $isDefault = false): Account
    {
        $account = Account::create(array_merge([
            'status' => AccountStatus::ACTIVE,
            'reports_subscription_enabled' => false,
            'reports_customer_limit' => null,
            'created_by_user_id' => $user->id,
        ], $attributes));

        $account->users()->attach($user->id, [
            'role' => AccountRole::OWNER->value,
            'is_default' => $isDefault,
        ]);

        return $account;
    }
}
