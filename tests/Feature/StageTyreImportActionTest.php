<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Accounts\Enums\AccountRole;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\SubscriptionPlan;
use App\Modules\Accounts\Models\Account;
use App\Modules\Products\Actions\StageTyreImportAction;
use App\Modules\Products\Enums\TyreImportBatchStatus;
use App\Modules\Products\Enums\TyreImportRowStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class StageTyreImportActionTest extends TestCase
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

    public function test_it_stages_xlsx_rows_and_flags_duplicate_merge_keys_ignoring_sku(): void
    {
        $owner = User::factory()->create();
        $account = $this->createAccount($owner, 'Alpha Tyres', 'alpha-tyres');
        $filePath = $this->createXlsxFile([
            ['SKU', 'Brand', 'Model', 'width', 'height', 'rim_size', 'full_size', 'load_index', 'speed_rating', 'DOT', 'Country', 'Type', 'Runflat', 'RFID', 'sidewall', 'warranty', 'Retail_price', 'wholesale_price_lvl1', 'wholesale_price_lvl2', 'wholesale_price_lvl3', 'brand_image', 'product_image_1', 'product_image_2', 'product_image_3'],
            ['SUP-001', 'Michelin', 'Pilot Sport 4S', 245, 30, 20, '245/35R20', 118, 's', '2026', 'Japan', 'Performance', 'NO', 'YES', 'Black', '5 Years', 1000, 900, 850, 700, 'brand.png', 'p1.png', 'p2.png', 'p3.png'],
            ['SUP-XYZ-ALT', 'Michelin', 'Pilot Sport 4S', 245, 30, 20, '245/35R20', 118, 's', '2026', 'Japan', 'Performance', 'NO', 'YES', 'Black', '5 Years', 1000, 900, 850, 700, 'brand.png', 'p1.png', 'p2.png', 'p3.png'],
        ]);

        $batch = app(StageTyreImportAction::class)->execute(
            account: $account,
            filePath: $filePath,
            uploadedBy: $owner,
            originalFileName: 'supplier-tyres.xlsx',
        );

        $this->assertSame(TyreImportBatchStatus::STAGED, $batch->status);
        $this->assertSame('xlsx', $batch->source_format);
        $this->assertSame(2, $batch->total_rows);
        $this->assertSame(2, $batch->staged_rows);
        $this->assertSame(1, $batch->valid_rows);
        $this->assertSame(1, $batch->duplicate_rows);
        $this->assertSame(0, $batch->invalid_rows);

        $rows = $batch->rows()->orderBy('source_row_number')->get()->values();

        $this->assertCount(2, $rows);
        $this->assertSame(TyreImportRowStatus::VALID, $rows[0]->status);
        $this->assertSame(TyreImportRowStatus::DUPLICATE, $rows[1]->status);
        $this->assertSame($rows[0]->id, $rows[1]->duplicate_of_row_id);
        $this->assertSame($rows[0]->storefront_merge_key, $rows[1]->storefront_merge_key);
        $this->assertSame('MICHELIN', $rows[0]->normalized_brand);
        $this->assertSame('PILOT SPORT 4S', $rows[0]->normalized_model);
        $this->assertSame('245/30R20', $rows[0]->normalized_full_size);
        $this->assertSame('2026', $rows[0]->normalized_dot_year);
        $this->assertTrue(in_array(
            'The full_size value does not match the numeric dimensions; canonical size was used for grouping.',
            $rows[0]->validation_warnings,
            true
        ));
    }

    public function test_it_stages_csv_rows_and_normalizes_boolean_like_fields(): void
    {
        $owner = User::factory()->create();
        $account = $this->createAccount($owner, 'Bravo Tyres', 'bravo-tyres');
        $filePath = $this->createCsvFile(implode("\n", [
            "\xEF\xBB\xBFSKU,Brand,Model,width,height,rim_size,full_size,load_index,speed_rating,DOT,Country,Type,Runflat,RFID,sidewall,warranty,Retail_price,wholesale_price_lvl1,wholesale_price_lvl2,wholesale_price_lvl3,brand_image,product_image_1,product_image_2,product_image_3",
            'CSV-001,Pirelli,P Zero,275,35,21,275/35R21,103,Y,2025,Italy,Summer,YES,NO,Black,3 Years,1500,1400,1350,1300,brand.png,p1.png,p2.png,p3.png',
        ]));

        $batch = app(StageTyreImportAction::class)->execute(
            account: $account,
            filePath: $filePath,
            uploadedBy: $owner,
            originalFileName: 'supplier-tyres.csv',
        );

        $this->assertSame(TyreImportBatchStatus::STAGED, $batch->status);
        $this->assertSame('csv', $batch->source_format);
        $this->assertSame(1, $batch->valid_rows);
        $this->assertSame(0, $batch->invalid_rows);

        $row = $batch->rows()->firstOrFail();

        $this->assertSame(TyreImportRowStatus::VALID, $row->status);
        $this->assertSame(true, $row->normalized_payload['runflat']);
        $this->assertSame(false, $row->normalized_payload['rfid']);
        $this->assertSame('275/35R21', $row->normalized_payload['canonical_size']);
        $this->assertSame('2025', $row->normalized_dot_year);
    }

    public function test_it_marks_a_batch_invalid_when_required_headers_are_missing(): void
    {
        $owner = User::factory()->create();
        $account = $this->createAccount($owner, 'Charlie Tyres', 'charlie-tyres');
        $filePath = $this->createCsvFile(implode("\n", [
            'SKU,Brand,Model,width,height,rim_size,full_size,load_index,speed_rating',
            'BROKEN-001,Michelin,Pilot Sport,245,30,20,245/30R20,118,Y',
        ]));

        $batch = app(StageTyreImportAction::class)->execute(
            account: $account,
            filePath: $filePath,
            uploadedBy: $owner,
            originalFileName: 'broken-tyres.csv',
        );

        $this->assertSame(TyreImportBatchStatus::INVALID_HEADERS, $batch->status);
        $this->assertSame(0, $batch->staged_rows);
        $this->assertDatabaseCount('tyre_import_rows', 0);
        $this->assertContains('retail_price', $batch->validation_summary['headers']['missing_required_fields']);
        $this->assertContains('wholesale_price_lvl3', $batch->validation_summary['headers']['missing_required_fields']);
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

    /**
     * @param  array<int, array<int, string|int|float>>  $rows
     */
    private function createXlsxFile(array $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $columnIndex => $value) {
                $sheet->setCellValueByColumnAndRow($columnIndex + 1, $rowIndex + 1, $value);
            }
        }

        $filePath = storage_path('app/testing-imports/'.Str::uuid().'.xlsx');
        $this->ensureDirectory(dirname($filePath));

        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);
        $spreadsheet->disconnectWorksheets();

        $this->createdFiles[] = $filePath;

        return $filePath;
    }

    private function createCsvFile(string $contents): string
    {
        $filePath = storage_path('app/testing-imports/'.Str::uuid().'.csv');
        $this->ensureDirectory(dirname($filePath));
        file_put_contents($filePath, $contents);
        $this->createdFiles[] = $filePath;

        return $filePath;
    }

    private function ensureDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
    }
}
