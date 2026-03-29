<?php

namespace Tests\Unit\Procurement;

use App\Modules\Accounts\Enums\AccountConnectionStatus;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\SubscriptionPlan;
use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Models\AccountConnection;
use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Enums\DocumentType;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\QuoteStatus;
use App\Modules\Procurement\Support\ProcurementWorkbenchData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProcurementWorkbenchDataTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_builds_account_summary_supplier_groups_and_recent_signals(): void
    {
        $now = now();

        $currentAccount = Account::create([
            'name' => 'Retail Hub',
            'slug' => 'retail-hub',
            'account_type' => AccountType::RETAILER,
            'retail_enabled' => true,
            'wholesale_enabled' => false,
            'status' => AccountStatus::ACTIVE,
            'base_subscription_plan' => SubscriptionPlan::BASIC,
            'reports_subscription_enabled' => false,
            'reports_customer_limit' => null,
            'created_by_user_id' => null,
        ]);

        $approvedSupplier = Account::create([
            'name' => 'Alpha Tyres',
            'slug' => 'alpha-tyres',
            'account_type' => AccountType::SUPPLIER,
            'retail_enabled' => false,
            'wholesale_enabled' => true,
            'status' => AccountStatus::ACTIVE,
            'base_subscription_plan' => SubscriptionPlan::PREMIUM,
            'reports_subscription_enabled' => true,
            'reports_customer_limit' => 50,
            'created_by_user_id' => null,
        ]);

        $pendingSupplier = Account::create([
            'name' => 'Bravo Supplies',
            'slug' => 'bravo-supplies',
            'account_type' => AccountType::SUPPLIER,
            'retail_enabled' => false,
            'wholesale_enabled' => true,
            'status' => AccountStatus::ACTIVE,
            'base_subscription_plan' => SubscriptionPlan::BASIC,
            'reports_subscription_enabled' => false,
            'reports_customer_limit' => null,
            'created_by_user_id' => null,
        ]);

        $inactiveSupplier = Account::create([
            'name' => 'Charlie Trade',
            'slug' => 'charlie-trade',
            'account_type' => AccountType::SUPPLIER,
            'retail_enabled' => false,
            'wholesale_enabled' => true,
            'status' => AccountStatus::ACTIVE,
            'base_subscription_plan' => SubscriptionPlan::BASIC,
            'reports_subscription_enabled' => false,
            'reports_customer_limit' => null,
            'created_by_user_id' => null,
        ]);

        AccountConnection::create([
            'retailer_account_id' => $currentAccount->id,
            'supplier_account_id' => $approvedSupplier->id,
            'status' => AccountConnectionStatus::APPROVED,
            'approved_at' => $now->copy()->subDays(3),
            'notes' => 'Preferred source.',
        ]);

        AccountConnection::create([
            'retailer_account_id' => $currentAccount->id,
            'supplier_account_id' => $pendingSupplier->id,
            'status' => AccountConnectionStatus::PENDING,
            'approved_at' => null,
            'notes' => 'Awaiting approval.',
        ]);

        AccountConnection::create([
            'retailer_account_id' => $currentAccount->id,
            'supplier_account_id' => $inactiveSupplier->id,
            'status' => AccountConnectionStatus::INACTIVE,
            'approved_at' => null,
            'notes' => 'Paused by admin.',
        ]);

        $firstCustomer = Customer::create([
            'customer_type' => 'retail',
            'business_name' => 'First Customer',
            'email' => 'first@example.com',
            'account_id' => $currentAccount->id,
            'status' => 'active',
        ]);

        $secondCustomer = Customer::create([
            'customer_type' => 'dealer',
            'business_name' => 'Second Customer',
            'email' => 'second@example.com',
            'account_id' => $currentAccount->id,
            'status' => 'active',
        ]);

        DB::table('orders')->insert([
            [
                'document_type' => DocumentType::QUOTE->value,
                'quote_number' => 'QUO-1001',
                'quote_status' => QuoteStatus::APPROVED->value,
                'order_number' => 'QUO-1001',
                'order_status' => OrderStatus::PENDING->value,
                'payment_status' => 'pending',
                'customer_id' => $firstCustomer->id,
                'sub_total' => 120,
                'tax' => 0,
                'vat' => 0,
                'shipping' => 0,
                'discount' => 0,
                'total' => 120,
                'currency' => 'AED',
                'tax_inclusive' => true,
                'issue_date' => $now->copy()->subDays(2)->toDateString(),
                'created_at' => $now->copy()->subDays(2),
                'updated_at' => $now->copy()->subDays(2),
            ],
            [
                'document_type' => DocumentType::INVOICE->value,
                'quote_number' => null,
                'quote_status' => null,
                'order_number' => 'INV-1001',
                'order_status' => OrderStatus::DELIVERED->value,
                'payment_status' => 'paid',
                'customer_id' => $secondCustomer->id,
                'sub_total' => 220,
                'tax' => 0,
                'vat' => 0,
                'shipping' => 0,
                'discount' => 0,
                'total' => 220,
                'currency' => 'AED',
                'tax_inclusive' => true,
                'issue_date' => $now->copy()->subDay()->toDateString(),
                'created_at' => $now->copy()->subDay(),
                'updated_at' => $now->copy()->subDay(),
            ],
            [
                'document_type' => DocumentType::ORDER->value,
                'quote_number' => null,
                'quote_status' => null,
                'order_number' => 'ORD-1001',
                'order_status' => OrderStatus::PROCESSING->value,
                'payment_status' => 'pending',
                'customer_id' => $firstCustomer->id,
                'sub_total' => 75,
                'tax' => 0,
                'vat' => 0,
                'shipping' => 0,
                'discount' => 0,
                'total' => 75,
                'currency' => 'AED',
                'tax_inclusive' => true,
                'issue_date' => $now->toDateString(),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $data = ProcurementWorkbenchData::forAccount($currentAccount);

        $summary = $data->currentAccountSummary();
        $groups = $data->supplierGroups();
        $signals = $data->recentProcurementSignals(2);

        $this->assertTrue($summary['has_current_account']);
        $this->assertSame('Retail Hub', $summary['account']['name']);
        $this->assertSame(3, $summary['supplier_connections']['total']);
        $this->assertSame(1, $summary['supplier_connections']['approved']);
        $this->assertSame(1, $summary['supplier_connections']['pending']);
        $this->assertSame(1, $summary['supplier_connections']['inactive']);
        $this->assertSame(2, $summary['customer_count']);
        $this->assertSame(1, $summary['document_counts']['quotes']);
        $this->assertSame(1, $summary['document_counts']['orders']);
        $this->assertSame(1, $summary['document_counts']['invoices']);
        $this->assertSame(3, $summary['document_counts']['total']);
        $this->assertSame(415.0, $summary['document_total']);
        $this->assertSame($now->toDateTimeString(), $summary['last_activity_at']);
        $this->assertSame('Order Processing', $summary['last_activity_label']);

        $this->assertCount(3, $groups);
        $this->assertSame('Alpha Tyres', $groups[0]['supplier_name']);
        $this->assertSame('Approved', $groups[0]['connection_status_label']);
        $this->assertSame('Bravo Supplies', $groups[1]['supplier_name']);
        $this->assertSame('Pending', $groups[1]['connection_status_label']);
        $this->assertSame('Charlie Trade', $groups[2]['supplier_name']);
        $this->assertSame('Inactive', $groups[2]['connection_status_label']);

        $this->assertCount(2, $signals);
        $this->assertSame('ORD-1001', $signals[0]['document_number']);
        $this->assertSame('order', $signals[0]['document_type']);
        $this->assertSame('Processing', $signals[0]['status_label']);
        $this->assertSame('INV-1001', $signals[1]['document_number']);
        $this->assertSame('invoice', $signals[1]['document_type']);
        $this->assertSame('Delivered', $signals[1]['status_label']);
    }

    #[Test]
    public function it_returns_safe_empty_payloads_without_a_current_account(): void
    {
        $data = ProcurementWorkbenchData::forAccount(null);

        $summary = $data->currentAccountSummary();

        $this->assertFalse($summary['has_current_account']);
        $this->assertNull($summary['account']);
        $this->assertSame(0, $summary['supplier_connections']['total']);
        $this->assertSame(0, $summary['document_counts']['total']);
        $this->assertSame([], $data->supplierGroups());
        $this->assertSame([], $data->recentProcurementSignals());
    }
}
