<?php

namespace Tests\Feature;

use App\Filament\Pages\ProcurementWorkbench;
use App\Models\User;
use App\Modules\Accounts\Enums\AccountConnectionStatus;
use App\Modules\Accounts\Enums\AccountRole;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\SubscriptionPlan;
use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Models\AccountConnection;
use App\Modules\Customers\Models\Customer;
use App\Modules\Inventory\Models\TyreOfferInventory;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Orders\Models\Order;
use App\Modules\Procurement\Actions\SubmitGroupedProcurementAction;
use App\Modules\Procurement\Models\ProcurementSubmission;
use App\Modules\Products\Models\TyreAccountOffer;
use App\Modules\Products\Models\TyreCatalogGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ProcurementWorkbenchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::firstOrCreate(['name' => 'view_quotes', 'guard_name' => 'web']);
    }

    public function test_procurement_workbench_shows_grouped_supplier_checkout_sections_with_live_offers(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view_quotes');

        $currentAccount = $this->createAccount($user, [
            'name' => 'Retail Admin',
            'slug' => 'retail-admin',
            'account_type' => AccountType::BOTH,
            'retail_enabled' => true,
            'wholesale_enabled' => true,
            'base_subscription_plan' => SubscriptionPlan::PREMIUM,
            'reports_subscription_enabled' => true,
        ], true);

        $firstSupplier = $this->createSupplier('North Coast Tyres', 'north-coast-tyres');
        $secondSupplier = $this->createSupplier('Desert Line Trading', 'desert-line-trading');

        AccountConnection::create([
            'retailer_account_id' => $currentAccount->id,
            'supplier_account_id' => $firstSupplier->id,
            'status' => AccountConnectionStatus::APPROVED,
            'approved_at' => now(),
            'notes' => 'Approved supplier stock ready for procurement.',
        ]);

        AccountConnection::create([
            'retailer_account_id' => $currentAccount->id,
            'supplier_account_id' => $secondSupplier->id,
            'status' => AccountConnectionStatus::APPROVED,
            'approved_at' => now(),
            'notes' => 'Second supplier group ready for shared checkout.',
        ]);

        $this->seedOffer($firstSupplier, 'NCT-PS4S-245', 'Michelin', 'Pilot Sport 4S', '245/35R20', 350, 12);
        $this->seedOffer($secondSupplier, 'DLT-SC7-255', 'Continental', 'SportContact 7', '255/35R19', 315, 8);

        $customer = Customer::create([
            'customer_type' => 'retail',
            'business_name' => 'Retail Walk-in',
            'email' => 'walkin@example.test',
            'account_id' => $currentAccount->id,
            'status' => 'active',
        ]);

        Order::create([
            'document_type' => 'quote',
            'customer_id' => $customer->id,
            'quote_number' => 'QUO-5001',
            'quote_status' => 'approved',
            'order_number' => 'QUO-5001',
            'payment_status' => 'pending',
            'sub_total' => 400,
            'tax' => 0,
            'vat' => 0,
            'shipping' => 0,
            'discount' => 0,
            'total' => 400,
            'currency' => 'AED',
            'tax_inclusive' => true,
            'issue_date' => now(),
            'channel' => 'wholesale',
        ]);

        $this->actingAs($user)
            ->get('/admin/procurement-workbench')
            ->assertOk()
            ->assertSee('Procurement Checkout')
            ->assertSee('North Coast Tyres')
            ->assertSee('Desert Line Trading')
            ->assertSee('Michelin')
            ->assertSee('Pilot Sport 4S')
            ->assertSee('Continental')
            ->assertSee('SportContact 7')
            ->assertSee('Checkout summary')
            ->assertSee('Recent procurement activity');

        /** @var ProcurementWorkbench $page */
        $page = app(ProcurementWorkbench::class);
        $page->mount();

        $this->assertCount(2, $page->supplierCatalogSections);
        $this->assertSame('Retail Admin', $page->currentAccountSummary['account']['name']);
        $this->assertSame(2, $page->checkoutSummary['approved_suppliers']);
        $this->assertSame(0, $page->checkoutSummary['selected_lines']);
        $this->assertSame('Select items to place order', $page->checkoutSummary['action_label']);
        $this->assertSame('NCT-PS4S-245', $page->supplierCatalogSections[0]['offers'][0]['sku']);
    }

    public function test_procurement_workbench_surfaces_persisted_grouped_procurement_requests(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view_quotes');

        $currentAccount = $this->createAccount($user, [
            'name' => 'Retail Admin',
            'slug' => 'retail-admin',
            'account_type' => AccountType::BOTH,
            'retail_enabled' => true,
            'wholesale_enabled' => true,
            'base_subscription_plan' => SubscriptionPlan::PREMIUM,
            'reports_subscription_enabled' => true,
        ], true);

        $supplier = $this->createSupplier('North Coast Tyres', 'north-coast-tyres');

        AccountConnection::create([
            'retailer_account_id' => $currentAccount->id,
            'supplier_account_id' => $supplier->id,
            'status' => AccountConnectionStatus::APPROVED,
            'approved_at' => now(),
        ]);

        $this->seedOffer($supplier, 'TYR-225-45-17', 'Bridgestone', 'Turanza T005', '225/45R17', 120, 20);

        $customer = Customer::create([
            'customer_type' => 'retail',
            'business_name' => 'Retail Walk-in',
            'email' => 'walkin@example.test',
            'account_id' => $currentAccount->id,
            'status' => 'active',
        ]);

        $submission = app(SubmitGroupedProcurementAction::class)->execute(
            retailerAccount: $currentAccount,
            actor: $user,
            customer: $customer,
            lineItems: [
                [
                    'supplier_id' => $supplier->id,
                    'supplier_name' => 'North Coast Tyres',
                    'sku' => 'TYR-225-45-17',
                    'product_name' => 'Bridgestone Turanza T005',
                    'size' => '225/45R17',
                    'quantity' => 4,
                    'unit_price' => 120,
                    'source' => 'Approved supplier connection',
                ],
            ],
        );

        $requestNumber = $submission->requests->first()?->request_number;

        $this->assertNotNull($requestNumber);

        $this->actingAs($user)
            ->get('/admin/procurement-workbench')
            ->assertOk()
            ->assertSee($requestNumber);

        /** @var ProcurementWorkbench $page */
        $page = app(ProcurementWorkbench::class);
        $page->mount();

        $this->assertSame($requestNumber, $page->recentProcurementSignals[0]['document_number']);
        $this->assertSame('Procurement Request', $page->recentProcurementSignals[0]['document_type_label']);
    }

    public function test_procurement_workbench_can_submit_grouped_procurement_from_selected_supplier_lines(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view_quotes');

        $currentAccount = $this->createAccount($user, [
            'name' => 'Retail Admin',
            'slug' => 'retail-admin',
            'account_type' => AccountType::BOTH,
            'retail_enabled' => true,
            'wholesale_enabled' => true,
            'base_subscription_plan' => SubscriptionPlan::PREMIUM,
            'reports_subscription_enabled' => true,
        ], true);

        $supplier = $this->createSupplier('North Coast Tyres', 'north-coast-tyres');

        AccountConnection::create([
            'retailer_account_id' => $currentAccount->id,
            'supplier_account_id' => $supplier->id,
            'status' => AccountConnectionStatus::APPROVED,
            'approved_at' => now(),
        ]);

        $offer = $this->seedOffer($supplier, 'NCT-PS4S-245', 'Michelin', 'Pilot Sport 4S', '245/35R20', 350, 12);

        $this->actingAs($user)
            ->get('/admin/procurement-workbench')
            ->assertOk()
            ->assertSee('Procurement Checkout');

        /** @var ProcurementWorkbench $page */
        $page = app(ProcurementWorkbench::class);
        $page->mount();
        $page->updatedSelectedQuantities(4, (string) $offer->id);
        $page->submitGroupedProcurement(app(SubmitGroupedProcurementAction::class));

        $this->assertDatabaseCount('procurement_submissions', 1);
        $this->assertDatabaseCount('procurement_requests', 1);
        $this->assertSame(1, ProcurementSubmission::query()->firstOrFail()->request_count);
        $this->assertSame(1, $page->checkoutSummary['selected_suppliers']);
        $this->assertSame(1, $page->latestSubmissionSummary['supplier_count'] ?? null);
        $this->assertSame(1, $page->latestSubmissionSummary['request_count'] ?? null);
    }

    private function createAccount(User $user, array $attributes, bool $isDefault = false): Account
    {
        $account = Account::create(array_merge([
            'status' => AccountStatus::ACTIVE,
            'reports_subscription_enabled' => false,
            'reports_customer_limit' => null,
            'created_by_user_id' => $user->id,
        ], $attributes));

        $account->users()->attach($user->id, [
            'role' => AccountRole::OWNER->value,
            'is_default' => $isDefault,
        ]);

        return $account;
    }

    private function createSupplier(string $name, string $slug): Account
    {
        return Account::create([
            'name' => $name,
            'slug' => $slug,
            'account_type' => AccountType::SUPPLIER,
            'retail_enabled' => false,
            'wholesale_enabled' => true,
            'status' => AccountStatus::ACTIVE,
            'base_subscription_plan' => SubscriptionPlan::BASIC,
            'reports_subscription_enabled' => false,
        ]);
    }

    private function seedOffer(
        Account $supplier,
        string $sku,
        string $brand,
        string $model,
        string $size,
        float $unitPrice,
        int $quantity,
    ): TyreAccountOffer {
        preg_match('/^(?<width>\\d+)\\/(?<height>\\d+)R(?<rim>\\d+)$/', $size, $matches);

        $catalogGroup = TyreCatalogGroup::create([
            'storefront_merge_key' => strtolower($brand.'|'.$model.'|'.$size.'|2026'),
            'brand_name' => $brand,
            'model_name' => $model,
            'width' => (int) ($matches['width'] ?? 0),
            'height' => (int) ($matches['height'] ?? 0),
            'rim_size' => (int) ($matches['rim'] ?? 0),
            'full_size' => $size,
            'load_index' => '95',
            'speed_rating' => 'Y',
            'dot_year' => '2026',
            'country' => 'Japan',
            'tyre_type' => 'Summer',
            'runflat' => false,
            'rfid' => false,
            'sidewall' => 'Blackwall',
            'warranty' => 'Manufacturer warranty',
        ]);

        $offer = TyreAccountOffer::create([
            'tyre_catalog_group_id' => $catalogGroup->id,
            'account_id' => $supplier->id,
            'source_sku' => $sku,
            'retail_price' => $unitPrice + 25,
            'wholesale_price_lvl1' => $unitPrice,
            'wholesale_price_lvl2' => $unitPrice - 5,
            'wholesale_price_lvl3' => $unitPrice - 10,
            'brand_image' => null,
            'product_image_1' => null,
            'product_image_2' => null,
            'product_image_3' => null,
            'media_status' => 'configured',
            'inventory_status' => 'configured_in_stock',
            'offer_payload' => ['test_seed' => true],
        ]);

        $warehouse = Warehouse::create([
            'warehouse_name' => $supplier->name.' Main Warehouse',
            'code' => strtoupper(substr($slug = preg_replace('/[^A-Z]/', '', strtoupper($supplier->slug)) ?: 'SUP', 0, 3)).'-MAIN-'.random_int(10, 99),
            'address' => 'Dubai Industrial Area',
            'city' => 'Dubai',
            'state' => 'Dubai',
            'country' => 'United Arab Emirates',
            'postal_code' => '00000',
            'status' => 1,
            'is_primary' => true,
            'is_system' => false,
        ]);

        TyreOfferInventory::create([
            'tyre_account_offer_id' => $offer->id,
            'account_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => $quantity,
            'eta_qty' => 0,
        ]);

        return $offer;
    }
}
