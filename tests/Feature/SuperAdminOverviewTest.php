<?php

namespace Tests\Feature;

use App\Filament\Pages\SuperAdminOverview;
use App\Models\User;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Models\AccountConnection;
use App\Modules\Accounts\Models\AccountSubscription;
use BackedEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SuperAdminOverviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    }

    public function test_super_admin_overview_is_gated_to_super_admin_users_and_loads_governance_metrics(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $otherUser = User::factory()->create();

        $this->actingAs($otherUser)
            ->get('/admin/super-admin-overview')
            ->assertForbidden();

        $this->actingAs($superAdmin)
            ->get('/admin/super-admin-overview')
            ->assertOk()
            ->assertSee('Super Admin Overview')
            ->assertSee('Read-only surface')
            ->assertSee('Create and manage accounts')
            ->assertSee('No impersonation')
            ->assertSee('No impersonation, no approval queue, no product editing');

        $activeRetailerId = $this->createAccount([
            'name' => 'Retail One',
            'slug' => 'retail-one',
            'account_type' => AccountType::RETAILER,
            'retail_enabled' => true,
            'wholesale_enabled' => false,
            'status' => 'active',
            'base_subscription_plan' => 'basic',
            'reports_subscription_enabled' => false,
            'reports_customer_limit' => null,
        ]);

        $activeSupplierId = $this->createAccount([
            'name' => 'Supplier One',
            'slug' => 'supplier-one',
            'account_type' => AccountType::SUPPLIER,
            'retail_enabled' => false,
            'wholesale_enabled' => true,
            'status' => 'active',
            'base_subscription_plan' => 'premium',
            'reports_subscription_enabled' => true,
            'reports_customer_limit' => 250,
        ]);

        $bothAccountId = $this->createAccount([
            'name' => 'Both Modes',
            'slug' => 'both-modes',
            'account_type' => AccountType::BOTH,
            'retail_enabled' => true,
            'wholesale_enabled' => true,
            'status' => 'active',
            'base_subscription_plan' => 'premium',
            'reports_subscription_enabled' => true,
            'reports_customer_limit' => 500,
        ]);

        AccountSubscription::create([
            'account_id' => $activeRetailerId,
            'plan_code' => 'basic',
            'status' => 'active',
            'reports_enabled' => false,
            'reports_customer_limit' => null,
            'starts_at' => now(),
        ]);

        AccountSubscription::create([
            'account_id' => $activeSupplierId,
            'plan_code' => 'premium',
            'status' => 'active',
            'reports_enabled' => true,
            'reports_customer_limit' => 250,
            'starts_at' => now(),
        ]);

        AccountSubscription::create([
            'account_id' => $bothAccountId,
            'plan_code' => 'premium',
            'status' => 'active',
            'reports_enabled' => true,
            'reports_customer_limit' => 500,
            'starts_at' => now(),
        ]);

        AccountConnection::create([
            'retailer_account_id' => $activeRetailerId,
            'supplier_account_id' => $activeSupplierId,
            'status' => 'approved',
            'approved_at' => now(),
            'notes' => 'Approved for launch',
        ]);

        AccountConnection::create([
            'retailer_account_id' => $bothAccountId,
            'supplier_account_id' => $activeSupplierId,
            'status' => 'pending',
            'notes' => 'Pending review',
        ]);

        $page = app(SuperAdminOverview::class);
        $page->mount();

        $this->assertTrue(SuperAdminOverview::canAccess());
        $this->assertCount(4, $page->governanceCards);
        $this->assertSame(3, $page->governanceCards[0]['value']);
        $this->assertCount(3, $page->accountGovernanceCards);
        $this->assertSame('Create supplier account', $page->accountGovernanceCards[0]['label']);
        $this->assertSame('Direct control', $page->accountGovernanceCards[0]['value']);
        $this->assertCount(10, $page->accountCreationFields);
        $this->assertSame('Account name', $page->accountCreationFields[0]['label']);
        $this->assertSame(2, $page->accountBreakdown[0]['value']);
        $this->assertSame(3, $page->subscriptionBreakdown[0]['value']);
        $this->assertSame(1, $page->connectionSummary[0]['value']);
        $this->assertSame(1, $page->connectionSummary[1]['value']);
        $this->assertCount(3, $page->accountRows);
        $this->assertSame(['Both Modes', 'Retail One', 'Supplier One'], array_column($page->accountRows, 'account'));
        $this->assertSame('Retailer & Supplier', $page->accountRows[0]['type']);
        $this->assertSame('Active', $page->accountRows[0]['status']);
        $this->assertSame('Premium', $page->accountRows[0]['base_plan']);
        $this->assertSame('500 customers', $page->accountRows[0]['reports_addon']);
        $this->assertSame('Yes', $page->accountRows[0]['wholesale']);
        $this->assertSame('Yes', $page->accountRows[0]['retail']);
        $this->assertSame('0', $page->accountRows[0]['approved_connections']);
        $this->assertSame('Disabled', $page->accountRows[1]['reports_addon']);
        $this->assertSame('250 customers', $page->accountRows[2]['reports_addon']);
        $this->assertSame('1', $page->accountRows[1]['approved_connections']);
        $this->assertSame('1', $page->accountRows[2]['approved_connections']);
        $this->assertCount(2, $page->reportAddOnTiers);
        $this->assertSame('250 customer limit', $page->reportAddOnTiers[0]['label']);
        $this->assertSame('Premium plan, 1 active subscription', $page->reportAddOnTiers[0]['summary']);
        $this->assertStringContainsString('Supplier One', $page->reportAddOnTiers[0]['note']);
        $this->assertSame('500 customer limit', $page->reportAddOnTiers[1]['label']);
        $this->assertSame('Premium plan, 1 active subscription', $page->reportAddOnTiers[1]['summary']);
        $this->assertStringContainsString('Both Modes', $page->reportAddOnTiers[1]['note']);
        $this->assertContains('Create supplier account directly', $page->accountGovernanceActions);
        $this->assertContains('No impersonation', array_column($page->guardrailCards, 'label'));
    }

    protected function createAccount(array $attributes): int
    {
        $normalized = collect($attributes)->map(function (mixed $value) {
            return $value instanceof BackedEnum ? $value->value : $value;
        })->all();

        return (int) DB::table('accounts')->insertGetId(array_merge($attributes, [
            ...$normalized,
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }
}
