<?php

namespace Tests\Feature;

use App\Filament\Pages\TyresGrid;
use App\Models\User;
use App\Modules\Accounts\Enums\AccountRole;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\SubscriptionPlan;
use App\Modules\Accounts\Models\Account;
use App\Modules\Products\Actions\StageTyreImportAction;
use App\Modules\Products\Models\TyreImportBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TyresGridTest extends TestCase
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

    public function test_tyre_grid_shows_the_latest_staged_preview_for_the_active_account(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view_products');

        $activeAccount = $this->createAccount($user, 'North Coast Tyres', 'north-coast-tyres', true);
        $secondaryAccount = $this->createAccount($user, 'South Road Tyres', 'south-road-tyres', false);

        $this->stageCsvImport($activeAccount, $user, 'previous-supplier.csv', implode("\n", [
            'SKU,Brand,Model,width,height,rim_size,full_size,load_index,speed_rating,DOT,Country,Type,Runflat,RFID,sidewall,warranty,Retail_price,wholesale_price_lvl1,wholesale_price_lvl2,wholesale_price_lvl3,brand_image,product_image_1,product_image_2,product_image_3',
            'PREVIOUS-001,Michelin,Pilot Sport 4S,245,35,20,245/35R20,103,Y,2026,Japan,Performance,NO,YES,Black,5 Years,1000,900,850,700,brand.png,p1.png,p2.png,p3.png',
        ]));

        $this->stageCsvImport($activeAccount, $user, 'active-supplier.csv', implode("\n", [
            'SKU,Brand,Model,width,height,rim_size,full_size,load_index,speed_rating,DOT,Country,Type,Runflat,RFID,sidewall,warranty,Retail_price,wholesale_price_lvl1,wholesale_price_lvl2,wholesale_price_lvl3,brand_image,product_image_1,product_image_2,product_image_3',
            'ACTIVE-001,Michelin,Pilot Sport 4S,245,35,20,245/35R20,103,Y,2026,Japan,Performance,NO,YES,Black,5 Years,1000,900,850,700,brand.png,p1.png,p2.png,p3.png',
            'ACTIVE-NEW-001,Continental,SportContact,225,45,17,225/45R17,94,Y,2026,Germany,Summer,YES,NO,Black,3 Years,700,650,620,600,brand.png,p1.png,p2.png,p3.png',
        ]));

        $this->stageCsvImport($secondaryAccount, $user, 'secondary-supplier.csv', implode("\n", [
            'SKU,Brand,Model,width,height,rim_size,full_size,load_index,speed_rating,DOT,Country,Type,Runflat,RFID,sidewall,warranty,Retail_price,wholesale_price_lvl1,wholesale_price_lvl2,wholesale_price_lvl3,brand_image,product_image_1,product_image_2,product_image_3',
            'SECONDARY-001,Pirelli,P Zero,275,35,21,275/35R21,103,Y,2025,Italy,Summer,YES,NO,Black,3 Years,1500,1400,1350,1300,brand.png,p1.png,p2.png,p3.png',
        ]));

        $this->actingAs($user)
            ->get('/admin/tyre-grid')
            ->assertOk()
            ->assertSee('Tyre import staging')
            ->assertSee('Commit planner preview')
            ->assertSee('North Coast Tyres')
            ->assertSee('active-supplier.csv')
            ->assertSee('Create new tyre group')
            ->assertSee('Merge into existing staged group')
            ->assertDontSee('secondary-supplier.csv');

        $page = app(TyresGrid::class);
        $page->mount();

        $this->assertSame('North Coast Tyres', $page->current_account_summary['name']);
        $this->assertSame('active-supplier.csv', $page->latest_import_batch['file_name']);
        $this->assertCount(2, $page->tyres_data);
        $this->assertSame('245/35R20', $page->tyres_data[0]['full_size']);
        $this->assertSame('NO', $page->tyres_data[0]['runflat']);
        $this->assertSame(2, $page->import_summary_cards[1]['value']);
        $this->assertSame(1, $page->commit_plan_summary_cards[0]['value']);
        $this->assertSame(1, $page->commit_plan_summary_cards[1]['value']);
    }

    public function test_tyre_grid_import_route_stages_a_csv_batch_for_the_active_account(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view_products');

        $account = $this->createAccount($user, 'North Coast Tyres', 'north-coast-tyres', true);

        $response = $this->actingAs($user)->post('/admin/tyre-grid/import', [
            'import_file' => UploadedFile::fake()->createWithContent('supplier.csv', implode("\n", [
                'SKU,Brand,Model,width,height,rim_size,full_size,load_index,speed_rating,DOT,Country,Type,Runflat,RFID,sidewall,warranty,Retail_price,wholesale_price_lvl1,wholesale_price_lvl2,wholesale_price_lvl3,brand_image,product_image_1,product_image_2,product_image_3',
                'CSV-001,Continental,SportContact,225,45,17,225/45R17,94,Y,2026,Germany,Summer,YES,NO,Black,2 Years,700,650,620,600,brand.png,p1.png,p2.png,p3.png',
            ])),
        ]);

        $response
            ->assertRedirect()
            ->assertSessionHas('tyre_import_status');

        $this->assertDatabaseCount('tyre_import_batches', 1);

        /** @var TyreImportBatch $batch */
        $batch = TyreImportBatch::query()->firstOrFail();

        $this->assertSame($account->id, $batch->account_id);
        $this->assertSame('supplier.csv', $batch->source_file_name);
        $this->assertSame('csv', $batch->source_format);
        $this->assertSame(1, $batch->valid_rows);

        $this->actingAs($user)
            ->get('/admin/tyre-grid')
            ->assertOk()
            ->assertSee('supplier.csv')
            ->assertSee('Valid rows');
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

    private function stageCsvImport(Account $account, User $user, string $fileName, string $contents): void
    {
        $filePath = storage_path('app/testing-imports/'.Str::uuid().'.csv');
        $this->ensureDirectory(dirname($filePath));
        file_put_contents($filePath, $contents);
        $this->createdFiles[] = $filePath;

        app(StageTyreImportAction::class)->execute(
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
