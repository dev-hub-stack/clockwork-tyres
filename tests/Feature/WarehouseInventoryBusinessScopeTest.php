<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Accounts\Enums\AccountRole;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\SubscriptionPlan;
use App\Modules\Accounts\Models\Account;
use App\Modules\Inventory\Models\ProductInventory;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class WarehouseInventoryBusinessScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['view_warehouses', 'view_inventory'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
    }

    public function test_business_user_only_sees_warehouses_for_their_current_account(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view_warehouses');

        $retailAccount = $this->createAccount($user, 'Retail HQ', 'retail-hq', true);
        $supplierAccount = $this->createAccount($user, 'Supplier Hub', 'supplier-hub');

        $retailWarehouse = Warehouse::create([
            'account_id' => $retailAccount->id,
            'warehouse_name' => 'Retail Main Warehouse',
            'code' => 'RTL-MAIN',
            'status' => 1,
            'is_primary' => true,
            'is_system' => false,
        ]);

        Warehouse::create([
            'account_id' => $supplierAccount->id,
            'warehouse_name' => 'Supplier Main Warehouse',
            'code' => 'SUP-MAIN',
            'status' => 1,
            'is_primary' => true,
            'is_system' => false,
        ]);

        $this->actingAs($user)
            ->get('/admin/warehouses')
            ->assertOk()
            ->assertSee($retailWarehouse->warehouse_name)
            ->assertDontSee('Supplier Main Warehouse');
    }

    public function test_inventory_grid_and_template_only_include_current_account_warehouses(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view_inventory');

        $retailAccount = $this->createAccount($user, 'Retail HQ', 'retail-hq', true);
        $supplierAccount = $this->createAccount($user, 'Supplier Hub', 'supplier-hub');

        $product = Product::create([
            'name' => 'Demo Wheel',
            'sku' => 'WHEEL-DEMO',
            'price' => 100,
            'status' => true,
            'track_inventory' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'WHEEL-DEMO-01',
            'size' => '20x9',
            'track_inventory' => true,
        ]);

        $retailWarehouse = Warehouse::create([
            'account_id' => $retailAccount->id,
            'warehouse_name' => 'Retail Main Warehouse',
            'code' => 'RTL-MAIN',
            'status' => 1,
            'is_primary' => true,
            'is_system' => false,
        ]);

        $supplierWarehouse = Warehouse::create([
            'account_id' => $supplierAccount->id,
            'warehouse_name' => 'Supplier Main Warehouse',
            'code' => 'SUP-MAIN',
            'status' => 1,
            'is_primary' => true,
            'is_system' => false,
        ]);

        ProductInventory::create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'warehouse_id' => $retailWarehouse->id,
            'quantity' => 5,
            'eta_qty' => 0,
        ]);

        ProductInventory::create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'warehouse_id' => $supplierWarehouse->id,
            'quantity' => 9,
            'eta_qty' => 0,
        ]);

        $templateResponse = $this->actingAs($user)->get('/admin/inventory/template');
        $templateResponse->assertOk();
        $templateContent = $templateResponse->streamedContent();

        $this->assertStringContainsString('RTL-MAIN', $templateContent);
        $this->assertStringNotContainsString('SUP-MAIN', $templateContent);

        $gridResponse = $this->actingAs($user)->getJson('/admin/api/inventory/grid-data');
        $gridResponse->assertOk();

        $inventoryRows = collect($gridResponse->json())->firstWhere('sku', 'WHEEL-DEMO-01')['inventory'] ?? [];

        $this->assertCount(1, $inventoryRows);
        $this->assertSame($retailWarehouse->id, $inventoryRows[0]['warehouse_id']);
    }

    public function test_inventory_write_endpoints_reject_other_business_warehouses(): void
    {
        $user = User::factory()->create();

        $retailAccount = $this->createAccount($user, 'Retail HQ', 'retail-hq', true);
        $supplierAccount = $this->createAccount($user, 'Supplier Hub', 'supplier-hub');

        $product = Product::create([
            'name' => 'Demo Wheel',
            'sku' => 'WHEEL-DEMO',
            'price' => 100,
            'status' => true,
            'track_inventory' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'WHEEL-DEMO-02',
            'size' => '20x10',
            'track_inventory' => true,
        ]);

        Warehouse::create([
            'account_id' => $retailAccount->id,
            'warehouse_name' => 'Retail Main Warehouse',
            'code' => 'RTL-MAIN',
            'status' => 1,
            'is_primary' => true,
            'is_system' => false,
        ]);

        $supplierWarehouse = Warehouse::create([
            'account_id' => $supplierAccount->id,
            'warehouse_name' => 'Supplier Main Warehouse',
            'code' => 'SUP-MAIN',
            'status' => 1,
            'is_primary' => true,
            'is_system' => false,
        ]);

        $response = $this->actingAs($user)->postJson('/admin/inventory/add', [
            'lines' => [
                [
                    'variant_id' => $variant->id,
                    'to' => $supplierWarehouse->id,
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

        $this->assertDatabaseMissing('product_inventories', [
            'product_variant_id' => $variant->id,
            'warehouse_id' => $supplierWarehouse->id,
            'quantity' => 5,
        ]);
    }

    private function createAccount(User $user, string $name, string $slug, bool $isDefault = false): Account
    {
        $account = Account::create([
            'name' => $name,
            'slug' => $slug,
            'account_type' => AccountType::BOTH,
            'retail_enabled' => true,
            'wholesale_enabled' => true,
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
}
