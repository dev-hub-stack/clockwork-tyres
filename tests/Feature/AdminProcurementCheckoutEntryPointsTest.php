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
use App\Modules\Procurement\Support\ProcurementQuoteLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminProcurementCheckoutEntryPointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['view_quotes', 'edit_quotes', 'view_invoices'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    }

    public function test_retailer_procurement_queue_exposes_checkout_like_request_actions(): void
    {
        [$supplierUser, $retailerOwner, $request] = $this->createProcurementFixture();

        $quote = $request->quoteOrder()->firstOrFail();
        app(ProcurementQuoteLifecycle::class)->startSupplierReview($quote);
        app(ProcurementQuoteLifecycle::class)->markQuoted($quote);

        $this->actingAs($retailerOwner)
            ->get('/admin/procurement-requests')
            ->assertOk()
            ->assertSee('Procurement Requests')
            ->assertSee('Open Queue')
            ->assertSee('Invoiced Flow')
            ->assertSee((string) $request->request_number)
            ->assertSee('Retail Alpha')
            ->assertSee('North Coast Tyres');

        $this->actingAs($retailerOwner)
            ->get('/admin/procurement-requests/'.$request->id)
            ->assertOk()
            ->assertSee((string) $request->request_number)
            ->assertSee('Request Items')
            ->assertSee('Open Quote');

        $approvedRequest = app(ApproveProcurementRequestAction::class)->execute($request->fresh());
        $invoice = $approvedRequest->invoiceOrder()->firstOrFail();

        $this->actingAs($retailerOwner)
            ->get('/admin/procurement-requests')
            ->assertOk()
            ->assertSee('Open Invoice')
            ->assertDontSee('Approve to Invoice');

        $this->actingAs($retailerOwner)
            ->get('/admin/procurement-requests/'.$approvedRequest->id)
            ->assertOk()
            ->assertSee((string) $invoice->order_number)
            ->assertSee('Open Invoice')
            ->assertSee('Open Quote');

        $this->actingAs($supplierUser)
            ->get('/admin/procurement-requests')
            ->assertOk()
            ->assertSee('Procurement Requests');
    }

    public function test_supplier_intake_quote_review_exposes_the_checkout_review_actions(): void
    {
        [$supplierUser, , $request] = $this->createProcurementFixture();

        $quote = $request->quoteOrder()->firstOrFail();

        $this->actingAs($supplierUser)
            ->get('/admin/quotes?activeTab=supplier_intake')
            ->assertOk()
            ->assertSee('Supplier Intake')
            ->assertSee((string) $quote->quote_number)
            ->assertSee('Procurement Quotes');

        $this->actingAs($supplierUser)
            ->get('/admin/quotes/'.$quote->id)
            ->assertOk()
            ->assertSee('Open Procurement Request')
            ->assertSee('Start Supplier Review')
            ->assertDontSee('Approve Procurement');

        app(ProcurementQuoteLifecycle::class)->startSupplierReview($quote);
        app(ProcurementQuoteLifecycle::class)->markQuoted($quote);

        $this->actingAs($supplierUser)
            ->get('/admin/quotes/'.$quote->id)
            ->assertOk()
            ->assertSee('Open Procurement Request')
            ->assertSee('Approve Procurement')
            ->assertSee('Request Revision')
            ->assertSee("Reject / Can't Supply");
    }

    /**
     * @return array{0: User, 1: User, 2: \App\Modules\Procurement\Models\ProcurementRequest}
     */
    private function createProcurementFixture(): array
    {
        $supplierUser = User::factory()->create();
        $supplierUser->givePermissionTo(['view_quotes', 'edit_quotes', 'view_invoices']);

        $supplierAccount = Account::query()->create([
            'name' => 'North Coast Tyres',
            'slug' => 'north-coast-tyres-admin-checkout',
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
            'slug' => 'retail-alpha-admin-checkout',
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
            'email' => 'retail-alpha-checkout@example.test',
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
                'sku' => 'TYR-ALPHA-ADMIN-001',
                'product_name' => 'Touring tyre',
                'size' => '225/45R17',
                'quantity' => 4,
                'unit_price' => 125,
                'source' => 'Approved supplier connection',
            ]],
        );

        return [
            $supplierUser,
            $retailerOwner,
            $submission->requests()->with(['quoteOrder', 'invoiceOrder'])->firstOrFail(),
        ];
    }
}
