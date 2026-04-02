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
use App\Modules\Inventory\Models\ProductInventory;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Orders\Enums\DocumentType;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductVariant;
use App\Modules\Procurement\Actions\ApproveProcurementRequestAction;
use App\Modules\Procurement\Actions\SubmitGroupedProcurementAction;
use App\Modules\Procurement\Enums\ProcurementWorkflowStage;
use App\Modules\Procurement\Support\ProcurementQuoteLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApproveProcurementRequestActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_approves_a_procurement_request_and_converts_the_linked_quote_to_invoice(): void
    {
        $supplierOwner = User::factory()->create();
        $retailerOwner = User::factory()->create();

        $retailer = $this->createAccount($retailerOwner, [
            'name' => 'Retail Hub',
            'slug' => 'retail-hub',
            'account_type' => AccountType::RETAILER,
            'retail_enabled' => true,
            'wholesale_enabled' => false,
        ]);

        $supplier = $this->createAccount($supplierOwner, [
            'name' => 'North Coast Tyres',
            'slug' => 'north-coast-tyres',
            'account_type' => AccountType::SUPPLIER,
            'retail_enabled' => false,
            'wholesale_enabled' => true,
        ]);

        AccountConnection::create([
            'retailer_account_id' => $retailer->id,
            'supplier_account_id' => $supplier->id,
            'status' => AccountConnectionStatus::APPROVED,
            'approved_at' => now(),
        ]);

        $customer = Customer::create([
            'customer_type' => 'retail',
            'business_name' => 'Walk In Customer',
            'email' => 'walk-in@example.test',
            'account_id' => $retailer->id,
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
            retailerAccount: $retailer,
            actor: $retailerOwner,
            customer: $customer,
            lineItems: [
                [
                    'supplier_id' => $supplier->id,
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

        $request = $submission->requests()->with('quoteOrder.items')->firstOrFail();

        $this->assertNotNull($request->quote_order_id);
        $this->assertSame(DocumentType::QUOTE, $request->quoteOrder?->document_type);

        app(ProcurementQuoteLifecycle::class)->startSupplierReview($request->quoteOrder()->firstOrFail());
        $quotedRequest = app(ProcurementQuoteLifecycle::class)->markQuoted($request->quoteOrder()->firstOrFail());

        $approved = app(ApproveProcurementRequestAction::class)->execute($quotedRequest);
        $invoice = $approved->invoiceOrder;

        $this->assertNotNull($approved->approved_at);
        $this->assertNotNull($approved->invoiced_at);
        $this->assertNotNull($approved->supplier_reviewed_at);
        $this->assertNotNull($approved->quoted_at);
        $this->assertSame($approved->quote_order_id, $approved->invoice_order_id);
        $this->assertSame(ProcurementWorkflowStage::STOCK_RESERVED, $approved->current_stage);
        $this->assertNotNull($invoice);
        $this->assertSame(DocumentType::INVOICE, $invoice?->document_type);
        $this->assertSame(OrderStatus::PROCESSING, $invoice?->order_status);
        $this->assertSame(4, $invoice?->items->first()?->allocated_quantity);

        $inventory = ProductInventory::query()
            ->where('product_variant_id', $variant->id)
            ->where('warehouse_id', $warehouse->id)
            ->firstOrFail();

        $this->assertLessThan(12, $inventory->quantity);
    }

    private function createAccount(User $user, array $attributes): Account
    {
        $account = Account::create(array_merge([
            'status' => AccountStatus::ACTIVE,
            'created_by_user_id' => $user->id,
        ], $attributes));

        $account->users()->attach($user->id, [
            'role' => AccountRole::OWNER->value,
            'is_default' => true,
        ]);

        return $account;
    }
}
