<?php

namespace Tests\Unit\Procurement;

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
use App\Modules\Procurement\Enums\ProcurementWorkflowStage;
use App\Modules\Procurement\Models\ProcurementRequest;
use App\Modules\Procurement\Models\ProcurementSubmission;
use App\Modules\Procurement\Support\SupplierIntakeWorkbenchSignals;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SupplierIntakeWorkbenchSignalsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_builds_account_aware_intake_signals_from_connected_quotes_and_invoices(): void
    {
        $user = User::factory()->create();

        $supplierAccount = $this->createAccount($user, [
            'name' => 'North Coast Tyres',
            'slug' => 'north-coast-tyres',
            'account_type' => AccountType::SUPPLIER,
            'retail_enabled' => false,
            'wholesale_enabled' => true,
        ]);

        $retailerAccount = $this->createAccount($user, [
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
            'notes' => 'Approved supplier connection',
        ]);

        $customer = Customer::create([
            'business_name' => 'Alpha Fleet Services',
            'email' => 'fleet@example.test',
            'account_id' => $retailerAccount->id,
        ]);

        $sentQuote = Order::create([
            'document_type' => 'quote',
            'customer_id' => $customer->id,
            'quote_number' => 'QUO-3001',
            'quote_status' => 'sent',
            'issue_date' => now()->subDays(2),
            'channel' => 'wholesale',
        ]);

        OrderItem::create([
            'order_id' => $sentQuote->id,
            'sku' => 'TYR-3001',
            'product_name' => 'Grand touring tyre',
            'quantity' => 2,
            'unit_price' => 140,
            'line_total' => 280,
            'item_attributes' => [
                'size' => '235/50R18',
            ],
        ]);

        $approvedQuote = Order::create([
            'document_type' => 'quote',
            'customer_id' => $customer->id,
            'quote_number' => 'QUO-3002',
            'quote_status' => 'approved',
            'issue_date' => now()->subDay(),
            'channel' => 'wholesale',
        ]);

        OrderItem::create([
            'order_id' => $approvedQuote->id,
            'sku' => 'TYR-3002',
            'product_name' => 'SUV all-season tyre',
            'quantity' => 4,
            'unit_price' => 160,
            'line_total' => 640,
            'item_attributes' => [
                'size' => '255/55R19',
            ],
        ]);

        $invoice = Order::create([
            'document_type' => 'invoice',
            'customer_id' => $customer->id,
            'order_number' => 'INV-3003',
            'order_status' => OrderStatus::PROCESSING,
            'issue_date' => now(),
            'channel' => 'wholesale',
        ]);

        OrderItem::create([
            'order_id' => $invoice->id,
            'sku' => 'TYR-3002',
            'product_name' => 'SUV all-season tyre',
            'quantity' => 4,
            'unit_price' => 160,
            'line_total' => 640,
            'item_attributes' => [
                'size' => '255/55R19',
            ],
        ]);

        $snapshot = SupplierIntakeWorkbenchSignals::forAccount($supplierAccount);

        $this->assertSame('North Coast Tyres', $snapshot['current_account_summary']['name']);
        $this->assertSame('Supplier', $snapshot['current_account_summary']['type']);
        $this->assertSame(1, $snapshot['current_account_summary']['retailer_connections']);
        $this->assertSame(1, $snapshot['current_account_summary']['open_quotes']);
        $this->assertSame(1, $snapshot['current_account_summary']['approved_quotes']);
        $this->assertSame(1, $snapshot['current_account_summary']['invoices_issued']);
        $this->assertSame(3, $snapshot['current_account_summary']['incoming_requests']);

        $signalCards = collect($snapshot['signal_cards'])->keyBy('label');
        $this->assertSame(2, $signalCards['Quotes & Proformas inbox']['value']);
        $this->assertSame(1, $signalCards['Approved quotes']['value']);
        $this->assertSame(1, $signalCards['Invoices issued']['value']);
        $this->assertSame(1, $signalCards['Retailer connections']['value']);

        $statusRail = collect($snapshot['status_rail'])->keyBy('key');
        $this->assertSame(1, $statusRail['submitted']['count']);
        $this->assertSame(1, $statusRail['approved']['count']);
        $this->assertSame(1, $statusRail['invoiced']['count']);
        $this->assertSame(1, $statusRail['stock_reserved']['count']);
        $this->assertSame('Quotes & Proformas inbox', $snapshot['workflow_notes'][0]['title']);

        $requestNumbers = array_column($snapshot['incoming_requests'], 'request_number');

        $this->assertSame('INV-3003', $requestNumbers[0]);
        $this->assertContains('QUO-3001', $requestNumbers);
        $this->assertContains('QUO-3002', $requestNumbers);
        $this->assertSame('255/55R19', $snapshot['incoming_requests'][0]['size']);
        $this->assertSame('Stock deduction follows the current CRM method.', $statusRail['stock_deducted']['description']);
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

    #[Test]
    public function it_surfaces_submitted_procurement_requests_ahead_of_quote_conversion(): void
    {
        $user = User::factory()->create();

        $supplierAccount = $this->createAccount($user, [
            'name' => 'North Coast Tyres',
            'slug' => 'north-coast-tyres',
            'account_type' => AccountType::SUPPLIER,
            'retail_enabled' => false,
            'wholesale_enabled' => true,
        ]);

        $retailerAccount = $this->createAccount($user, [
            'name' => 'Alpha Retail',
            'slug' => 'alpha-retail',
            'account_type' => AccountType::RETAILER,
            'retail_enabled' => true,
            'wholesale_enabled' => false,
        ], attachToUser: false);

        $supplierCustomer = Customer::create([
            'customer_type' => 'wholesale',
            'business_name' => 'Alpha Retail',
            'email' => null,
            'account_id' => $supplierAccount->id,
            'status' => 'active',
        ]);

        $connection = AccountConnection::create([
            'retailer_account_id' => $retailerAccount->id,
            'supplier_account_id' => $supplierAccount->id,
            'supplier_customer_id' => $supplierCustomer->id,
            'status' => AccountConnectionStatus::APPROVED,
            'approved_at' => now(),
        ]);

        $submission = ProcurementSubmission::create([
            'submission_number' => 'PRB-20260329-0101',
            'retailer_account_id' => $retailerAccount->id,
            'submitted_by_user_id' => $user->id,
            'status' => ProcurementWorkflowStage::SUBMITTED,
            'supplier_count' => 1,
            'request_count' => 1,
            'line_item_count' => 1,
            'quantity_total' => 4,
            'subtotal' => 520,
            'currency' => 'AED',
            'source' => 'admin_workbench',
            'submitted_at' => now(),
        ]);

        $request = ProcurementRequest::create([
            'request_number' => 'PRQ-20260329-0101',
            'procurement_submission_id' => $submission->id,
            'retailer_account_id' => $retailerAccount->id,
            'supplier_account_id' => $supplierAccount->id,
            'account_connection_id' => $connection->id,
            'customer_id' => $supplierCustomer->id,
            'submitted_by_user_id' => $user->id,
            'current_stage' => ProcurementWorkflowStage::SUBMITTED,
            'line_item_count' => 1,
            'currency' => 'AED',
            'quantity_total' => 4,
            'subtotal' => 520,
            'submitted_at' => now(),
        ]);

        $request->items()->create([
            'sku' => 'TYR-INTAKE-001',
            'product_name' => 'Touring tyre',
            'size' => '225/45R17',
            'quantity' => 4,
            'unit_price' => 130,
            'line_total' => 520,
            'payload' => [
                'size' => '225/45R17',
            ],
        ]);

        $snapshot = SupplierIntakeWorkbenchSignals::forAccount($supplierAccount);
        $signalCards = collect($snapshot['signal_cards'])->keyBy('label');

        $this->assertSame(1, $snapshot['current_account_summary']['procurement_requests']);
        $this->assertSame(1, $signalCards['Quotes & Proformas inbox']['value']);
        $this->assertSame('PRQ-20260329-0101', $snapshot['incoming_requests'][0]['request_number']);
        $this->assertSame('Procurement Request', $snapshot['incoming_requests'][0]['document_type']);
        $this->assertSame('Submitted', $snapshot['incoming_requests'][0]['status']);
        $this->assertSame('225/45R17', $snapshot['incoming_requests'][0]['size']);
        $statusRail = collect($snapshot['status_rail'])->keyBy('key');
        $this->assertSame(1, $statusRail['submitted']['count']);
    }
}
