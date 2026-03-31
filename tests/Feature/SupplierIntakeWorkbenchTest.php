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
use App\Modules\Inventory\Models\ProductInventory;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Orders\Enums\DocumentType;
use App\Modules\Procurement\Actions\SubmitGroupedProcurementAction;
use App\Modules\Procurement\Actions\ApproveProcurementRequestAction;
use App\Modules\Procurement\Enums\ProcurementWorkflowStage;
use App\Modules\Procurement\Models\ProcurementRequest;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductVariant;
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

    public function test_supplier_intake_workbench_reads_persisted_procurement_requests_when_present(): void
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

        $retailerOwner = User::factory()->create();
        $retailerAccount = $this->createAccount($retailerOwner, [
            'name' => 'Retail Alpha',
            'slug' => 'retail-alpha',
            'account_type' => AccountType::RETAILER,
            'retail_enabled' => true,
            'wholesale_enabled' => false,
        ]);

        AccountConnection::create([
            'retailer_account_id' => $retailerAccount->id,
            'supplier_account_id' => $supplierAccount->id,
            'status' => AccountConnectionStatus::APPROVED,
            'approved_at' => now(),
            'notes' => 'Primary procurement connection',
        ]);

        $customer = Customer::create([
            'business_name' => 'Alpha Fleet Services',
            'email' => 'fleet@example.test',
            'account_id' => $retailerAccount->id,
        ]);

        app(SubmitGroupedProcurementAction::class)->execute(
            retailerAccount: $retailerAccount,
            actor: $retailerOwner,
            customer: $customer,
            lineItems: [
                [
                    'supplier_id' => $supplierAccount->id,
                    'supplier_name' => 'North Coast Tyres',
                    'sku' => 'TYR-ALPHA-001',
                    'product_name' => 'Touring tyre',
                    'size' => '225/45R17',
                    'quantity' => 4,
                    'unit_price' => 125,
                    'source' => 'Approved supplier connection',
                ],
            ],
        );

        $this->actingAs($supplierUser)
            ->get('/admin/supplier-intake-workbench')
            ->assertOk()
            ->assertSee('Supplier Intake Workbench')
            ->assertSee('North Coast Tyres')
            ->assertSee('Retail Alpha')
            ->assertSee('Retail Alpha')
            ->assertSee('Quotes & Proformas inbox')
            ->assertSee('Procurement Request');

        $page = app(SupplierIntakeWorkbench::class);
        $page->mount();

        $this->assertSame('North Coast Tyres', $page->currentAccountSummary['name']);
        $this->assertSame(1, $page->currentAccountSummary['retailer_connections']);
        $this->assertSame(1, $page->currentAccountSummary['incoming_requests']);
        $this->assertSame(1, $page->signalCards[0]['value']);
        $this->assertSame('Retail Alpha', $page->incomingRequests[0]['retailer']);
        $this->assertSame('submitted', $page->incomingRequests[0]['stage']);
    }

    public function test_supplier_intake_workbench_can_approve_a_procurement_request_to_invoice(): void
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

        $retailerOwner = User::factory()->create();
        $retailerAccount = $this->createAccount($retailerOwner, [
            'name' => 'Retail Alpha',
            'slug' => 'retail-alpha',
            'account_type' => AccountType::RETAILER,
            'retail_enabled' => true,
            'wholesale_enabled' => false,
        ]);

        AccountConnection::create([
            'retailer_account_id' => $retailerAccount->id,
            'supplier_account_id' => $supplierAccount->id,
            'status' => AccountConnectionStatus::APPROVED,
            'approved_at' => now(),
            'notes' => 'Primary procurement connection',
        ]);

        $customer = Customer::create([
            'customer_type' => 'retail',
            'business_name' => 'Alpha Fleet Services',
            'email' => 'fleet@example.test',
            'account_id' => $retailerAccount->id,
            'status' => 'active',
        ]);

        $warehouse = Warehouse::create([
            'warehouse_name' => 'Main Warehouse',
            'code' => 'MAIN',
        ]);

        $product = Product::create([
            'name' => 'Touring Tyre',
            'slug' => 'touring-tyre',
            'sku' => 'TYR-225-45-17',
            'price' => 120,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'TYR-225-45-17-A',
            'price' => 120,
            'uae_retail_price' => 120,
        ]);

        ProductInventory::create([
            'product_variant_id' => $variant->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 12,
        ]);

        $submission = app(SubmitGroupedProcurementAction::class)->execute(
            retailerAccount: $retailerAccount,
            actor: $retailerOwner,
            customer: $customer,
            lineItems: [
                [
                    'supplier_id' => $supplierAccount->id,
                    'supplier_name' => 'North Coast Tyres',
                    'product_variant_id' => $variant->id,
                    'warehouse_id' => $warehouse->id,
                    'sku' => 'TYR-225-45-17-A',
                    'product_name' => 'Touring Tyre',
                    'size' => '225/45R17',
                    'quantity' => 4,
                    'unit_price' => 120,
                    'source' => 'Approved supplier connection',
                ],
            ],
        );

        $request = $submission->requests()->firstOrFail();

        $this->actingAs($supplierUser)
            ->get('/admin/supplier-intake-workbench')
            ->assertOk()
            ->assertSee('Supplier Intake Workbench');

        $page = app(SupplierIntakeWorkbench::class);
        $page->mount();
        $page->approveRequest($request->id, app(ApproveProcurementRequestAction::class));

        $request->refresh();

        $this->assertSame(ProcurementWorkflowStage::STOCK_RESERVED, $request->current_stage);
        $this->assertNotNull($request->invoice_order_id);
        $this->assertSame(DocumentType::INVOICE, $request->invoiceOrder?->document_type);
        $this->assertSame('North Coast Tyres', $page->currentAccountSummary['name']);
        $this->assertSame(1, $page->currentAccountSummary['invoices_issued']);
        $this->assertSame($request->request_number, $page->latestApprovalSummary['request_number'] ?? null);
        $this->assertSame($request->invoiceOrder?->order_number, $page->latestApprovalSummary['invoice_number'] ?? null);
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
