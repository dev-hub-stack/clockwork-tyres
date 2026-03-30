<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Accounts\Enums\AccountRole;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\SubscriptionPlan;
use App\Modules\Accounts\Models\Account;
use App\Modules\Inventory\Actions\UpsertTyreOfferInventoryAction;
use App\Modules\Inventory\Models\InventoryLog;
use App\Modules\Inventory\Models\TyreOfferInventory;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Products\Models\TyreAccountOffer;
use App\Modules\Products\Models\TyreCatalogGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpsertTyreOfferInventoryActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_upserts_tyre_offer_inventory_and_logs_the_change(): void
    {
        $owner = User::factory()->create();
        $account = Account::create([
            'name' => 'Alpha Supply',
            'slug' => 'alpha-supply',
            'account_type' => AccountType::SUPPLIER,
            'retail_enabled' => false,
            'wholesale_enabled' => true,
            'status' => AccountStatus::ACTIVE,
            'base_subscription_plan' => SubscriptionPlan::PREMIUM,
            'created_by_user_id' => $owner->id,
        ]);
        $account->users()->attach($owner->id, [
            'role' => AccountRole::OWNER->value,
            'is_default' => true,
        ]);

        $group = TyreCatalogGroup::create([
            'storefront_merge_key' => 'michelin|pilot-sport-4s|245/35R20|2026',
            'brand_name' => 'Michelin',
            'model_name' => 'Pilot Sport 4S',
            'full_size' => '245/35R20',
            'dot_year' => '2026',
        ]);

        $offer = TyreAccountOffer::create([
            'tyre_catalog_group_id' => $group->id,
            'account_id' => $account->id,
            'source_sku' => 'ALPHA-001',
            'retail_price' => 390,
        ]);

        $warehouse = Warehouse::create([
            'warehouse_name' => 'Main Warehouse',
            'code' => 'MAIN',
            'is_primary' => true,
        ]);

        $inventory = app(UpsertTyreOfferInventoryAction::class)->execute(
            offer: $offer,
            warehouse: $warehouse,
            quantity: 4,
            eta: '2026-04-15',
            etaQty: 2,
            actor: $owner,
        );

        $offer->refresh();

        $this->assertInstanceOf(TyreOfferInventory::class, $inventory);
        $this->assertSame(4, $inventory->quantity);
        $this->assertSame(2, $inventory->eta_qty);
        $this->assertSame('configured_in_stock', $offer->inventory_status);
        $this->assertDatabaseHas('inventory_logs', [
            'warehouse_id' => $warehouse->id,
            'reference_type' => 'tyre_offer',
            'reference_id' => $offer->id,
            'quantity_after' => 4,
            'eta_qty_after' => 2,
            'user_id' => $owner->id,
        ]);

        app(UpsertTyreOfferInventoryAction::class)->execute(
            offer: $offer->fresh(),
            warehouse: $warehouse,
            quantity: 0,
            eta: null,
            etaQty: 0,
            actor: $owner,
        );

        $offer->refresh();

        $this->assertSame('configured_out_of_stock', $offer->inventory_status);
        $this->assertSame(2, InventoryLog::query()->where('reference_type', 'tyre_offer')->count());
    }
}
