<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Addon;
use App\Models\AddonCategory;
use App\Modules\Accounts\Enums\AccountRole;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\SubscriptionPlan;
use App\Modules\Accounts\Models\Account;
use App\Modules\Inventory\Models\InventoryLog;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductVariant;
use App\Modules\Products\Models\TyreAccountOffer;
use App\Modules\Products\Models\TyreCatalogGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class InventoryMovementLogTypeFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::firstOrCreate(['name' => 'view_inventory', 'guard_name' => 'web']);
    }

    public function test_inventory_log_page_honors_the_requested_inventory_type_preset(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view_inventory');
        $this->createAccount($user, 'Scope Demo', 'scope-demo', true);

        $response = $this->actingAs($user)->get('/admin/inventory-movement-log?inventory_type=tyres');

        $response->assertOk();
        $response->assertSee('id="f-inventory-type" value="tyres"', false);
        $response->assertSee('class="log-tab-btn active" data-type="tyres"', false);
    }

    public function test_inventory_log_can_filter_by_products_tyres_and_addons_for_current_business(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view_inventory');
        $account = $this->createAccount($user, 'Scope Demo', 'scope-demo', true);

        $warehouse = Warehouse::create([
            'account_id' => $account->id,
            'warehouse_name' => 'Scope Demo Main',
            'code' => 'SDM-01',
            'status' => 1,
            'is_primary' => true,
            'is_system' => false,
        ]);

        $group = TyreCatalogGroup::create([
            'storefront_merge_key' => 'scope-demo-245-35-20-2026',
            'brand_name' => 'Michelin',
            'model_name' => 'Pilot Sport 4S',
            'full_size' => '245/35R20',
        ]);

        $tyreOffer = TyreAccountOffer::create([
            'tyre_catalog_group_id' => $group->id,
            'account_id' => $account->id,
            'source_sku' => 'TYR-001',
            'inventory_status' => 'configured_in_stock',
            'media_status' => 'configured',
        ]);

        $product = Product::create([
            'name' => 'Scope Product',
            'sku' => 'PRD-001',
            'price' => 100,
            'status' => true,
            'track_inventory' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'PRD-001-A',
            'size' => '20x9',
            'track_inventory' => true,
        ]);

        $addonCategory = AddonCategory::create([
            'name' => 'Wheel Accessories',
            'slug' => 'wheel-accessories',
            'is_active' => true,
        ]);

        $addon = Addon::create([
            'addon_category_id' => $addonCategory->id,
            'title' => 'Valve Cap',
            'part_number' => 'ADD-001',
            'price' => 10,
        ]);

        InventoryLog::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'action' => InventoryLog::ACTION_IMPORT,
            'quantity_before' => 0,
            'quantity_after' => 5,
            'quantity_change' => 5,
            'notes' => 'Product import',
            'user_id' => $user->id,
        ]);

        InventoryLog::create([
            'warehouse_id' => $warehouse->id,
            'tyre_account_offer_id' => $tyreOffer->id,
            'action' => InventoryLog::ACTION_ADJUSTMENT,
            'quantity_before' => 0,
            'quantity_after' => 8,
            'quantity_change' => 8,
            'notes' => 'Tyre adjustment',
            'user_id' => $user->id,
        ]);

        InventoryLog::create([
            'warehouse_id' => $warehouse->id,
            'add_on_id' => $addon->id,
            'action' => InventoryLog::ACTION_IMPORT,
            'quantity_before' => 0,
            'quantity_after' => 2,
            'quantity_change' => 2,
            'notes' => 'Addon import',
            'user_id' => $user->id,
        ]);

        $productLogs = $this->actingAs($user)->getJson('/admin/inventory/log-data?inventory_type=products');
        $productLogs->assertOk()->assertJsonCount(1);
        $this->assertSame('products', $productLogs->json('0.inventory_type'));
        $this->assertSame('PRD-001-A', $productLogs->json('0.sku'));

        $tyreLogs = $this->actingAs($user)->getJson('/admin/inventory/log-data?inventory_type=tyres');
        $tyreLogs->assertOk()->assertJsonCount(1);
        $this->assertSame('tyres', $tyreLogs->json('0.inventory_type'));
        $this->assertSame('TYR-001', $tyreLogs->json('0.sku'));

        $addonLogs = $this->actingAs($user)->getJson('/admin/inventory/log-data?inventory_type=addons');
        $addonLogs->assertOk()->assertJsonCount(1);
        $this->assertSame('addons', $addonLogs->json('0.inventory_type'));
        $this->assertSame('ADD-001', $addonLogs->json('0.sku'));
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
