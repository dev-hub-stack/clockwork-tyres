<?php

namespace Tests\Feature;

use App\Filament\Pages\SupplierIntakeWorkbench;
use App\Models\User;
use App\Modules\Accounts\Enums\AccountConnectionStatus;
use App\Modules\Accounts\Enums\AccountRole;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Models\AccountConnection;
use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SupplierIntakeWorkbenchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::firstOrCreate(['name' => 'view_quotes', 'guard_name' => 'web']);
    }

    public function test_supplier_intake_workbench_renders_account_aware_quote_and_invoice_signals(): void
    {
        $supplierUser = User::factory()->create();
        $supplierUser->givePermissionTo('view_quotes');

        $supplierAccount = $this->createAccount($supplierUser, [
            'name' => 'North Coast Tyres',
            'slug' => 'north-coast-tyres',
            'account_type' => AccountType::SUPPLIER,
            'retail_enabled' => false,
            'wholesale_enabled' => true,
        ]);

        $retailerAccount = $this->createAccount($supplierUser, [
            'name' => 'Alpha Retail',
            'slug' => 'alpha-retail',
            'account_type' => AccountType::RETAILER,
            'retail_enabled' => true,
            'wholesale_enabled' => false,
        ], attachToUser: false);

        AccountConnection::create([
            'retailer_account_id' => $retailerAccount->id,
            'supplier_account_id' => $supplierAccount->id,
            'status' => AccountConnectionStatus::APPROVED,
            'approved_at' => now(),
            'notes' => 'Primary intake connection',
        ]);

        $customer = Customer::create([
            'business_name' => 'Alpha Fleet Services',
            'email' => 'fleet@example.test',
            'account_id' => $retailerAccount->id,
        ]);

        $quote = Order::create([
            'document_type' => 'quote',
            'customer_id' => $customer->id,
            'quote_number' => 'QUO-1001',
            'quote_status' => 'sent',
            'issue_date' => now()->subDay(),
            'valid_until' => now()->addDays(14),
            'channel' => 'wholesale',
        ]);

        OrderItem::create([
            'order_id' => $quote->id,
            'sku' => 'TYR-ALPHA-001',
            'product_name' => 'Touring tyre',
            'quantity' => 4,
            'unit_price' => 125,
            'line_total' => 500,
            'item_attributes' => [
                'size' => '225/45R17',
            ],
        ]);

        $invoice = Order::create([
            'document_type' => 'invoice',
            'customer_id' => $customer->id,
            'order_number' => 'INV-2001',
            'order_status' => OrderStatus::PROCESSING,
            'issue_date' => now(),
            'channel' => 'wholesale',
        ]);

        OrderItem::create([
            'order_id' => $invoice->id,
            'sku' => 'TYR-ALPHA-001',
            'product_name' => 'Touring tyre',
            'quantity' => 4,
            'unit_price' => 125,
            'line_total' => 500,
            'item_attributes' => [
                'size' => '225/45R17',
            ],
        ]);

        $this->actingAs($supplierUser)
            ->get('/admin/supplier-intake-workbench')
            ->assertOk()
            ->assertSee('Supplier Intake Workbench')
            ->assertSee('North Coast Tyres')
            ->assertSee('Alpha Retail')
            ->assertSee('Alpha Fleet Services')
            ->assertSee('QUO-1001')
            ->assertSee('INV-2001')
            ->assertSee('Quotes & Proformas inbox')
            ->assertSee('Quote approval converts to invoice');

        $page = app(SupplierIntakeWorkbench::class);
        $page->mount();

        $this->assertSame('North Coast Tyres', $page->currentAccountSummary['name']);
        $this->assertSame(1, $page->currentAccountSummary['retailer_connections']);
        $this->assertSame(1, $page->currentAccountSummary['open_quotes']);
        $this->assertSame(0, $page->currentAccountSummary['approved_quotes']);
        $this->assertSame(2, $page->currentAccountSummary['incoming_requests']);

        $requestNumbers = array_column($page->incomingRequests, 'request_number');

        $this->assertContains('QUO-1001', $requestNumbers);
        $this->assertContains('INV-2001', $requestNumbers);
        $this->assertSame(4, $page->incomingRequests[0]['quantity']);
        $this->assertSame('Quotes & Proformas inbox', $page->signalCards[0]['label']);
        $this->assertSame(1, $page->signalCards[0]['value']);
        $this->assertCount(9, $page->statusRail);
    }

    /**
     * @param  array{name: string, slug: string, account_type: AccountType, retail_enabled: bool, wholesale_enabled: bool}  $attributes
     */
    private function createAccount(User $user, array $attributes, bool $attachToUser = true): Account
    {
        $account = Account::create([
            'name' => $attributes['name'],
            'slug' => $attributes['slug'],
            'account_type' => $attributes['account_type'],
            'retail_enabled' => $attributes['retail_enabled'],
            'wholesale_enabled' => $attributes['wholesale_enabled'],
            'status' => AccountStatus::ACTIVE,
            'created_by_user_id' => $user->id,
        ]);

        if ($attachToUser) {
            $account->users()->attach($user->id, [
                'role' => AccountRole::OWNER->value,
                'is_default' => true,
            ]);
        }

        return $account;
    }
}
