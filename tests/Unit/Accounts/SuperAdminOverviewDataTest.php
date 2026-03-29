<?php

namespace Tests\Unit\Accounts;

use App\Modules\Accounts\Enums\AccountConnectionStatus;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\SubscriptionPlan;
use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Models\AccountConnection;
use App\Modules\Accounts\Models\AccountSubscription;
use App\Modules\Accounts\Support\SuperAdminOverviewData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SuperAdminOverviewDataTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_builds_query_backed_overview_rows_from_live_models(): void
    {
        $retailOne = $this->createAccount([
            'name' => 'Retail One',
            'slug' => 'retail-one',
            'account_type' => AccountType::RETAILER->value,
            'retail_enabled' => true,
            'wholesale_enabled' => false,
            'status' => AccountStatus::ACTIVE->value,
            'base_subscription_plan' => SubscriptionPlan::BASIC->value,
            'reports_subscription_enabled' => false,
            'reports_customer_limit' => null,
        ]);

        $supplierOne = $this->createAccount([
            'name' => 'Supplier One',
            'slug' => 'supplier-one',
            'account_type' => AccountType::SUPPLIER->value,
            'retail_enabled' => false,
            'wholesale_enabled' => true,
            'status' => AccountStatus::ACTIVE->value,
            'base_subscription_plan' => SubscriptionPlan::PREMIUM->value,
            'reports_subscription_enabled' => true,
            'reports_customer_limit' => 250,
        ]);

        $bothModes = $this->createAccount([
            'name' => 'Both Modes',
            'slug' => 'both-modes',
            'account_type' => AccountType::BOTH->value,
            'retail_enabled' => true,
            'wholesale_enabled' => true,
            'status' => AccountStatus::SUSPENDED->value,
            'base_subscription_plan' => SubscriptionPlan::PREMIUM->value,
            'reports_subscription_enabled' => true,
            'reports_customer_limit' => 500,
        ]);

        AccountSubscription::create([
            'account_id' => $retailOne,
            'plan_code' => SubscriptionPlan::BASIC->value,
            'status' => 'active',
            'reports_enabled' => false,
            'reports_customer_limit' => null,
            'starts_at' => now()->subDays(10),
        ]);

        AccountSubscription::create([
            'account_id' => $supplierOne,
            'plan_code' => SubscriptionPlan::BASIC->value,
            'status' => 'active',
            'reports_enabled' => false,
            'reports_customer_limit' => null,
            'starts_at' => now()->subDays(8),
        ]);

        AccountSubscription::create([
            'account_id' => $supplierOne,
            'plan_code' => SubscriptionPlan::BASIC->value,
            'status' => 'active',
            'reports_enabled' => true,
            'reports_customer_limit' => 250,
            'starts_at' => now()->subDays(1),
        ]);

        AccountSubscription::create([
            'account_id' => $bothModes,
            'plan_code' => SubscriptionPlan::PREMIUM->value,
            'status' => 'active',
            'reports_enabled' => true,
            'reports_customer_limit' => 500,
            'starts_at' => now()->subDay(),
        ]);

        AccountConnection::create([
            'retailer_account_id' => $retailOne,
            'supplier_account_id' => $supplierOne,
            'status' => AccountConnectionStatus::APPROVED->value,
            'approved_at' => now(),
            'notes' => 'Approved for launch',
        ]);

        AccountConnection::create([
            'retailer_account_id' => $bothModes,
            'supplier_account_id' => $supplierOne,
            'status' => AccountConnectionStatus::APPROVED->value,
            'approved_at' => now(),
            'notes' => 'Second approved retailer',
        ]);

        AccountConnection::create([
            'retailer_account_id' => $bothModes,
            'supplier_account_id' => $retailOne,
            'status' => AccountConnectionStatus::PENDING->value,
            'notes' => 'Pending review',
        ]);

        $overview = new SuperAdminOverviewData();

        $this->assertSame([
            'Account',
            'Type',
            'Status',
            'Base plan',
            'Reports add-on',
            'Wholesale',
            'Retail',
            'Approved connections',
        ], $overview->buildAccountDirectoryColumns());

        $this->assertSame([
            [
                'account' => 'Both Modes',
                'type' => 'Retailer & Supplier',
                'status' => 'Suspended',
                'base_plan' => 'Premium',
                'reports_addon' => '500 customers',
                'wholesale' => 'Yes',
                'retail' => 'Yes',
                'approved_connections' => '1',
            ],
            [
                'account' => 'Retail One',
                'type' => 'Retailer',
                'status' => 'Active',
                'base_plan' => 'Basic',
                'reports_addon' => 'Disabled',
                'wholesale' => 'No',
                'retail' => 'Yes',
                'approved_connections' => '1',
            ],
            [
                'account' => 'Supplier One',
                'type' => 'Supplier',
                'status' => 'Active',
                'base_plan' => 'Premium',
                'reports_addon' => '250 customers',
                'wholesale' => 'Yes',
                'retail' => 'No',
                'approved_connections' => '2',
            ],
        ], $overview->buildAccountRows());

        $this->assertSame([
            [
                'label' => '250 customer limit',
                'summary' => 'Basic plan, 1 active subscription',
                'note' => 'Accounts: Supplier One',
            ],
            [
                'label' => '500 customer limit',
                'summary' => 'Premium plan, 1 active subscription',
                'note' => 'Accounts: Both Modes',
            ],
        ], $overview->buildReportAddOnTiers());

        $this->assertSame([
            [
                'label' => 'Approved connections',
                'value' => 2,
                'note' => 'Live retailer-to-supplier relationships.',
            ],
            [
                'label' => 'Pending connections',
                'value' => 1,
                'note' => 'Supplier links still awaiting review or approval.',
            ],
        ], $overview->buildConnectionSummary());
    }

    #[Test]
    public function it_exports_a_single_payload_for_super_admin_overview_sections(): void
    {
        $overview = new SuperAdminOverviewData();

        $payload = $overview->toArray();

        $this->assertArrayHasKey('account_directory_columns', $payload);
        $this->assertArrayHasKey('account_rows', $payload);
        $this->assertArrayHasKey('report_add_on_tiers', $payload);
        $this->assertArrayHasKey('connection_summary', $payload);
    }

    protected function createAccount(array $attributes): int
    {
        return (int) Account::query()->insertGetId(array_merge($attributes, [
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }
}
