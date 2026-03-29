<?php

namespace Tests\Feature;

use App\Filament\Pages\ProcurementWorkbench;
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
use App\Modules\Orders\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ProcurementWorkbenchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::firstOrCreate(['name' => 'view_quotes', 'guard_name' => 'web']);
    }

    public function test_procurement_workbench_shows_grouped_supplier_sections_and_unified_submission_copy(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view_quotes');

        $currentAccount = $this->createAccount($user, [
            'name' => 'Retail Admin',
            'slug' => 'retail-admin',
            'account_type' => AccountType::BOTH,
            'retail_enabled' => true,
            'wholesale_enabled' => true,
            'base_subscription_plan' => SubscriptionPlan::PREMIUM,
            'reports_subscription_enabled' => true,
        ], true);

        $firstSupplier = Account::create([
            'name' => 'North Coast Tyres',
            'slug' => 'north-coast-tyres',
            'account_type' => AccountType::SUPPLIER,
            'retail_enabled' => false,
            'wholesale_enabled' => true,
            'status' => AccountStatus::ACTIVE,
            'base_subscription_plan' => SubscriptionPlan::BASIC,
            'reports_subscription_enabled' => false,
        ]);

        $secondSupplier = Account::create([
            'name' => 'Desert Line Trading',
            'slug' => 'desert-line-trading',
            'account_type' => AccountType::SUPPLIER,
            'retail_enabled' => false,
            'wholesale_enabled' => true,
            'status' => AccountStatus::ACTIVE,
            'base_subscription_plan' => SubscriptionPlan::BASIC,
            'reports_subscription_enabled' => false,
        ]);

        AccountConnection::create([
            'retailer_account_id' => $currentAccount->id,
            'supplier_account_id' => $firstSupplier->id,
            'status' => AccountConnectionStatus::APPROVED,
            'approved_at' => now(),
            'notes' => 'Grouped from the active retailer account.',
        ]);

        AccountConnection::create([
            'retailer_account_id' => $currentAccount->id,
            'supplier_account_id' => $secondSupplier->id,
            'status' => AccountConnectionStatus::APPROVED,
            'approved_at' => now(),
        ]);

        $customer = Customer::create([
            'customer_type' => 'retail',
            'business_name' => 'Retail Walk-in',
            'email' => 'walkin@example.test',
            'account_id' => $currentAccount->id,
            'status' => 'active',
        ]);

        Order::create([
            'document_type' => 'quote',
            'customer_id' => $customer->id,
            'quote_number' => 'QUO-5001',
            'quote_status' => 'approved',
            'order_number' => 'QUO-5001',
            'payment_status' => 'pending',
            'sub_total' => 400,
            'tax' => 0,
            'vat' => 0,
            'shipping' => 0,
            'discount' => 0,
            'total' => 400,
            'currency' => 'AED',
            'tax_inclusive' => true,
            'issue_date' => now(),
            'channel' => 'wholesale',
        ]);

        $this->actingAs($user)
            ->get('/admin/procurement-workbench')
            ->assertOk()
            ->assertSee('Retail Admin')
            ->assertSee('Grouped supplier cart')
            ->assertSee('Recent procurement signals')
            ->assertSee("George's grouped-by-supplier admin checkout rule")
            ->assertSee('North Coast Tyres')
            ->assertSee('Desert Line Trading')
            ->assertSee('QUO-5001')
            ->assertSee('Approved supplier connection ready for procurement.')
            ->assertSee('Supplier order #1')
            ->assertSee('Supplier order #2');

        $page = app(ProcurementWorkbench::class);
        $page->mount();

        $this->assertCount(2, $page->supplierGroups);
        $this->assertSame('Retail Admin', $page->currentAccountSummary['account']['name']);
        $this->assertSame(2, $page->currentAccountSummary['supplier_connections']['approved']);
        $this->assertSame("George's grouped-by-supplier admin checkout rule", $page->placeOrderCallout['title']);
        $this->assertSame('Retail Admin', $page->requestSummary[0]['value']);
        $this->assertSame(2, $page->requestSummary[1]['value']);
        $this->assertSame(1, $page->requestSummary[2]['value']);
        $this->assertSame('QUO-5001', $page->recentProcurementSignals[0]['document_number']);
    }

    public function test_procurement_workbench_surfaces_persisted_grouped_procurement_requests(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view_quotes');

        $currentAccount = $this->createAccount($user, [
            'name' => 'Retail Admin',
            'slug' => 'retail-admin',
            'account_type' => AccountType::BOTH,
            'retail_enabled' => true,
            'wholesale_enabled' => true,
            'base_subscription_plan' => SubscriptionPlan::PREMIUM,
            'reports_subscription_enabled' => true,
        ], true);

        $supplier = Account::create([
            'name' => 'North Coast Tyres',
            'slug' => 'north-coast-tyres',
            'account_type' => AccountType::SUPPLIER,
            'retail_enabled' => false,
            'wholesale_enabled' => true,
            'status' => AccountStatus::ACTIVE,
            'base_subscription_plan' => SubscriptionPlan::BASIC,
            'reports_subscription_enabled' => false,
        ]);

        AccountConnection::create([
            'retailer_account_id' => $currentAccount->id,
            'supplier_account_id' => $supplier->id,
            'status' => AccountConnectionStatus::APPROVED,
            'approved_at' => now(),
        ]);

        $customer = Customer::create([
            'customer_type' => 'retail',
            'business_name' => 'Retail Walk-in',
            'email' => 'walkin@example.test',
            'account_id' => $currentAccount->id,
            'status' => 'active',
        ]);

        $submission = app(SubmitGroupedProcurementAction::class)->execute(
            retailerAccount: $currentAccount,
            actor: $user,
            customer: $customer,
            lineItems: [
                [
                    'supplier_id' => $supplier->id,
                    'supplier_name' => 'North Coast Tyres',
                    'sku' => 'TYR-225-45-17',
                    'product_name' => 'Touring tyre',
                    'size' => '225/45R17',
                    'quantity' => 4,
                    'unit_price' => 120,
                    'source' => 'Approved supplier connection',
                ],
            ],
        );

        $requestNumber = $submission->requests->first()?->request_number;

        $this->assertNotNull($requestNumber);

        $this->actingAs($user)
            ->get('/admin/procurement-workbench')
            ->assertOk()
            ->assertSee('Retail Admin')
            ->assertSee('Live procurement requests')
            ->assertSee($requestNumber);

        $page = app(ProcurementWorkbench::class);
        $page->mount();

        $this->assertSame(1, $page->requestSummary[2]['value']);
        $this->assertSame($requestNumber, $page->recentProcurementSignals[0]['document_number']);
        $this->assertSame('Procurement Request', $page->recentProcurementSignals[0]['document_type_label']);
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
