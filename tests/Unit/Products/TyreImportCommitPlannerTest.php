<?php

namespace Tests\Unit\Products;

use App\Models\User;
use App\Modules\Accounts\Enums\AccountRole;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\SubscriptionPlan;
use App\Modules\Accounts\Models\Account;
use App\Modules\Products\Actions\StageTyreImportAction;
use App\Modules\Products\Models\TyreImportBatch;
use App\Modules\Products\Support\TyreImportCommitPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TyreImportCommitPlannerTest extends TestCase
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

    public function test_it_builds_create_and_merge_groups_using_only_the_same_account_history(): void
    {
        $owner = User::factory()->create();
        $account = $this->createAccount($owner, 'North Coast Tyres', 'north-coast-tyres');
        $otherAccount = $this->createAccount($owner, 'South Road Tyres', 'south-road-tyres');

        $this->stageCsvImport($account, $owner, 'previous.csv', implode("\n", [
            'SKU,Brand,Model,width,height,rim_size,full_size,load_index,speed_rating,DOT,Country,Type,Runflat,RFID,sidewall,warranty,Retail_price,wholesale_price_lvl1,wholesale_price_lvl2,wholesale_price_lvl3,brand_image,product_image_1,product_image_2,product_image_3',
            'PREV-001,Michelin,Pilot Sport 4S,245,35,20,245/35R20,103,Y,2026,Japan,Performance,NO,YES,Black,5 Years,1000,900,850,700,brand.png,p1.png,p2.png,p3.png',
        ]));

        $this->stageCsvImport($otherAccount, $owner, 'other-account.csv', implode("\n", [
            'SKU,Brand,Model,width,height,rim_size,full_size,load_index,speed_rating,DOT,Country,Type,Runflat,RFID,sidewall,warranty,Retail_price,wholesale_price_lvl1,wholesale_price_lvl2,wholesale_price_lvl3,brand_image,product_image_1,product_image_2,product_image_3',
            'OTHER-001,Continental,SportContact,225,45,17,225/45R17,94,Y,2026,Germany,Summer,YES,NO,Black,3 Years,700,650,620,600,brand.png,p1.png,p2.png,p3.png',
        ]));

        /** @var TyreImportBatch $currentBatch */
        $currentBatch = $this->stageCsvImport($account, $owner, 'current.csv', implode("\n", [
            'SKU,Brand,Model,width,height,rim_size,full_size,load_index,speed_rating,DOT,Country,Type,Runflat,RFID,sidewall,warranty,Retail_price,wholesale_price_lvl1,wholesale_price_lvl2,wholesale_price_lvl3,brand_image,product_image_1,product_image_2,product_image_3',
            'MERGE-001,Michelin,Pilot Sport 4S,245,35,20,245/35R20,103,Y,2026,Japan,Performance,NO,YES,Black,5 Years,1000,900,850,700,brand.png,p1.png,p2.png,p3.png',
            'CREATE-001,Continental,SportContact,225,45,17,225/45R17,94,Y,2026,Germany,Summer,YES,NO,Black,3 Years,700,650,620,600,brand.png,p1.png,p2.png,p3.png',
            'CREATE-002,Continental,SportContact,225,45,17,225/45R17,94,Y,2026,Germany,Summer,YES,NO,Black,3 Years,700,650,620,600,brand.png,p1.png,p2.png,p3.png',
        ]));

        $plan = app(TyreImportCommitPlanner::class)->plan($currentBatch);

        $this->assertSame('Planner compares only against prior staged rows for the same account. Other accounts never affect the result.', $plan['scope_note']);
        $this->assertSame(1, $plan['summary_cards'][0]['value']);
        $this->assertSame(1, $plan['summary_cards'][1]['value']);
        $this->assertSame(2, $plan['summary_cards'][2]['value']);
        $this->assertSame(1, $plan['summary_cards'][3]['value']);
        $this->assertCount(2, $plan['group_rows']);

        $mergeGroup = collect($plan['group_rows'])->firstWhere('action_key', 'merge_group');
        $createGroup = collect($plan['group_rows'])->firstWhere('action_key', 'create_group');

        $this->assertNotNull($mergeGroup);
        $this->assertNotNull($createGroup);
        $this->assertSame('Michelin', $mergeGroup['brand']);
        $this->assertSame(1, $mergeGroup['previous_match_count']);
        $this->assertSame('Continental', $createGroup['brand']);
        $this->assertSame(0, $createGroup['previous_match_count']);
        $this->assertSame([3], $createGroup['source_rows']);
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
            'is_default' => false,
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
