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
use App\Modules\Procurement\Actions\ApproveProcurementRequestAction;
use App\Modules\Procurement\Actions\SubmitGroupedProcurementAction;
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
        [$supplierUser, $request] = $this->createProcurementRequest();

        $quote = $request->quoteOrder()->firstOrFail();

        $this->actingAs($supplierUser)
            ->get('/admin/quotes')
            ->assertOk()
            ->assertSee('Quotes & Proformas')
            ->assertSee((string) $quote->quote_number)
            ->assertSee((string) $request->request_number)
            ->assertSee('Retail Alpha');

        $this->actingAs($supplierUser)
            ->get('/admin/quotes/'.$quote->id)
            ->assertOk()
            ->assertSee('Open Procurement Request')
            ->assertSee('Approve Procurement');
    }

    public function test_invoices_resource_surfaces_procurement_context_after_supplier_approval(): void
    {
        [$supplierUser, $request] = $this->createProcurementRequest();

        $approvedRequest = app(ApproveProcurementRequestAction::class)->execute($request);
        $invoice = $approvedRequest->invoiceOrder()->firstOrFail();

        $this->actingAs($supplierUser)
            ->get('/admin/invoices')
            ->assertOk()
            ->assertSee((string) $invoice->order_number)
            ->assertSee((string) $approvedRequest->request_number)
            ->assertSee('Retail Alpha');

        $this->actingAs($supplierUser)
            ->get('/admin/invoices/'.$invoice->id)
            ->assertOk()
            ->assertSee('Open Procurement Request');
    }

    /**
     * @return array{0: User, 1: \App\Modules\Procurement\Models\ProcurementRequest}
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

        return [$supplierUser, $submission->requests()->with(['quoteOrder', 'invoiceOrder'])->firstOrFail()];
    }
}
