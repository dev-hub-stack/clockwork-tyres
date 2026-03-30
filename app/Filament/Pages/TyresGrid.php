<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Support\CurrentAccountResolver;
use App\Modules\Products\Models\TyreImportBatch;
use App\Modules\Products\Models\TyreImportRow;
use App\Modules\Products\Support\CatalogCategoryRegistry;
use App\Modules\Products\Support\TyreCatalogContract;
use App\Modules\Products\Support\TyreGridLayout;
use App\Modules\Products\Support\TyreImportCommitPlanner;
use App\Modules\Products\Support\TyreImportTargetMapper;
use BackedEnum;
use Filament\Pages\Page;
use Throwable;
use UnitEnum;

class TyresGrid extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-table-cells';

    protected static UnitEnum|string|null $navigationGroup = 'Tyres';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Tyres Grid';

    protected static ?string $slug = 'tyre-grid';

    protected string $view = 'filament.pages.tyres-grid';

    public array $tyres_data = [];

    public array $category_definition = [];

    public array $pricing_levels = [];

    public array $launch_notes = [];

    public array $grid_columns = [];

    public array $toolbar_actions = [];

    public array $current_account_summary = [];

    public array $latest_import_batch = [];

    public array $import_summary_cards = [];

    public array $import_issue_rows = [];

    public array $commit_plan_summary_cards = [];

    public array $commit_plan_groups = [];

    public string $commit_plan_scope_note = '';

    public array $target_mapping_summary_cards = [];

    public array $target_mapping_groups = [];

    public string $target_mapping_scope_note = '';

    public function mount(): void
    {
        $this->category_definition = CatalogCategoryRegistry::definition(CatalogCategoryRegistry::TYRES) ?? [];

        $blueprint = TyreCatalogContract::blueprint();
        $this->pricing_levels = $blueprint['pricing_levels'] ?? [];
        $this->launch_notes = $blueprint['launch_notes'] ?? [];
        $this->grid_columns = TyreGridLayout::columns();
        $this->toolbar_actions = TyreGridLayout::toolbarActions();

        $this->loadTyresData();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_products') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->can('view_products') ?? false;
    }

    protected function loadTyresData(): void
    {
        $currentAccount = $this->resolveCurrentAccount();
        $this->current_account_summary = $this->buildCurrentAccountSummary($currentAccount);

        if (! $currentAccount instanceof Account) {
            $this->resetImportPreview();

            return;
        }

        $latestBatch = $this->latestBatchFor($currentAccount);

        if (! $latestBatch instanceof TyreImportBatch) {
            $this->resetImportPreview();

            return;
        }

        $commitPlan = app(TyreImportCommitPlanner::class)->plan($latestBatch);
        $targetMapping = app(TyreImportTargetMapper::class)->map($latestBatch);

        $this->latest_import_batch = $this->buildLatestImportBatchPayload($latestBatch);
        $this->import_summary_cards = $this->buildImportSummaryCards($latestBatch);
        $this->import_issue_rows = $this->buildImportIssueRows($latestBatch);
        $this->commit_plan_summary_cards = $commitPlan['summary_cards'];
        $this->commit_plan_groups = $commitPlan['group_rows'];
        $this->commit_plan_scope_note = $commitPlan['scope_note'];
        $this->target_mapping_summary_cards = $targetMapping['summary_cards'];
        $this->target_mapping_groups = $targetMapping['group_targets'];
        $this->target_mapping_scope_note = $targetMapping['scope_note'];
        $this->tyres_data = $latestBatch->rows
            ->map(fn (TyreImportRow $row): array => $this->formatGridRow($row))
            ->all();
    }

    protected function buildPlaceholderRows(): array
    {
        return [
            [
                'sku' => '1234',
                'brand' => 'michelin',
                'model' => 'Pilot Sport 4S',
                'width' => 245,
                'height' => 30,
                'rim_size' => 20,
                'full_size' => '245/35R20',
                'load_index' => 118,
                'speed_rating' => 'S',
                'dot' => '2026',
                'country' => 'Japan',
                'type' => 'Performance',
                'runflat' => 'NO',
                'rfid' => 'YES',
                'sidewall' => 'Black',
                'warranty' => '5 Years',
                'retail_price' => 1000,
                'wholesale_price_lvl1' => 900,
                'wholesale_price_lvl2' => 850,
                'wholesale_price_lvl3' => 700,
                'brand_image' => 'brand_image.png',
                'product_image_1' => 'product_image_1.png',
                'product_image_2' => 'product_image_2.png',
                'product_image_3' => 'product_image_3.png',
            ],
        ];
    }

    private function resolveCurrentAccount(): ?Account
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user instanceof User) {
            return null;
        }

        try {
            return app(CurrentAccountResolver::class)->resolve(request(), $user)->currentAccount;
        } catch (Throwable) {
            return null;
        }
    }

    private function latestBatchFor(Account $account): ?TyreImportBatch
    {
        return TyreImportBatch::query()
            ->where('account_id', $account->id)
            ->latest('id')
            ->with(['rows' => fn ($query) => $query->orderBy('source_row_number')->limit(50)])
            ->first();
    }

    private function buildCurrentAccountSummary(?Account $account): array
    {
        if (! $account instanceof Account) {
            return [
                'name' => 'No active account',
                'slug' => null,
                'supports_wholesale' => false,
                'supports_retail' => false,
                'status' => null,
            ];
        }

        return [
            'name' => $account->name,
            'slug' => $account->slug,
            'supports_wholesale' => $account->isWholesalerEnabled(),
            'supports_retail' => $account->isRetailEnabled(),
            'status' => $account->status?->value,
        ];
    }

    private function buildLatestImportBatchPayload(TyreImportBatch $batch): array
    {
        return [
            'id' => $batch->id,
            'file_name' => $batch->source_file_name,
            'source_format' => strtoupper($batch->source_format),
            'status' => $batch->status->value,
            'sheet_name' => $batch->sheet_name,
            'uploaded_by' => $batch->uploadedBy?->name,
            'uploaded_at' => $batch->created_at?->format('d M Y, h:i A'),
            'row_window_count' => $batch->rows->count(),
        ];
    }

    private function buildImportSummaryCards(TyreImportBatch $batch): array
    {
        return [
            [
                'label' => 'Total rows',
                'value' => $batch->total_rows,
                'note' => 'Source rows detected in the uploaded file.',
            ],
            [
                'label' => 'Valid rows',
                'value' => $batch->valid_rows,
                'note' => 'Rows ready for the next persistence step.',
            ],
            [
                'label' => 'Duplicate rows',
                'value' => $batch->duplicate_rows,
                'note' => "Rows merged by George's grouping rule and flagged inside the same supplier file.",
            ],
            [
                'label' => 'Invalid rows',
                'value' => $batch->invalid_rows,
                'note' => 'Rows with missing fields or validation problems.',
            ],
        ];
    }

    private function buildImportIssueRows(TyreImportBatch $batch): array
    {
        return $batch->rows
            ->filter(function (TyreImportRow $row): bool {
                return $row->status->value !== 'valid'
                    || ! empty($row->validation_errors)
                    || ! empty($row->validation_warnings);
            })
            ->map(function (TyreImportRow $row): array {
                return [
                    'source_row_number' => $row->source_row_number,
                    'status' => $row->status->value,
                    'sku' => $row->source_sku ?? '--',
                    'summary' => $row->validation_errors[0]
                        ?? $row->validation_warnings[0]
                        ?? 'Grouped import issue',
                    'errors' => $row->validation_errors ?? [],
                    'warnings' => $row->validation_warnings ?? [],
                ];
            })
            ->values()
            ->all();
    }

    private function formatGridRow(TyreImportRow $row): array
    {
        $payload = $row->normalized_payload ?? [];

        return [
            'sku' => $payload['sku'] ?? $row->source_sku ?? '--',
            'brand' => $payload['brand'] ?? null,
            'model' => $payload['model'] ?? null,
            'width' => $payload['width'] ?? null,
            'height' => $payload['height'] ?? null,
            'rim_size' => $payload['rim_size'] ?? null,
            'full_size' => $payload['full_size'] ?? ($payload['canonical_size'] ?? null),
            'load_index' => $payload['load_index'] ?? null,
            'speed_rating' => $payload['speed_rating'] ?? null,
            'dot' => $payload['dot'] ?? null,
            'country' => $payload['country'] ?? null,
            'type' => $payload['type'] ?? null,
            'runflat' => $this->formatBooleanLikeValue($payload['runflat'] ?? null),
            'rfid' => $this->formatBooleanLikeValue($payload['rfid'] ?? null),
            'sidewall' => $payload['sidewall'] ?? null,
            'warranty' => $payload['warranty'] ?? null,
            'retail_price' => $payload['retail_price'] ?? null,
            'wholesale_price_lvl1' => $payload['wholesale_price_lvl1'] ?? null,
            'wholesale_price_lvl2' => $payload['wholesale_price_lvl2'] ?? null,
            'wholesale_price_lvl3' => $payload['wholesale_price_lvl3'] ?? null,
            'brand_image' => $payload['brand_image'] ?? null,
            'product_image_1' => $payload['product_image_1'] ?? null,
            'product_image_2' => $payload['product_image_2'] ?? null,
            'product_image_3' => $payload['product_image_3'] ?? null,
        ];
    }

    private function formatBooleanLikeValue(mixed $value): string
    {
        if ($value === true) {
            return 'YES';
        }

        if ($value === false) {
            return 'NO';
        }

        if ($value === null) {
            return '--';
        }

        return strtoupper((string) $value);
    }

    private function resetImportPreview(): void
    {
        $this->latest_import_batch = [];
        $this->import_summary_cards = [];
        $this->import_issue_rows = [];
        $this->commit_plan_summary_cards = [];
        $this->commit_plan_groups = [];
        $this->commit_plan_scope_note = '';
        $this->target_mapping_summary_cards = [];
        $this->target_mapping_groups = [];
        $this->target_mapping_scope_note = '';
        $this->tyres_data = $this->buildPlaceholderRows();
    }
}
