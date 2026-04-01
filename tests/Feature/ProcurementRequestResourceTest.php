<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Accounts\Enums\AccountConnectionStatus;
use App\Modules\Accounts\Enums\AccountRole;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\SubscriptionPlan;
use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Models\AccountConnection;
use App\Modules\Customers\Models\Customer;
use App\Modules\Procurement\Actions\SubmitGroupedProcurementAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ProcurementRequestResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::firstOrCreate(['name' => 'view_quotes', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    }

    public function test_procurement_requests_resource_is_visible_to_scoped_quote_users(): void
    {
        $supplierUser = User::factory()->create();
        $supplierUser->givePermissionTo('view_quotes');

        $supplierAccount = Account::query()->create([
            'name' => 'North Coast Tyres',
            'slug' => 'north-coast-tyres',
            'account_type' => AccountType::SUPPLIER,
            'retail_enabled' => false,
            'wholesale_enabled' => true,
            'status' => AccountStatus::ACTIVE,
            'base_subscription_plan' => SubscriptionPlan::BASIC,
            'reports_subscription_enabled' => false,
            'created_by_user_id' => $supplierUser->id,
        ]);

        $supplierUser->accounts()->attach($supplierAccount->id, [
            'role' => AccountRole::OWNER->value,
            'is_default' => true,
        ]);

        $retailerOwner = User::factory()->create();
        $retailerAccount = Account::query()->create([
            'name' => 'Retail Alpha',
            'slug' => 'retail-alpha',
            'account_type' => AccountType::RETAILER,
            'retail_enabled' => true,
            'wholesale_enabled' => false,
            'status' => AccountStatus::ACTIVE,
            'base_subscription_plan' => SubscriptionPlan::PREMIUM,
            'reports_subscription_enabled' => false,
            'created_by_user_id' => $retailerOwner->id,
        ]);

        $retailerOwner->accounts()->attach($retailerAccount->id, [
            'role' => AccountRole::OWNER->value,
            'is_default' => true,
        ]);

        AccountConnection::create([
            'retailer_account_id' => $retailerAccount->id,
            'supplier_account_id' => $supplierAccount->id,
            'status' => AccountConnectionStatus::APPROVED,
            'approved_at' => now(),
        ]);

        $customer = Customer::create([
            'customer_type' => 'retail',
            'business_name' => 'Alpha Fleet Services',
            'email' => 'fleet@example.test',
            'account_id' => $retailerAccount->id,
            'status' => 'active',
        ]);

        $submission = app(SubmitGroupedProcurementAction::class)->execute(
            retailerAccount: $retailerAccount,
            actor: $retailerOwner,
            customer: $customer,
            lineItems: [[
                'supplier_id' => $supplierAccount->id,
                'supplier_name' => 'North Coast Tyres',
                'sku' => 'TYR-ALPHA-001',
                'product_name' => 'Touring tyre',
                'size' => '225/45R17',
                'quantity' => 4,
                'unit_price' => 125,
                'source' => 'Approved supplier connection',
            ]],
        );

        $request = $submission->requests()->firstOrFail();

        $this->actingAs($supplierUser)
            ->get('/admin/procurement-requests')
            ->assertOk()
            ->assertSee('Procurement Requests')
            ->assertSee((string) $request->request_number)
            ->assertSee('Retail Alpha')
            ->assertSee('North Coast Tyres');

        $this->actingAs($supplierUser)
            ->get('/admin/procurement-requests/'.$request->id)
            ->assertOk()
            ->assertSee((string) $request->request_number)
            ->assertSee('Request Items')
            ->assertSee('Touring tyre');
    }

    public function test_procurement_requests_resource_is_visible_to_super_admin(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $this->actingAs($superAdmin)
            ->get('/admin/procurement-requests')
            ->assertOk()
            ->assertSee('Procurement Requests');
    }
}
