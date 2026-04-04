<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Accounts\Enums\AccountRole;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\SubscriptionPlan;
use App\Modules\Accounts\Models\Account;
use App\Modules\Products\Models\TyreAccountOffer;
use App\Modules\Products\Models\TyreCatalogGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TyreImageControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['view_products', 'edit_products'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    }

    public function test_operational_user_can_view_tyre_images_for_current_account(): void
    {
        [$user, $account] = $this->createOperationalUser();

        $visibleGroup = TyreCatalogGroup::query()->create([
            'storefront_merge_key' => 'michelin-pilot-sport-4s-245-35r20-2026',
            'brand_name' => 'Michelin',
            'model_name' => 'Pilot Sport 4S',
            'width' => 245,
            'height' => 35,
            'rim_size' => 20,
            'full_size' => '245/35R20',
            'dot_year' => '2026',
        ]);

        TyreAccountOffer::query()->create([
            'tyre_catalog_group_id' => $visibleGroup->id,
            'account_id' => $account->id,
            'source_sku' => 'TYR-PS4S-001',
            'brand_image' => 'tyres/brand/michelin.png',
        ]);

        $otherAccount = Account::query()->create([
            'name' => 'Hidden Supply',
            'slug' => 'hidden-supply',
            'account_type' => AccountType::SUPPLIER,
            'retail_enabled' => false,
            'wholesale_enabled' => true,
            'status' => AccountStatus::ACTIVE,
            'base_subscription_plan' => SubscriptionPlan::BASIC,
            'reports_subscription_enabled' => false,
            'created_by_user_id' => $user->id,
        ]);

        $hiddenGroup = TyreCatalogGroup::query()->create([
            'storefront_merge_key' => 'pirelli-p-zero-285-45r21-2026',
            'brand_name' => 'Pirelli',
            'model_name' => 'P Zero',
            'width' => 285,
            'height' => 45,
            'rim_size' => 21,
            'full_size' => '285/45R21',
            'dot_year' => '2026',
        ]);

        TyreAccountOffer::query()->create([
            'tyre_catalog_group_id' => $hiddenGroup->id,
            'account_id' => $otherAccount->id,
            'source_sku' => 'TYR-PZERO-999',
        ]);

        $this->actingAs($user)
            ->get('/admin/tyres/images?account_id='.$account->id)
            ->assertOk()
            ->assertSee('Tyre Images')
            ->assertSee('TYR-PS4S-001')
            ->assertSee('Pilot Sport 4S')
            ->assertDontSee('TYR-PZERO-999');
    }

    public function test_operational_user_can_upload_tyre_image_through_edit_screen(): void
    {
        Storage::fake('s3');

        [$user, $account] = $this->createOperationalUser();

        $group = TyreCatalogGroup::query()->create([
            'storefront_merge_key' => 'continental-sport-contact-7-255-35r19-2026',
            'brand_name' => 'Continental',
            'model_name' => 'SportContact 7',
            'width' => 255,
            'height' => 35,
            'rim_size' => 19,
            'full_size' => '255/35R19',
            'dot_year' => '2026',
        ]);

        $offer = TyreAccountOffer::query()->create([
            'tyre_catalog_group_id' => $group->id,
            'account_id' => $account->id,
            'source_sku' => 'TYR-CS7-255',
        ]);

        $this->actingAs($user)
            ->put('/admin/tyres/images/'.$offer->id.'?account_id='.$account->id, [
                'product_image_1' => UploadedFile::fake()->image('sport-contact-7.jpg', 200, 200),
            ])
            ->assertRedirect(route('admin.tyres.images.index'));

        $offer->refresh();

        $this->assertNotNull($offer->product_image_1);
        $this->assertStringStartsWith('tyres/', $offer->product_image_1);
        Storage::disk('s3')->assertExists($offer->product_image_1);
    }

    public function test_super_admin_is_forbidden_from_tyre_images_screen(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');
        $superAdmin->givePermissionTo(['view_products', 'edit_products']);

        $this->actingAs($superAdmin)
            ->get('/admin/tyres/images')
            ->assertForbidden();
    }

    /**
     * @return array{0: User, 1: Account}
     */
    private function createOperationalUser(): array
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['view_products', 'edit_products']);

        $account = Account::query()->create([
            'name' => 'Desert Drift Tyres LLC',
            'slug' => 'desert-drift-tyres',
            'account_type' => AccountType::RETAILER,
            'retail_enabled' => true,
            'wholesale_enabled' => false,
            'status' => AccountStatus::ACTIVE,
            'base_subscription_plan' => SubscriptionPlan::PREMIUM,
            'reports_subscription_enabled' => false,
            'created_by_user_id' => $user->id,
        ]);

        $user->accounts()->attach($account->id, [
            'role' => AccountRole::OWNER->value,
            'is_default' => true,
        ]);

        return [$user, $account];
    }
}
