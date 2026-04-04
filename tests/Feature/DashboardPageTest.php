<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Accounts\Enums\AccountRole;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\SubscriptionPlan;
use App\Modules\Accounts\Models\Account;
use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Enums\DocumentType;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DashboardPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    }

    public function test_super_admin_sees_platform_dashboard_instead_of_business_ops_order_sheet(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $this->actingAs($superAdmin)
            ->followingRedirects()
            ->get('/admin')
            ->assertOk()
            ->assertSee('Platform Dashboard')
            ->assertSee('Business Accounts')
            ->assertSee('Products Listed')
            ->assertSee('Retail Transaction Value')
            ->assertSee('Wholesale Transaction Value')
            ->assertSee('Procurement Queue Snapshot')
            ->assertDontSee('Pending Orders - Order Sheet');
    }

    public function test_business_admin_sees_business_dashboard_instead_of_super_admin_placeholder(): void
    {
        $admin = User::factory()->create([
            'name' => 'Clockwork Admin',
            'email' => 'admin@example.com',
        ]);

        $account = Account::query()->create([
            'name' => 'Desert Drift Tyres LLC',
            'slug' => 'desert-drift-tyres',
            'account_type' => AccountType::RETAILER,
            'retail_enabled' => true,
            'wholesale_enabled' => false,
            'status' => AccountStatus::ACTIVE,
            'base_subscription_plan' => SubscriptionPlan::PREMIUM,
            'reports_subscription_enabled' => false,
            'created_by_user_id' => $admin->id,
        ]);

        $admin->accounts()->attach($account->id, [
            'role' => AccountRole::ADMIN->value,
            'is_default' => true,
        ]);

        $customer = Customer::query()->create([
            'customer_type' => 'retail',
            'business_name' => 'Desert Drift Counter Customer',
            'email' => 'customer@example.com',
            'password' => 'password',
            'account_id' => $account->id,
        ]);

        Order::query()->create([
            'document_type' => DocumentType::INVOICE,
            'customer_id' => $customer->id,
            'order_number' => 'CW-INV-1001',
            'order_status' => OrderStatus::PENDING,
            'payment_status' => PaymentStatus::PENDING,
            'sub_total' => 1250,
            'total' => 1250,
            'currency' => 'AED',
            'channel' => 'retail',
        ]);

        $this->actingAs($admin)
            ->followingRedirects()
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Business Dashboard')
            ->assertSee('Desert Drift Tyres LLC')
            ->assertSee('Retail Transactions')
            ->assertSee('Wholesale Transactions')
            ->assertSee('Recent Retail Invoices')
            ->assertSee('CW-INV-1001')
            ->assertDontSee('This dashboard is now reserved for super-admin platform monitoring.');
    }
}
