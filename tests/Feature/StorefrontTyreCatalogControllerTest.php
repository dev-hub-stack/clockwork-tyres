<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Accounts\Enums\AccountConnectionStatus;
use App\Modules\Accounts\Enums\AccountRole;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\SubscriptionPlan;
use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Models\AccountConnection;
use App\Modules\Inventory\Models\TyreOfferInventory;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Products\Models\TyreAccountOffer;
use App\Modules\Products\Models\TyreCatalogGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StorefrontTyreCatalogControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_retail_store_catalog_groups_own_and_supplier_offers_for_the_current_account(): void
    {
        [$owner, $account] = $this->createOwnerAccount('Alpha Retail', 'alpha-retail', AccountType::BOTH, true, true);
        $supplier = $this->createStandaloneAccount('Bravo Supply', 'bravo-supply', AccountType::SUPPLIER, false, true);
        $hiddenSupplier = $this->createStandaloneAccount('Hidden Supply', 'hidden-supply', AccountType::SUPPLIER, false, true);

        AccountConnection::create([
            'retailer_account_id' => $account->id,
            'supplier_account_id' => $supplier->id,
            'status' => AccountConnectionStatus::APPROVED,
            'approved_at' => now(),
        ]);

        AccountConnection::create([
            'retailer_account_id' => $account->id,
            'supplier_account_id' => $hiddenSupplier->id,
            'status' => AccountConnectionStatus::PENDING,
        ]);

        $ownFirstGroup = TyreCatalogGroup::create([
            'storefront_merge_key' => 'michelin|pilot-sport-4s|245/35R20|2026',
            'brand_name' => 'Michelin',
            'model_name' => 'Pilot Sport 4S',
            'full_size' => '245/35R20',
            'dot_year' => '2026',
            'load_index' => '95',
            'speed_rating' => 'Y',
            'tyre_type' => 'Summer',
            'country' => 'France',
        ]);

        $supplierOnlyGroup = TyreCatalogGroup::create([
            'storefront_merge_key' => 'continental|sportcontact-7|255/35R19|2026',
            'brand_name' => 'Continental',
            'model_name' => 'SportContact 7',
            'full_size' => '255/35R19',
            'dot_year' => '2026',
            'load_index' => '96',
            'speed_rating' => 'Y',
            'tyre_type' => 'Summer',
            'country' => 'Germany',
        ]);

        $hiddenGroup = TyreCatalogGroup::create([
            'storefront_merge_key' => 'goodyear|eagle-f1|265/40R20|2026',
            'brand_name' => 'Goodyear',
            'model_name' => 'Eagle F1',
            'full_size' => '265/40R20',
            'dot_year' => '2026',
        ]);

        $ownOffer = TyreAccountOffer::create([
            'tyre_catalog_group_id' => $ownFirstGroup->id,
            'account_id' => $account->id,
            'source_sku' => 'ALPHA-001',
            'retail_price' => 390,
            'product_image_1' => 'alpha-own.png',
        ]);

        $supplierOffer = TyreAccountOffer::create([
            'tyre_catalog_group_id' => $ownFirstGroup->id,
            'account_id' => $supplier->id,
            'source_sku' => 'BRAVO-001',
            'retail_price' => 360,
            'product_image_1' => 'bravo-supplier.png',
        ]);

        $supplierOnlyOffer = TyreAccountOffer::create([
            'tyre_catalog_group_id' => $supplierOnlyGroup->id,
            'account_id' => $supplier->id,
            'source_sku' => 'BRAVO-002',
            'retail_price' => 340,
            'product_image_1' => 'bravo-only.png',
        ]);

        $hiddenOffer = TyreAccountOffer::create([
            'tyre_catalog_group_id' => $hiddenGroup->id,
            'account_id' => $hiddenSupplier->id,
            'source_sku' => 'HIDDEN-001',
            'retail_price' => 310,
        ]);

        $mainWarehouse = $this->createWarehouse('Main Warehouse', 'MAIN');
        $secondaryWarehouse = $this->createWarehouse('Secondary Warehouse', 'SECONDARY');

        $this->seedOfferInventory($ownOffer, $mainWarehouse, 4);
        $this->seedOfferInventory($supplierOffer, $secondaryWarehouse, 9);
        $this->seedOfferInventory($supplierOnlyOffer, $secondaryWarehouse, 3);
        $this->seedOfferInventory($hiddenOffer, $secondaryWarehouse, 5);

        $token = $owner->createToken('storefront-catalog')->plainTextToken;

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/storefront/catalog/tyres?mode=retail-store');

        $response
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.meta.mode', 'retail-store')
            ->assertJsonPath('data.meta.category', 'tyres')
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.brand', 'Michelin')
            ->assertJsonPath('data.items.0.availability.origin', 'own')
            ->assertJsonPath('data.items.0.availability.label', 'in stock')
            ->assertJsonPath('data.items.0.availability.quantity', 4)
            ->assertJsonPath('data.items.0.availability.show_quantity', true)
            ->assertJsonPath('data.items.0.availability.supplier_count', 1)
            ->assertJsonPath('data.items.0.price', 390)
            ->assertJsonPath('data.items.1.brand', 'Continental')
            ->assertJsonPath('data.items.1.availability.origin', 'supplier')
            ->assertJsonPath('data.items.1.availability.label', 'available')
            ->assertJsonPath('data.items.1.availability.quantity', 3)
            ->assertJsonPath('data.items.1.availability.show_quantity', true)
            ->assertJsonPath('data.items.1.availability.supplier_count', 1);
    }

    public function test_supplier_preview_catalog_only_returns_the_current_accounts_own_offers(): void
    {
        [$owner, $account] = $this->createOwnerAccount('Supplier Preview', 'supplier-preview', AccountType::SUPPLIER, false, true);
        $supplier = $this->createStandaloneAccount('Bravo Supply', 'bravo-supply', AccountType::SUPPLIER, false, true);

        $ownGroup = TyreCatalogGroup::create([
            'storefront_merge_key' => 'pirelli|p-zero|285/45R21|2026',
            'brand_name' => 'Pirelli',
            'model_name' => 'P Zero',
            'full_size' => '285/45R21',
            'dot_year' => '2026',
        ]);

        $supplierOnlyGroup = TyreCatalogGroup::create([
            'storefront_merge_key' => 'bridgestone|potenza-sport|265/40R20|2026',
            'brand_name' => 'Bridgestone',
            'model_name' => 'Potenza Sport',
            'full_size' => '265/40R20',
            'dot_year' => '2026',
        ]);

        $ownOffer = TyreAccountOffer::create([
            'tyre_catalog_group_id' => $ownGroup->id,
            'account_id' => $account->id,
            'source_sku' => 'SUP-001',
            'retail_price' => 500,
        ]);

        $supplierOnlyOffer = TyreAccountOffer::create([
            'tyre_catalog_group_id' => $supplierOnlyGroup->id,
            'account_id' => $supplier->id,
            'source_sku' => 'OTHER-001',
            'retail_price' => 480,
        ]);

        $previewWarehouse = $this->createWarehouse('Preview Warehouse', 'PREVIEW');
        $this->seedOfferInventory($ownOffer, $previewWarehouse, 2);
        $this->seedOfferInventory($supplierOnlyOffer, $previewWarehouse, 6);

        $token = $owner->createToken('storefront-catalog')->plainTextToken;

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/storefront/catalog/tyres?mode=supplier-preview');

        $response
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.meta.mode', 'supplier-preview')
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.brand', 'Pirelli')
            ->assertJsonPath('data.items.0.availability.origin', 'own')
            ->assertJsonPath('data.items.0.availability.quantity', 2)
            ->assertJsonPath('data.items.0.availability.show_quantity', true)
            ->assertJsonPath('data.items.0.mode_availability.retail_store', false)
            ->assertJsonPath('data.items.0.mode_availability.supplier_preview', true);
    }

    public function test_product_detail_returns_size_options_for_visible_sibling_groups(): void
    {
        [$owner, $account] = $this->createOwnerAccount('Alpha Retail', 'alpha-retail', AccountType::BOTH, true, true);
        $supplier = $this->createStandaloneAccount('Bravo Supply', 'bravo-supply', AccountType::SUPPLIER, false, true);

        AccountConnection::create([
            'retailer_account_id' => $account->id,
            'supplier_account_id' => $supplier->id,
            'status' => AccountConnectionStatus::APPROVED,
            'approved_at' => now(),
        ]);

        $first = TyreCatalogGroup::create([
            'storefront_merge_key' => 'michelin|pilot-sport-4s|245/35R20|2026',
            'brand_name' => 'Michelin',
            'model_name' => 'Pilot Sport 4S',
            'full_size' => '245/35R20',
            'dot_year' => '2026',
            'load_index' => '95',
            'speed_rating' => 'Y',
            'tyre_type' => 'Summer',
            'country' => 'France',
            'runflat' => false,
            'rfid' => true,
        ]);

        $second = TyreCatalogGroup::create([
            'storefront_merge_key' => 'michelin|pilot-sport-4s|255/35R20|2026',
            'brand_name' => 'Michelin',
            'model_name' => 'Pilot Sport 4S',
            'full_size' => '255/35R20',
            'dot_year' => '2026',
            'load_index' => '97',
            'speed_rating' => 'Y',
            'tyre_type' => 'Summer',
            'country' => 'France',
        ]);

        $firstOffer = TyreAccountOffer::create([
            'tyre_catalog_group_id' => $first->id,
            'account_id' => $account->id,
            'source_sku' => 'ALPHA-001',
            'retail_price' => 390,
            'product_image_1' => 'alpha-own.png',
        ]);

        $secondOffer = TyreAccountOffer::create([
            'tyre_catalog_group_id' => $second->id,
            'account_id' => $supplier->id,
            'source_sku' => 'BRAVO-002',
            'retail_price' => 360,
            'product_image_1' => 'bravo-option.png',
        ]);

        $primaryWarehouse = $this->createWarehouse('Detail Warehouse', 'DETAIL');
        $this->seedOfferInventory($firstOffer, $primaryWarehouse, 5);
        $this->seedOfferInventory($secondOffer, $primaryWarehouse, 2);

        $token = $owner->createToken('storefront-catalog')->plainTextToken;

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/storefront/catalog/tyres/michelin-pilot-sport-4s-245-35r20-2026?mode=retail-store');

        $response
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.product.slug', 'michelin-pilot-sport-4s-245-35r20-2026')
            ->assertJsonPath('data.product.brand', 'Michelin')
            ->assertJsonPath('data.product.availability.origin', 'own')
            ->assertJsonPath('data.product.availability.quantity', 5)
            ->assertJsonPath('data.product.availability.show_quantity', false)
            ->assertJsonPath('data.product.specifications.0.label', 'Size')
            ->assertJsonPath('data.product.options.0.size', '245/35R20')
            ->assertJsonPath('data.product.options.1.size', '255/35R20')
            ->assertJsonPath('data.product.options.1.availability.origin', 'supplier')
            ->assertJsonPath('data.product.options.1.availability.quantity', 2)
            ->assertJsonPath('data.product.options.1.availability.show_quantity', true)
            ->assertJsonPath('data.product.related_slugs.0', 'michelin-pilot-sport-4s-255-35r20-2026');
    }

    public function test_retail_store_catalog_applies_live_tyre_size_filters(): void
    {
        [$owner, $account] = $this->createOwnerAccount('Alpha Retail', 'alpha-retail', AccountType::BOTH, true, true);

        $matchingGroup = TyreCatalogGroup::create([
            'storefront_merge_key' => 'michelin|pilot-sport-4s|245/35R20|2026',
            'brand_name' => 'Michelin',
            'model_name' => 'Pilot Sport 4S',
            'width' => 245,
            'height' => 35,
            'rim_size' => 20,
            'full_size' => '245/35R20',
            'load_index' => '95',
            'speed_rating' => 'Y',
            'dot_year' => '2026',
            'tyre_type' => 'Summer',
        ]);

        $nonMatchingGroup = TyreCatalogGroup::create([
            'storefront_merge_key' => 'continental|sportcontact-7|255/40R20|2026',
            'brand_name' => 'Continental',
            'model_name' => 'SportContact 7',
            'width' => 255,
            'height' => 40,
            'rim_size' => 20,
            'full_size' => '255/40R20',
            'load_index' => '99',
            'speed_rating' => 'Y',
            'dot_year' => '2026',
            'tyre_type' => 'Summer',
        ]);

        $matchingOffer = TyreAccountOffer::create([
            'tyre_catalog_group_id' => $matchingGroup->id,
            'account_id' => $account->id,
            'source_sku' => 'ALPHA-245',
            'retail_price' => 390,
        ]);

        $nonMatchingOffer = TyreAccountOffer::create([
            'tyre_catalog_group_id' => $nonMatchingGroup->id,
            'account_id' => $account->id,
            'source_sku' => 'ALPHA-255',
            'retail_price' => 410,
        ]);

        $warehouse = $this->createWarehouse('Filter Warehouse', 'FILTER');
        $this->seedOfferInventory($matchingOffer, $warehouse, 4);
        $this->seedOfferInventory($nonMatchingOffer, $warehouse, 7);

        $token = $owner->createToken('storefront-catalog')->plainTextToken;

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/storefront/catalog/tyres?mode=retail-store&search_by_size=true&width=245&aspectRatio=35&rimSize=20&loadIndex=95&speedRating=Y&season=Summer');

        $response
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.brand', 'Michelin')
            ->assertJsonPath('data.items.0.size', '245/35R20')
            ->assertJsonPath('data.meta.filters.width', 245)
            ->assertJsonPath('data.meta.filters.height', 35)
            ->assertJsonPath('data.meta.filters.rim_size', 20)
            ->assertJsonPath('data.meta.filters.load_index', '95')
            ->assertJsonPath('data.meta.filters.speed_rating', 'Y')
            ->assertJsonPath('data.meta.filters.season', 'Summer');
    }

    public function test_retail_store_catalog_resolves_vehicle_search_into_live_tyre_filters(): void
    {
        config()->set('wheel_size.key', 'test-wheel-key');
        config()->set('wheel_size.url', 'https://api.wheel-size.com/v2/');

        [$owner, $account] = $this->createOwnerAccount('Alpha Retail', 'alpha-retail', AccountType::BOTH, true, true);

        $matchingGroup = TyreCatalogGroup::create([
            'storefront_merge_key' => 'michelin|pilot-sport-4s|245/35R20|2026',
            'brand_name' => 'Michelin',
            'model_name' => 'Pilot Sport 4S',
            'width' => 245,
            'height' => 35,
            'rim_size' => 20,
            'full_size' => '245/35R20',
            'load_index' => '95',
            'speed_rating' => 'Y',
            'dot_year' => '2026',
            'tyre_type' => 'Summer',
        ]);

        $nonMatchingGroup = TyreCatalogGroup::create([
            'storefront_merge_key' => 'continental|sportcontact-7|255/40R20|2026',
            'brand_name' => 'Continental',
            'model_name' => 'SportContact 7',
            'width' => 255,
            'height' => 40,
            'rim_size' => 20,
            'full_size' => '255/40R20',
            'load_index' => '99',
            'speed_rating' => 'Y',
            'dot_year' => '2026',
            'tyre_type' => 'Summer',
        ]);

        $matchingOffer = TyreAccountOffer::create([
            'tyre_catalog_group_id' => $matchingGroup->id,
            'account_id' => $account->id,
            'source_sku' => 'ALPHA-245',
            'retail_price' => 390,
        ]);

        $nonMatchingOffer = TyreAccountOffer::create([
            'tyre_catalog_group_id' => $nonMatchingGroup->id,
            'account_id' => $account->id,
            'source_sku' => 'ALPHA-255',
            'retail_price' => 410,
        ]);

        $warehouse = $this->createWarehouse('Vehicle Filter Warehouse', 'VEHICLE-FILTER');
        $this->seedOfferInventory($matchingOffer, $warehouse, 4);
        $this->seedOfferInventory($nonMatchingOffer, $warehouse, 7);

        Http::fake([
            'https://api.wheel-size.com/v2/search/by_model/*' => Http::response([
                'data' => [[
                    'wheels' => [[
                        'is_stock' => true,
                        'front' => [
                            'tire_width' => 245,
                            'tire_aspect_ratio' => 35,
                            'rim_diameter' => 20,
                            'load_index' => '95',
                            'speed_rating' => 'Y',
                        ],
                    ]],
                ]],
            ]),
        ]);

        $token = $owner->createToken('storefront-catalog')->plainTextToken;

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/storefront/catalog/tyres?mode=retail-store&search_by_vehicle=true&make=Audi&model=RS6&year=2024&variant=Performance');

        $response
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.brand', 'Michelin')
            ->assertJsonPath('data.items.0.size', '245/35R20')
            ->assertJsonPath('data.meta.filters.vehicle_requested', true)
            ->assertJsonPath('data.meta.filters.vehicle_resolved', true)
            ->assertJsonPath('data.meta.filters.vehicle_search.make', 'Audi')
            ->assertJsonPath('data.meta.filters.vehicle_search.model', 'RS6')
            ->assertJsonPath('data.meta.filters.vehicle_search.year', '2024')
            ->assertJsonPath('data.meta.filters.vehicle_search.modification', 'Performance')
            ->assertJsonPath('data.meta.filters.width', 245)
            ->assertJsonPath('data.meta.filters.height', 35)
            ->assertJsonPath('data.meta.filters.rim_size', 20)
            ->assertJsonPath('data.meta.filters.load_index', '95')
            ->assertJsonPath('data.meta.filters.speed_rating', 'Y');
    }

    private function createOwnerAccount(
        string $name,
        string $slug,
        AccountType $type,
        bool $retailEnabled,
        bool $wholesaleEnabled,
    ): array {
        $owner = User::factory()->create();
        $account = Account::create([
            'name' => $name,
            'slug' => $slug,
            'account_type' => $type,
            'retail_enabled' => $retailEnabled,
            'wholesale_enabled' => $wholesaleEnabled,
            'status' => AccountStatus::ACTIVE,
            'base_subscription_plan' => SubscriptionPlan::PREMIUM,
            'created_by_user_id' => $owner->id,
        ]);

        $account->users()->attach($owner->id, [
            'role' => AccountRole::OWNER->value,
            'is_default' => true,
        ]);

        return [$owner, $account];
    }

    private function createStandaloneAccount(
        string $name,
        string $slug,
        AccountType $type,
        bool $retailEnabled,
        bool $wholesaleEnabled,
    ): Account {
        return Account::create([
            'name' => $name,
            'slug' => $slug,
            'account_type' => $type,
            'retail_enabled' => $retailEnabled,
            'wholesale_enabled' => $wholesaleEnabled,
            'status' => AccountStatus::ACTIVE,
            'base_subscription_plan' => SubscriptionPlan::PREMIUM,
        ]);
    }

    private function createWarehouse(string $name, string $code): Warehouse
    {
        return Warehouse::create([
            'warehouse_name' => $name,
            'code' => $code,
            'is_primary' => true,
        ]);
    }

    private function seedOfferInventory(TyreAccountOffer $offer, Warehouse $warehouse, int $quantity): void
    {
        TyreOfferInventory::create([
            'tyre_account_offer_id' => $offer->id,
            'account_id' => $offer->account_id,
            'warehouse_id' => $warehouse->id,
            'quantity' => $quantity,
            'eta_qty' => 0,
        ]);

        $offer->forceFill([
            'inventory_status' => $quantity > 0 ? 'configured_in_stock' : 'configured_out_of_stock',
        ])->save();
    }
}
