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
use App\Modules\Orders\Enums\QuoteStatus;
use App\Modules\Procurement\Actions\SubmitGroupedProcurementAction;
use App\Modules\Procurement\Enums\ProcurementWorkflowStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class SubmitGroupedProcurementActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_persists_one_submission_and_one_request_per_supplier_group(): void
    {
        $owner = User::factory()->create();

        $retailer = $this->createAccount($owner, [
            'name' => 'Retail Hub',
            'slug' => 'retail-hub',
            'account_type' => AccountType::BOTH,
            'retail_enabled' => true,
            'wholesale_enabled' => true,
        ]);

        $northCoast = Account::create([
            'name' => 'North Coast Tyres',
            'slug' => 'north-coast-tyres',
            'account_type' => AccountType::SUPPLIER,
            'retail_enabled' => false,
            'wholesale_enabled' => true,
            'status' => AccountStatus::ACTIVE,
        ]);

        $desertLine = Account::create([
            'name' => 'Desert Line Trading',
            'slug' => 'desert-line-trading',
            'account_type' => AccountType::SUPPLIER,
            'retail_enabled' => false,
            'wholesale_enabled' => true,
            'status' => AccountStatus::ACTIVE,
        ]);

        AccountConnection::create([
            'retailer_account_id' => $retailer->id,
            'supplier_account_id' => $northCoast->id,
            'status' => AccountConnectionStatus::APPROVED,
            'approved_at' => now(),
        ]);

        AccountConnection::create([
            'retailer_account_id' => $retailer->id,
            'supplier_account_id' => $desertLine->id,
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

        $submission = app(SubmitGroupedProcurementAction::class)->execute(
            retailerAccount: $retailer,
            actor: $owner,
            customer: $customer,
            lineItems: [
                [
                    'supplier_id' => $northCoast->id,
                    'supplier_name' => 'North Coast Tyres',
                    'sku' => 'TYR-225-45-17',
                    'product_name' => 'Touring tyre',
                    'size' => '225/45R17',
                    'quantity' => 4,
                    'unit_price' => 120,
                    'source' => 'Approved supplier connection',
                ],
                [
                    'supplier_id' => $northCoast->id,
                    'supplier_name' => 'North Coast Tyres',
                    'sku' => 'TYR-205-55-16',
                    'product_name' => 'City tyre',
                    'size' => '205/55R16',
                    'quantity' => 2,
                    'unit_price' => 95,
                    'source' => 'Approved supplier connection',
                ],
                [
                    'supplier_id' => $desertLine->id,
                    'supplier_name' => 'Desert Line Trading',
                    'sku' => 'TYR-245-40-18',
                    'product_name' => 'Sport tyre',
                    'size' => '245/40R18',
                    'quantity' => 4,
                    'unit_price' => 140,
                    'source' => 'Approved supplier connection',
                ],
            ],
            meta: [
                'submitted_from' => 'procurement-workbench',
            ],
        );

        $this->assertNotNull($submission->submission_number);
        $this->assertSame(2, $submission->supplier_count);
        $this->assertSame(2, $submission->request_count);
        $this->assertSame(3, $submission->line_item_count);
        $this->assertSame(10, $submission->quantity_total);
        $this->assertSame(1230.00, (float) $submission->subtotal);

        $requests = $submission->requests->sortBy('supplier_account_id')->values();
        $northCoastConnection = AccountConnection::query()
            ->where('retailer_account_id', $retailer->id)
            ->where('supplier_account_id', $northCoast->id)
            ->firstOrFail();
        $desertLineConnection = AccountConnection::query()
            ->where('retailer_account_id', $retailer->id)
            ->where('supplier_account_id', $desertLine->id)
            ->firstOrFail();

        $this->assertCount(2, $requests);
        $this->assertSame(ProcurementWorkflowStage::SUBMITTED, $requests[0]->current_stage);
        $this->assertSame($retailer->id, $requests[0]->retailer_account_id);
        $this->assertSame($owner->id, $requests[0]->submitted_by_user_id);
        $this->assertNotSame($customer->id, $requests[0]->customer_id);
        $this->assertNotNull($requests[0]->request_number);
        $this->assertNotNull($northCoastConnection->supplier_customer_id);
        $this->assertNotNull($desertLineConnection->supplier_customer_id);
        $this->assertSame($northCoastConnection->supplier_customer_id, $requests[0]->customer_id);
        $this->assertSame('Retail Hub', $requests[0]->customer?->business_name);
        $this->assertSame($northCoast->id, $requests[0]->customer?->account_id);
        $this->assertNotNull($requests[0]->quote_order_id);
        $this->assertNotNull($requests[0]->quoteOrder);
        $this->assertSame(DocumentType::QUOTE, $requests[0]->quoteOrder?->document_type);
        $this->assertSame(QuoteStatus::SENT, $requests[0]->quoteOrder?->quote_status);
        $this->assertSame($northCoastConnection->supplier_customer_id, $requests[0]->quoteOrder?->customer_id);
        $this->assertCount(2, $requests[0]->items);
        $this->assertSame($desertLineConnection->supplier_customer_id, $requests[1]->customer_id);
        $this->assertSame($desertLine->id, $requests[1]->customer?->account_id);
        $this->assertNotNull($requests[1]->quote_order_id);
        $this->assertNotNull($requests[1]->quoteOrder);
        $this->assertSame(DocumentType::QUOTE, $requests[1]->quoteOrder?->document_type);
        $this->assertSame(QuoteStatus::SENT, $requests[1]->quoteOrder?->quote_status);
        $this->assertSame($desertLineConnection->supplier_customer_id, $requests[1]->quoteOrder?->customer_id);
        $this->assertCount(1, $requests[1]->items);
    }

    public function test_it_rejects_supplier_groups_that_are_not_approved_connections(): void
    {
        $owner = User::factory()->create();

        $retailer = $this->createAccount($owner, [
            'name' => 'Retail Hub',
            'slug' => 'retail-hub',
            'account_type' => AccountType::RETAILER,
            'retail_enabled' => true,
            'wholesale_enabled' => false,
        ]);

        $supplier = Account::create([
            'name' => 'Pending Supplier',
            'slug' => 'pending-supplier',
            'account_type' => AccountType::SUPPLIER,
            'retail_enabled' => false,
            'wholesale_enabled' => true,
            'status' => AccountStatus::ACTIVE,
        ]);

        AccountConnection::create([
            'retailer_account_id' => $retailer->id,
            'supplier_account_id' => $supplier->id,
            'status' => AccountConnectionStatus::PENDING,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('approved supplier connections');

        app(SubmitGroupedProcurementAction::class)->execute(
            retailerAccount: $retailer,
            actor: $owner,
            lineItems: [
                [
                    'supplier_id' => $supplier->id,
                    'supplier_name' => 'Pending Supplier',
                    'sku' => 'TYR-001',
                    'product_name' => 'Pending tyre',
                    'size' => '225/45R17',
                    'quantity' => 4,
                    'unit_price' => 99,
                ],
            ],
        );
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
