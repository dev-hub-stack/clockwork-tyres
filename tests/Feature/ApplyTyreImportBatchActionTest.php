<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Accounts\Enums\AccountRole;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\SubscriptionPlan;
use App\Modules\Accounts\Models\Account;
use App\Modules\Products\Actions\ApplyTyreImportBatchAction;
use App\Modules\Products\Actions\StageTyreImportAction;
use App\Modules\Products\Enums\TyreImportBatchStatus;
use App\Modules\Products\Models\TyreAccountOffer;
use App\Modules\Products\Models\TyreCatalogGroup;
use App\Modules\Products\Models\TyreImportBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ApplyTyreImportBatchActionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string>
     */
    private array $createdFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::firstOrCreate(['name' => 'view_products', 'guard_name' => 'web']);
    }

    protected function tearDown(): void
    {
        foreach ($this->createdFiles as $filePath) {
            if (is_file($filePath)) {
                @unlink($filePath);
            }
        }

        parent::tearDown();
    }

    public function test_it_applies_a_staged_batch_into_tyre_targets_and_is_idempotent(): void
    {
        $owner = User::factory()->create();
        $account = $this->createAccount($owner, 'North Coast Tyres', 'north-coast-tyres', true);

        $batch = $this->stageCsvImport($account, $owner, 'apply.csv', implode("\n", [
            'SKU,Brand,Model,width,height,rim_size,full_size,load_index,speed_rating,DOT,Country,Type,Runflat,RFID,sidewall,warranty,Retail_price,wholesale_price_lvl1,wholesale_price_lvl2,wholesale_price_lvl3,brand_image,product_image_1,product_image_2,product_image_3',
            'APPLY-001,Michelin,Pilot Sport 4S,245,35,20,245/35R20,103,Y,2026,Japan,Performance,YES,NO,Black,5 Years,1000,900,850,700,brand.png,p1.png,p2.png,p3.png',
        ]));

        $firstCounts = app(ApplyTyreImportBatchAction::class)->execute($batch, $owner);

        $batch->refresh();

        $this->assertSame([
            'groups_created' => 1,
            'groups_updated' => 0,
            'offers_created' => 1,
            'offers_updated' => 0,
        ], $firstCounts);
        $this->assertSame(TyreImportBatchStatus::APPLIED, $batch->status);
        $this->assertNotNull($batch->applied_at);
        $this->assertSame($owner->id, $batch->applied_by_user_id);
        $this->assertDatabaseCount('tyre_catalog_groups', 1);
        $this->assertDatabaseCount('tyre_account_offers', 1);

        $group = TyreCatalogGroup::query()->firstOrFail();
        $offer = TyreAccountOffer::query()->firstOrFail();

        $this->assertSame('245/35R20', $group->full_size);
        $this->assertSame($group->id, $offer->tyre_catalog_group_id);
        $this->assertSame('blocked_storage_resolution', $offer->media_status);
        $this->assertSame('blocked_warehouse_mapping', $offer->inventory_status);

        $secondCounts = app(ApplyTyreImportBatchAction::class)->execute($batch->fresh(), $owner);

        $this->assertSame([
            'groups_created' => 0,
            'groups_updated' => 1,
            'offers_created' => 0,
            'offers_updated' => 1,
        ], $secondCounts);
        $this->assertDatabaseCount('tyre_catalog_groups', 1);
        $this->assertDatabaseCount('tyre_account_offers', 1);
    }

    public function test_apply_route_is_scoped_to_the_active_account(): void
    {
        $user = User::factory()->create();
        $account = $this->createAccount($user, 'North Coast Tyres', 'north-coast-tyres', true);
        $otherAccount = $this->createAccount($user, 'South Road Tyres', 'south-road-tyres', false);
        $user->givePermissionTo('view_products');

        $otherBatch = $this->stageCsvImport($otherAccount, $user, 'other.csv', implode("\n", [
            'SKU,Brand,Model,width,height,rim_size,full_size,load_index,speed_rating,DOT,Country,Type,Runflat,RFID,sidewall,warranty,Retail_price,wholesale_price_lvl1,wholesale_price_lvl2,wholesale_price_lvl3,brand_image,product_image_1,product_image_2,product_image_3',
            'OTHER-001,Continental,SportContact,225,45,17,225/45R17,94,Y,2026,Germany,Summer,NO,NO,Black,2 Years,700,650,620,600,,,,',
        ]));

        $this->actingAs($user)
            ->post('/admin/tyre-grid/apply', [
                'batch_id' => $otherBatch->id,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('apply_batch');

        $this->assertSame($account->id, $user->accounts()->orderByRaw('CASE WHEN account_user.is_default = 1 THEN 0 ELSE 1 END')->first()->id);
        $this->assertDatabaseCount('tyre_catalog_groups', 0);
        $this->assertDatabaseCount('tyre_account_offers', 0);
    }

    private function createAccount(User $user, string $name, string $slug, bool $isDefault): Account
    {
        $account = Account::create([
            'name' => $name,
            'slug' => $slug,
            'account_type' => AccountType::SUPPLIER,
            'retail_enabled' => false,
            'wholesale_enabled' => true,
            'status' => AccountStatus::ACTIVE,
            'base_subscription_plan' => SubscriptionPlan::PREMIUM,
            'created_by_user_id' => $user->id,
        ]);

        $account->users()->attach($user->id, [
            'role' => AccountRole::OWNER->value,
            'is_default' => $isDefault,
        ]);

        return $account;
    }

    private function stageCsvImport(Account $account, User $user, string $fileName, string $contents): TyreImportBatch
    {
        $filePath = storage_path('app/testing-imports/'.Str::uuid().'.csv');
        $this->ensureDirectory(dirname($filePath));
        file_put_contents($filePath, $contents);
        $this->createdFiles[] = $filePath;

        return app(StageTyreImportAction::class)->execute(
            account: $account,
            filePath: $filePath,
            uploadedBy: $user,
            originalFileName: $fileName,
        );
    }

    private function ensureDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
    }
}
