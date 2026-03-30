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
use App\Modules\Products\Models\TyreAccountOffer;
use App\Modules\Products\Models\TyreCatalogGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        TyreAccountOffer::create([
            'tyre_catalog_group_id' => $ownFirstGroup->id,
            'account_id' => $account->id,
            'source_sku' => 'ALPHA-001',
            'retail_price' => 390,
            'product_image_1' => 'alpha-own.png',
        ]);

        TyreAccountOffer::create([
            'tyre_catalog_group_id' => $ownFirstGroup->id,
            'account_id' => $supplier->id,
            'source_sku' => 'BRAVO-001',
            'retail_price' => 360,
            'product_image_1' => 'bravo-supplier.png',
        ]);

        TyreAccountOffer::create([
            'tyre_catalog_group_id' => $supplierOnlyGroup->id,
            'account_id' => $supplier->id,
            'source_sku' => 'BRAVO-002',
            'retail_price' => 340,
            'product_image_1' => 'bravo-only.png',
        ]);

        TyreAccountOffer::create([
            'tyre_catalog_group_id' => $hiddenGroup->id,
            'account_id' => $hiddenSupplier->id,
            'source_sku' => 'HIDDEN-001',
            'retail_price' => 310,
        ]);

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
            ->assertJsonPath('data.items.0.availability.supplier_count', 1)
            ->assertJsonPath('data.items.0.price', 390)
            ->assertJsonPath('data.items.1.brand', 'Continental')
            ->assertJsonPath('data.items.1.availability.origin', 'supplier')
            ->assertJsonPath('data.items.1.availability.label', 'available')
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

        TyreAccountOffer::create([
            'tyre_catalog_group_id' => $ownGroup->id,
            'account_id' => $account->id,
            'source_sku' => 'SUP-001',
            'retail_price' => 500,
        ]);

        TyreAccountOffer::create([
            'tyre_catalog_group_id' => $supplierOnlyGroup->id,
            'account_id' => $supplier->id,
            'source_sku' => 'OTHER-001',
            'retail_price' => 480,
        ]);

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

        TyreAccountOffer::create([
            'tyre_catalog_group_id' => $first->id,
            'account_id' => $account->id,
            'source_sku' => 'ALPHA-001',
            'retail_price' => 390,
            'product_image_1' => 'alpha-own.png',
        ]);

        TyreAccountOffer::create([
            'tyre_catalog_group_id' => $second->id,
            'account_id' => $supplier->id,
            'source_sku' => 'BRAVO-002',
            'retail_price' => 360,
            'product_image_1' => 'bravo-option.png',
        ]);

        $token = $owner->createToken('storefront-catalog')->plainTextToken;

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/storefront/catalog/tyres/michelin-pilot-sport-4s-245-35r20-2026?mode=retail-store');

        $response
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.product.slug', 'michelin-pilot-sport-4s-245-35r20-2026')
            ->assertJsonPath('data.product.brand', 'Michelin')
            ->assertJsonPath('data.product.specifications.0.label', 'Size')
            ->assertJsonPath('data.product.options.0.size', '245/35R20')
            ->assertJsonPath('data.product.options.1.size', '255/35R20')
            ->assertJsonPath('data.product.related_slugs.0', 'michelin-pilot-sport-4s-255-35r20-2026');
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
}
