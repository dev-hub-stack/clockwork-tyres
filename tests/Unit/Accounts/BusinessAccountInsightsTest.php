<?php

namespace Tests\Unit\Accounts;

use App\Models\User;
use App\Modules\Accounts\Enums\AccountConnectionStatus;
use App\Modules\Accounts\Enums\AccountRole;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\SubscriptionPlan;
use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Models\AccountConnection;
use App\Modules\Accounts\Support\BusinessAccountInsights;
use App\Modules\Customers\Models\Customer;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Orders\Enums\DocumentType;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Procurement\Enums\ProcurementWorkflowStage;
use App\Modules\Procurement\Models\ProcurementRequest;
use App\Modules\Procurement\Models\ProcurementSubmission;
use App\Modules\Products\Models\TyreAccountOffer;
use App\Modules\Products\Models\TyreCatalogGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BusinessAccountInsightsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_builds_per_account_and_platform_summary_metrics_from_live_models(): void
    {
        $owner = User::query()->create([
            'name' => 'George Ahmad',
            'email' => 'george@clockwork.test',
            'password' => 'password',
        ]);

        $hybrid = Account::query()->create([
            'name' => 'Urban Fleet Wholesale & Retail LLC',
            'slug' => 'urban-fleet',
            'account_type' => AccountType::BOTH,
            'retail_enabled' => true,
            'wholesale_enabled' => true,
            'status' => AccountStatus::ACTIVE,
            'base_subscription_plan' => SubscriptionPlan::PREMIUM,
            'reports_subscription_enabled' => true,
            'reports_customer_limit' => 250,
            'created_by_user_id' => $owner->id,
        ]);

        $supplier = Account::query()->create([
            'name' => 'Northern Rubber Trading LLC',
            'slug' => 'northern-rubber',
            'account_type' => AccountType::SUPPLIER,
            'retail_enabled' => false,
            'wholesale_enabled' => true,
            'status' => AccountStatus::ACTIVE,
            'base_subscription_plan' => SubscriptionPlan::PREMIUM,
            'created_by_user_id' => $owner->id,
        ]);

        $retailerOne = Account::query()->create([
            'name' => 'Desert Drift Tyres LLC',
            'slug' => 'desert-drift',
            'account_type' => AccountType::RETAILER,
            'retail_enabled' => true,
            'wholesale_enabled' => false,
            'status' => AccountStatus::ACTIVE,
            'base_subscription_plan' => SubscriptionPlan::BASIC,
            'created_by_user_id' => $owner->id,
        ]);

        $retailerTwo = Account::query()->create([
            'name' => 'Marina Road Tyres LLC',
            'slug' => 'marina-road',
            'account_type' => AccountType::RETAILER,
            'retail_enabled' => true,
            'wholesale_enabled' => false,
            'status' => AccountStatus::ACTIVE,
            'base_subscription_plan' => SubscriptionPlan::BASIC,
            'created_by_user_id' => $owner->id,
        ]);

        $hybrid->users()->attach($owner->id, [
            'role' => AccountRole::OWNER->value,
            'is_default' => true,
        ]);

        Warehouse::query()->create([
            'account_id' => $hybrid->id,
            'warehouse_name' => 'Urban Fleet Main Warehouse',
            'code' => 'UF-MAIN',
            'status' => 1,
            'is_primary' => true,
            'is_system' => false,
        ]);

        AccountConnection::query()->create([
            'retailer_account_id' => $hybrid->id,
            'supplier_account_id' => $supplier->id,
            'status' => AccountConnectionStatus::APPROVED->value,
            'approved_at' => now(),
        ]);

        AccountConnection::query()->create([
            'retailer_account_id' => $retailerOne->id,
            'supplier_account_id' => $hybrid->id,
            'status' => AccountConnectionStatus::APPROVED->value,
            'approved_at' => now(),
        ]);

        AccountConnection::query()->create([
            'retailer_account_id' => $retailerTwo->id,
            'supplier_account_id' => $hybrid->id,
            'status' => AccountConnectionStatus::APPROVED->value,
            'approved_at' => now(),
        ]);

        $catalogGroup = TyreCatalogGroup::query()->create([
            'storefront_merge_key' => 'michelin-pilot-sport-4s-245-35r20-2026',
            'brand_name' => 'Michelin',
            'model_name' => 'Pilot Sport 4S',
            'width' => 245,
            'height' => 35,
            'rim_size' => 20,
            'full_size' => '245/35R20',
            'dot_year' => '2026',
        ]);

        foreach (['TYR-001', 'TYR-002', 'TYR-003'] as $sku) {
            TyreAccountOffer::query()->create([
                'tyre_catalog_group_id' => $catalogGroup->id,
                'account_id' => $hybrid->id,
                'source_sku' => $sku,
                'retail_price' => 350,
                'media_status' => 'ready',
                'inventory_status' => 'ready',
            ]);
        }

        $retailCustomer = Customer::query()->create([
            'customer_type' => 'retail',
            'business_name' => 'Urban Fleet Counter',
            'email' => 'counter@urbanfleet.test',
            'account_id' => $hybrid->id,
            'status' => 'active',
        ]);

        $retailOrder = Order::query()->create([
            'document_type' => DocumentType::INVOICE,
            'order_number' => 'INV-RET-1001',
            'order_status' => OrderStatus::PROCESSING,
            'payment_status' => PaymentStatus::PENDING,
            'customer_id' => $retailCustomer->id,
            'external_source' => 'retail',
            'sub_total' => 500,
            'shipping' => 0,
            'vat' => 25,
            'total' => 525,
            'currency' => 'AED',
            'issue_date' => now()->toDateString(),
        ]);

        $wholesaleCustomer = Customer::query()->create([
            'customer_type' => 'dealer',
            'business_name' => 'Desert Drift Tyres LLC',
            'email' => 'orders@desert-drift.test',
            'account_id' => $retailerOne->id,
            'status' => 'active',
        ]);

        $wholesaleOrder = Order::query()->create([
            'document_type' => DocumentType::INVOICE,
            'order_number' => 'INV-WHS-2001',
            'order_status' => OrderStatus::PROCESSING,
            'payment_status' => PaymentStatus::PENDING,
            'customer_id' => $wholesaleCustomer->id,
            'external_source' => 'wholesale',
            'sub_total' => 800,
            'shipping' => 0,
            'vat' => 40,
            'total' => 840,
            'currency' => 'AED',
            'issue_date' => now()->toDateString(),
        ]);

        $submission = ProcurementSubmission::query()->create([
            'submission_number' => 'PS-1001',
            'retailer_account_id' => $retailerOne->id,
            'submitted_by_user_id' => $owner->id,
            'status' => ProcurementWorkflowStage::SUBMITTED,
            'supplier_count' => 1,
            'request_count' => 1,
            'line_item_count' => 2,
            'quantity_total' => 4,
            'subtotal' => 800,
            'currency' => 'AED',
            'submitted_at' => now()->subDay(),
        ]);

        ProcurementRequest::query()->create([
            'request_number' => 'PR-1001',
            'procurement_submission_id' => $submission->id,
            'retailer_account_id' => $retailerOne->id,
            'supplier_account_id' => $hybrid->id,
            'customer_id' => $wholesaleCustomer->id,
            'submitted_by_user_id' => $owner->id,
            'invoice_order_id' => $wholesaleOrder->id,
            'current_stage' => ProcurementWorkflowStage::INVOICED,
            'line_item_count' => 2,
            'quantity_total' => 4,
            'subtotal' => 800,
            'currency' => 'AED',
            'invoiced_at' => now(),
            'submitted_at' => now()->subDay(),
        ]);

        $insights = app(BusinessAccountInsights::class);

        $accountSummary = $insights->for($hybrid);
        $platformSummary = $insights->platform();

        $this->assertSame(1, $accountSummary['connected_suppliers']);
        $this->assertSame(2, $accountSummary['connected_retailers']);
        $this->assertSame(3, $accountSummary['products_listed']);
        $this->assertSame(1, $accountSummary['warehouses']);
        $this->assertSame(1, $accountSummary['users']);
        $this->assertSame(1, $accountSummary['customers']);
        $this->assertSame(1, $accountSummary['retail_transaction_count']);
        $this->assertSame(525.0, $accountSummary['retail_transaction_value']);
        $this->assertSame(1, $accountSummary['wholesale_transaction_count']);
        $this->assertSame(840.0, $accountSummary['wholesale_transaction_value']);

        $this->assertSame(3, $platformSummary['products_listed']);
        $this->assertSame(1, $platformSummary['retail_transaction_count']);
        $this->assertSame(525.0, $platformSummary['retail_transaction_value']);
        $this->assertSame(1, $platformSummary['wholesale_transaction_count']);
        $this->assertSame(840.0, $platformSummary['wholesale_transaction_value']);
    }
}
