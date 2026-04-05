<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Accounts\Enums\AccountRole;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\SubscriptionPlan;
use App\Modules\Accounts\Models\Account;
use App\Modules\Consignments\Models\Consignment;
use App\Modules\Consignments\Models\ConsignmentItem;
use App\Modules\Customers\Models\Customer;
use App\Modules\Inventory\Models\InventoryLog;
use App\Modules\Inventory\Models\TyreDamagedInventory;
use App\Modules\Inventory\Models\TyreOfferInventory;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Products\Models\TyreAccountOffer;
use App\Modules\Products\Models\TyreCatalogGroup;
use App\Modules\Warranties\Models\WarrantyClaim;
use App\Modules\Warranties\Models\WarrantyClaimItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TyreInventoryGridTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['view_inventory', 'edit_inventory_grid', 'view_bulk_transfer', 'view_add_inventory'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
    }

    public function test_tyre_inventory_grid_page_renders_for_entitled_business_user(): void
    {
        [$user, $account] = $this->createOperationalUser();

        $this->actingAs($user)
            ->get('/admin/tyre-inventory-grid')
            ->assertOk()
            ->assertSee('Tyre Inventory Grid')
            ->assertSee($account->name)
            ->assertSee("title: 'Product Full Name'", false)
            ->assertSee("title: 'Consignment Stock'", false)
            ->assertDontSee("title: 'Status'", false)
            ->assertDontSee("title: 'Retail Price'", false)
            ->assertDontSee("title: 'Wholesale Price'", false);
    }

    public function test_tyre_inventory_grid_data_is_scoped_and_includes_current_account_metrics(): void
    {
        [$user, $account] = $this->createOperationalUser();
        $otherAccount = $this->createAccount($user, 'Hidden Supply', 'hidden-supply', false, AccountType::SUPPLIER);

        $warehouse = $this->createWarehouse($account, 'Desert Drift Main Warehouse', 'DDR-MAIN');
        $otherWarehouse = $this->createWarehouse($otherAccount, 'Hidden Supply Warehouse', 'HID-MAIN');

        $offer = $this->createOffer($account, 'TYR-PS4S-001', 'Michelin', 'Pilot Sport 4S', '245/35R20');
        $hiddenOffer = $this->createOffer($otherAccount, 'TYR-HIDDEN-999', 'Pirelli', 'P Zero', '285/45R21');

        TyreOfferInventory::create([
            'tyre_account_offer_id' => $offer->id,
            'account_id' => $account->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 6,
            'eta' => '15-04-2026',
            'eta_qty' => 2,
        ]);

        TyreOfferInventory::create([
            'tyre_account_offer_id' => $hiddenOffer->id,
            'account_id' => $otherAccount->id,
            'warehouse_id' => $otherWarehouse->id,
            'quantity' => 9,
            'eta' => null,
            'eta_qty' => 0,
        ]);

        $customer = Customer::create([
            'account_id' => $account->id,
            'business_name' => 'Fleet Customer',
            'email' => 'fleet@example.com',
            'customer_type' => 'dealer',
            'status' => 1,
        ]);

        $consignment = Consignment::create([
            'consignment_number' => 'CON-TYRE-1001',
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'status' => 'sent',
            'issue_date' => now(),
            'created_by' => $user->id,
        ]);

        ConsignmentItem::create([
            'consignment_id' => $consignment->id,
            'tyre_account_offer_id' => $offer->id,
            'warehouse_id' => $warehouse->id,
            'sku' => $offer->source_sku,
            'quantity_sent' => 4,
            'quantity_sold' => 1,
            'quantity_returned' => 1,
            'price' => 350,
            'status' => 'sent',
        ]);

        TyreDamagedInventory::create([
            'tyre_account_offer_id' => $offer->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 1,
            'condition' => 'damaged',
            'notes' => 'From Warranty Claim: WC-001',
        ]);

        $response = $this->actingAs($user)->getJson('/admin/api/tyres/inventory/grid-data');
        $response->assertOk();

        $rows = collect($response->json());
        $this->assertCount(1, $rows);

        $row = $rows->firstWhere('sku', 'TYR-PS4S-001');

        $this->assertNotNull($row);
        $this->assertSame(6, $row['current_stock']);
        $this->assertSame(2, $row['incoming_stock']);
        $this->assertSame(2, $row['consignment_stock']);
        $this->assertSame(1, $row['damaged_stock']);
        $this->assertSame('Michelin', $row['brand']);
        $this->assertSame('Pilot Sport 4S', $row['model']);
        $this->assertSame('245/35R20', $row['full_size']);
        $this->assertFalse($rows->contains(fn (array $item) => $item['sku'] === 'TYR-HIDDEN-999'));
    }

    public function test_tyre_inventory_detail_endpoints_return_consignment_incoming_and_warranty_damage_data(): void
    {
        [$user, $account] = $this->createOperationalUser();
        $warehouse = $this->createWarehouse($account, 'Desert Drift Main Warehouse', 'DDR-MAIN');
        $offer = $this->createOffer($account, 'TYR-SCV-001', 'Pirelli', 'Scorpion Verde', '235/60R18');

        TyreOfferInventory::create([
            'tyre_account_offer_id' => $offer->id,
            'account_id' => $account->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 5,
            'eta' => '20-04-2026',
            'eta_qty' => 4,
        ]);

        $customer = Customer::create([
            'account_id' => $account->id,
            'business_name' => 'Warranty Fleet',
            'email' => 'warranty-fleet@example.com',
            'customer_type' => 'dealer',
            'status' => 1,
        ]);

        $consignment = Consignment::create([
            'consignment_number' => 'CON-TYRE-1002',
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'status' => 'delivered',
            'issue_date' => now(),
            'created_by' => $user->id,
        ]);

        ConsignmentItem::create([
            'consignment_id' => $consignment->id,
            'tyre_account_offer_id' => $offer->id,
            'warehouse_id' => $warehouse->id,
            'sku' => $offer->source_sku,
            'quantity_sent' => 3,
            'quantity_sold' => 0,
            'quantity_returned' => 0,
            'price' => 305,
            'status' => 'sent',
        ]);

        $claim = WarrantyClaim::create([
            'claim_number' => 'WC-TYRE-1001',
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'claim_date' => now(),
            'issue_date' => now(),
            'created_by' => $user->id,
        ]);

        WarrantyClaimItem::create([
            'warranty_claim_id' => $claim->id,
            'tyre_account_offer_id' => $offer->id,
            'quantity' => 2,
            'issue_description' => 'Sidewall damage',
        ]);

        $this->actingAs($user);
        $claim->load('items');
        $claim->markAsReplaced();

        $this->getJson('/admin/api/tyres/inventory/sku/TYR-SCV-001/consignments')
            ->assertOk()
            ->assertJsonFragment([
                'customer' => 'Warranty Fleet',
                'available_qty' => 3,
                'consignment_number' => 'CON-TYRE-1002',
            ]);

        $this->getJson('/admin/api/tyres/inventory/sku/TYR-SCV-001/incoming?warehouse=DDR-MAIN')
            ->assertOk()
            ->assertJsonFragment([
                'warehouse_code' => 'DDR-MAIN',
                'quantity' => 4,
            ]);

        $this->getJson('/admin/api/tyres/inventory/sku/TYR-SCV-001/damaged')
            ->assertOk()
            ->assertJsonFragment([
                'warehouse_code' => 'DDR-MAIN',
                'quantity' => 2,
                'condition' => 'Damaged',
            ]);

        $this->assertDatabaseHas('inventory_logs', [
            'tyre_account_offer_id' => $offer->id,
            'reference_type' => 'warranty_claim',
            'reference_id' => $claim->id,
        ]);

        $this->assertSame(2, TyreDamagedInventory::query()->where('tyre_account_offer_id', $offer->id)->sum('quantity'));
    }

    public function test_tyre_inventory_add_endpoint_rejects_other_business_warehouse(): void
    {
        [$user, $account] = $this->createOperationalUser();
        $otherAccount = $this->createAccount($user, 'Supplier Hub', 'supplier-hub', false, AccountType::SUPPLIER);

        $offer = $this->createOffer($account, 'TYR-ADD-001', 'Bridgestone', 'Turanza T005', '225/55R17');
        $otherWarehouse = $this->createWarehouse($otherAccount, 'Supplier Warehouse', 'SUP-MAIN');

        $response = $this->actingAs($user)->postJson('/admin/tyres/inventory/add', [
            'lines' => [
                [
                    'offer_id' => $offer->id,
                    'to' => $otherWarehouse->id,
                    'quantity' => 5,
                ],
            ],
            'reference' => 'cross-account-test',
        ]);

        $response
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'The selected warehouse is not available to the current business account.',
            ]);

        $this->assertDatabaseMissing('tyre_offer_inventories', [
            'tyre_account_offer_id' => $offer->id,
            'warehouse_id' => $otherWarehouse->id,
        ]);
    }

    /**
     * @return array{0: User, 1: Account}
     */
    private function createOperationalUser(): array
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['view_inventory', 'edit_inventory_grid', 'view_bulk_transfer', 'view_add_inventory']);

        $account = $this->createAccount($user, 'Desert Drift Tyres LLC', 'desert-drift-tyres', true, AccountType::RETAILER);

        return [$user, $account];
    }

    private function createAccount(User $user, string $name, string $slug, bool $isDefault, AccountType $accountType): Account
    {
        $account = Account::create([
            'name' => $name,
            'slug' => $slug,
            'account_type' => $accountType,
            'retail_enabled' => true,
            'wholesale_enabled' => $accountType !== AccountType::RETAILER,
            'status' => AccountStatus::ACTIVE,
            'base_subscription_plan' => SubscriptionPlan::PREMIUM,
            'reports_subscription_enabled' => false,
            'created_by_user_id' => $user->id,
        ]);

        $account->users()->attach($user->id, [
            'role' => AccountRole::OWNER->value,
            'is_default' => $isDefault,
        ]);

        return $account;
    }

    private function createWarehouse(Account $account, string $name, string $code): Warehouse
    {
        return Warehouse::create([
            'account_id' => $account->id,
            'warehouse_name' => $name,
            'code' => $code,
            'status' => 1,
            'is_primary' => true,
            'is_system' => false,
        ]);
    }

    private function createOffer(Account $account, string $sku, string $brand, string $model, string $fullSize): TyreAccountOffer
    {
        $group = TyreCatalogGroup::create([
            'storefront_merge_key' => strtolower(str_replace([' ', '/', '.'], '-', "{$brand}-{$model}-{$fullSize}-2026")),
            'brand_name' => $brand,
            'model_name' => $model,
            'width' => (int) strtok($fullSize, '/'),
            'height' => (int) explode('R', explode('/', $fullSize)[1])[0],
            'rim_size' => (int) explode('R', $fullSize)[1],
            'full_size' => $fullSize,
            'dot_year' => '2026',
            'load_index' => '101',
            'speed_rating' => 'W',
            'tyre_type' => 'summer',
            'runflat' => false,
            'rfid' => false,
        ]);

        return TyreAccountOffer::create([
            'tyre_catalog_group_id' => $group->id,
            'account_id' => $account->id,
            'source_sku' => $sku,
            'retail_price' => 350,
            'wholesale_price_lvl1' => 300,
            'inventory_status' => 'in_stock',
            'media_status' => 'ready',
        ]);
    }
}
