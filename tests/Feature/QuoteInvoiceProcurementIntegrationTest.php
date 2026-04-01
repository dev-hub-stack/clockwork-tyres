<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Accounts\Enums\AccountConnectionStatus;
use App\Modules\Accounts\Enums\AccountRole;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Models\AccountConnection;
use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Enums\DocumentType;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\QuoteStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Procurement\Actions\ApproveProcurementRequestAction;
use App\Modules\Procurement\Actions\SubmitGroupedProcurementAction;
use App\Modules\Procurement\Enums\ProcurementWorkflowStage;
use App\Modules\Procurement\Support\ProcurementQuoteLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class QuoteInvoiceProcurementIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::firstOrCreate(['name' => 'view_quotes', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'edit_quotes', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_invoices', 'guard_name' => 'web']);
    }

    public function test_quotes_resource_surfaces_procurement_context_and_actions(): void
    {
        [$supplierUser, $retailerOwner, $request] = $this->createProcurementRequest();

        $quote = $request->quoteOrder()->firstOrFail();
        $hiddenQuote = $this->createDirectQuote('Hidden Wholesale Quote');

        $this->actingAs($supplierUser)
            ->get('/admin/quotes')
            ->assertOk()
            ->assertSee('Quotes & Proformas')
            ->assertSee('Procurement Quotes')
            ->assertSee('Supplier Intake')
            ->assertSee((string) $quote->quote_number)
            ->assertSee((string) $request->request_number)
            ->assertSee('Retail Alpha')
            ->assertDontSee($hiddenQuote->quote_number);

        $this->actingAs($supplierUser)
            ->get('/admin/quotes/'.$quote->id)
            ->assertOk()
            ->assertSee('Open Procurement Request')
            ->assertSee('Start Supplier Review')
            ->assertSee('Approve Procurement');

        $this->actingAs($retailerOwner)
            ->get('/admin/quotes')
            ->assertOk()
            ->assertSee((string) $quote->quote_number)
            ->assertSee('Procurement Quotes');
    }

    public function test_invoices_resource_surfaces_procurement_context_after_supplier_approval(): void
    {
        [$supplierUser, $retailerOwner, $request] = $this->createProcurementRequest();

        $approvedRequest = app(ApproveProcurementRequestAction::class)->execute($request);
        $invoice = $approvedRequest->invoiceOrder()->firstOrFail();
        $hiddenInvoice = $this->createDirectInvoice('Hidden Direct Invoice');

        $this->actingAs($supplierUser)
            ->get('/admin/invoices')
            ->assertOk()
            ->assertSee('Procurement Invoices')
            ->assertSee((string) $invoice->order_number)
            ->assertSee((string) $approvedRequest->request_number)
            ->assertSee('Retail Alpha')
            ->assertDontSee($hiddenInvoice->order_number);

        $this->actingAs($supplierUser)
            ->get('/admin/invoices/'.$invoice->id)
            ->assertOk()
            ->assertSee('Open Procurement Request');

        $this->actingAs($retailerOwner)
            ->get('/admin/invoices')
            ->assertOk()
            ->assertSee((string) $invoice->order_number)
            ->assertSee('Procurement Invoices');
    }

    public function test_supplier_quote_lifecycle_syncs_procurement_stage_before_invoice_conversion(): void
    {
        [$supplierUser, , $request] = $this->createProcurementRequest();

        $quote = $request->quoteOrder()->firstOrFail();
        $lifecycle = app(ProcurementQuoteLifecycle::class);

        $this->assertSame(ProcurementWorkflowStage::SUBMITTED, $request->current_stage);

        $reviewingRequest = $lifecycle->startSupplierReview($quote);

        $this->assertSame(ProcurementWorkflowStage::SUPPLIER_REVIEW, $reviewingRequest?->current_stage);
        $this->assertNotNull($reviewingRequest?->supplier_reviewed_at);

        $quotedRequest = $lifecycle->markQuoted($quote);

        $this->assertSame(ProcurementWorkflowStage::QUOTED, $quotedRequest?->current_stage);
        $this->assertNotNull($quotedRequest?->quoted_at);

        $this->actingAs($supplierUser);

        $approvedRequest = app(ApproveProcurementRequestAction::class)->execute($quotedRequest->fresh());

        $this->assertTrue(in_array($approvedRequest->current_stage, [
            ProcurementWorkflowStage::INVOICED,
            ProcurementWorkflowStage::STOCK_RESERVED,
            ProcurementWorkflowStage::STOCK_DEDUCTED,
            ProcurementWorkflowStage::FULFILLED,
        ], true));
    }

    /**
     * @return array{0: User, 1: User, 2: \App\Modules\Procurement\Models\ProcurementRequest}
     */
    private function createProcurementRequest(): array
    {
        $supplierUser = User::factory()->create();
        $supplierUser->givePermissionTo(['view_quotes', 'edit_quotes', 'view_invoices']);

        $supplierAccount = Account::query()->create([
            'name' => 'North Coast Tyres',
            'slug' => 'north-coast-tyres',
            'account_type' => AccountType::SUPPLIER,
            'retail_enabled' => false,
            'wholesale_enabled' => true,
            'status' => AccountStatus::ACTIVE,
            'created_by_user_id' => $supplierUser->id,
        ]);

        $supplierUser->accounts()->attach($supplierAccount->id, [
            'role' => AccountRole::OWNER->value,
            'is_default' => true,
        ]);

        $retailerOwner = User::factory()->create();
        $retailerOwner->givePermissionTo(['view_quotes', 'edit_quotes', 'view_invoices']);
        $retailerAccount = Account::query()->create([
            'name' => 'Retail Alpha',
            'slug' => 'retail-alpha',
            'account_type' => AccountType::RETAILER,
            'retail_enabled' => true,
            'wholesale_enabled' => false,
            'status' => AccountStatus::ACTIVE,
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
            'business_name' => 'Retail Alpha Walk In',
            'email' => 'retail-alpha@example.test',
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

        return [$supplierUser, $retailerOwner, $submission->requests()->with(['quoteOrder', 'invoiceOrder'])->firstOrFail()];
    }

    private function createDirectQuote(string $businessName): Order
    {
        $customer = Customer::create([
            'customer_type' => 'wholesale',
            'business_name' => $businessName,
            'email' => str($businessName)->slug('-').'@example.test',
            'status' => 'active',
        ]);

        return Order::create([
            'customer_id' => $customer->id,
            'document_type' => DocumentType::QUOTE,
            'quote_number' => 'QUO-HIDDEN-001',
            'quote_status' => QuoteStatus::DRAFT,
            'issue_date' => now(),
            'valid_until' => now()->addDays(14),
            'channel' => 'wholesale',
        ]);
    }

    private function createDirectInvoice(string $businessName): Order
    {
        $customer = Customer::create([
            'customer_type' => 'retail',
            'business_name' => $businessName,
            'email' => str($businessName)->slug('-').'@example.test',
            'status' => 'active',
        ]);

        return Order::create([
            'customer_id' => $customer->id,
            'document_type' => DocumentType::INVOICE,
            'order_number' => 'INV-HIDDEN-001',
            'order_status' => OrderStatus::PROCESSING,
            'issue_date' => now(),
            'valid_until' => now()->addDays(14),
            'channel' => 'wholesale',
        ]);
    }
}
