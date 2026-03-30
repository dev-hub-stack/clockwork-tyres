<?php

namespace Tests\Unit\Products;

use App\Models\User;
use App\Modules\Accounts\Enums\AccountRole;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\SubscriptionPlan;
use App\Modules\Accounts\Models\Account;
use App\Modules\Products\Actions\StageTyreImportAction;
use App\Modules\Products\Models\Brand;
use App\Modules\Products\Models\ProductModel;
use App\Modules\Products\Models\TyreImportBatch;
use App\Modules\Products\Support\TyreImportTargetMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TyreImportTargetMapperTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string>
     */
    private array $createdFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->createdFiles as $filePath) {
            if (is_file($filePath)) {
                @unlink($filePath);
            }
        }

        parent::tearDown();
    }

    public function test_it_maps_valid_staged_rows_into_dedicated_tyre_targets(): void
    {
        $owner = User::factory()->create();
        $account = $this->createAccount($owner, 'North Coast Tyres', 'north-coast-tyres');

        Brand::query()->create([
            'name' => 'Michelin',
            'slug' => 'michelin',
            'status' => 1,
        ]);

        ProductModel::query()->create([
            'name' => 'Pilot Sport 4S',
            'image' => null,
        ]);

        $batch = $this->stageCsvImport($account, $owner, 'mapped.csv', implode("\n", [
            'SKU,Brand,Model,width,height,rim_size,full_size,load_index,speed_rating,DOT,Country,Type,Runflat,RFID,sidewall,warranty,Retail_price,wholesale_price_lvl1,wholesale_price_lvl2,wholesale_price_lvl3,brand_image,product_image_1,product_image_2,product_image_3',
            'MAP-001,Michelin,Pilot Sport 4S,245,30,20,245/35R20,118,s,2026,Japan,Performance,YES,NO,Black,5 Years,1000,900,850,700,brand.png,p1.png,p2.png,p3.png',
        ]));

        $mapped = app(TyreImportTargetMapper::class)->map($batch);

        $this->assertSame(['tyre_catalog_groups', 'tyre_account_offers'], $mapped['target_tables']);
        $this->assertSame(1, $mapped['summary_cards'][0]['value']);
        $this->assertSame(1, $mapped['summary_cards'][1]['value']);
        $this->assertSame(1, count($mapped['group_targets']));

        $groupTarget = $mapped['group_targets'][0];
        $catalogTarget = $groupTarget['catalog_group_target'];
        $offerTarget = $groupTarget['offer_targets'][0];

        $this->assertSame('tyre_catalog_groups', $catalogTarget['target_table']);
        $this->assertSame('245/30R20', $catalogTarget['full_size']);
        $this->assertSame('S', $catalogTarget['speed_rating']);
        $this->assertSame(true, $catalogTarget['runflat']);
        $this->assertSame(false, $catalogTarget['rfid']);
        $this->assertSame('existing', $catalogTarget['reference_resolution']['brand']['status']);
        $this->assertSame('existing_global_model', $catalogTarget['reference_resolution']['model']['status']);

        $this->assertSame('tyre_account_offers', $offerTarget['target_table']);
        $this->assertSame('MAP-001', $offerTarget['source_sku']);
        $this->assertSame(1000, $offerTarget['retail_price']);
        $this->assertSame(900, $offerTarget['wholesale_price_lvl1']);
        $this->assertSame('blocked_storage_resolution', $offerTarget['media_status']);
        $this->assertSame('blocked_warehouse_mapping', $offerTarget['inventory_status']);
    }

    public function test_it_flags_proposed_references_and_blocked_side_effect_targets(): void
    {
        $owner = User::factory()->create();
        $account = $this->createAccount($owner, 'South Road Tyres', 'south-road-tyres');

        $batch = $this->stageCsvImport($account, $owner, 'proposed.csv', implode("\n", [
            'SKU,Brand,Model,width,height,rim_size,full_size,load_index,speed_rating,DOT,Country,Type,Runflat,RFID,sidewall,warranty,Retail_price,wholesale_price_lvl1,wholesale_price_lvl2,wholesale_price_lvl3,brand_image,product_image_1,product_image_2,product_image_3',
            'PROP-001,Atlas,StreetGrip,225,45,17,225/45R17,94,Y,2026,China,All Season,NO,NO,Black,2 Years,700,650,620,600,,,,',
        ]));

        $mapped = app(TyreImportTargetMapper::class)->map($batch);

        $groupTarget = $mapped['group_targets'][0]['catalog_group_target'];
        $offerTarget = $mapped['group_targets'][0]['offer_targets'][0];

        $this->assertSame('proposed', $groupTarget['reference_resolution']['brand']['status']);
        $this->assertSame('proposed_global_model', $groupTarget['reference_resolution']['model']['status']);
        $this->assertSame('not_provided', $offerTarget['media_status']);
        $this->assertSame('blocked_warehouse_mapping', $offerTarget['inventory_status']);
        $this->assertSame(0, $mapped['summary_cards'][1]['value']);
        $this->assertSame(1, $mapped['summary_cards'][3]['value']);
    }

    private function createAccount(User $user, string $name, string $slug): Account
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
            'is_default' => true,
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
