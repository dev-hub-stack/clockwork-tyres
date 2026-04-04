<?php

namespace App\Modules\Products\Support;

use App\Modules\Products\Models\Brand;
use App\Modules\Products\Models\ProductModel;
use App\Modules\Products\Models\TyreImportBatch;
use App\Modules\Products\Models\TyreImportRow;
use Illuminate\Support\Collection;

final class TyreImportTargetMapper
{
    public function __construct(
        private readonly TyreImportCommitPlanner $planner,
    ) {}

    /**
     * @return array{
     *   summary_cards: array<int, array{label: string, value: int, note: string}>,
     *   group_targets: array<int, array<string, mixed>>,
     *   target_tables: array<int, string>,
     *   scope_note: string
     * }
     */
    public function map(TyreImportBatch $batch): array
    {
        $plan = $this->planner->plan($batch);
        $rowsByMergeKey = $this->rowsByMergeKey($batch);

        $groupTargets = collect($plan['group_rows'])
            ->map(function (array $group) use ($batch, $rowsByMergeKey): array {
                $rows = $rowsByMergeKey->get($group['merge_key'], collect());
                /** @var TyreImportRow|null $firstRow */
                $firstRow = $rows->first();
                $payload = $firstRow?->normalized_payload ?? [];
                $brandResolution = $this->resolveBrand($payload['brand'] ?? $group['brand']);
                $modelResolution = $this->resolveModel($payload['model'] ?? $group['model']);
                $offerTargets = $this->buildOfferTargets($batch, $rows);

                return [
                    'merge_key' => $group['merge_key'],
                    'action_key' => $group['action_key'],
                    'catalog_group_target' => [
                        'target_table' => 'tyre_catalog_groups',
                        'storefront_merge_key' => $group['merge_key'],
                        'brand_id' => $brandResolution['id'],
                        'brand_name' => $payload['brand'] ?? $group['brand'],
                        'model_id' => $modelResolution['id'],
                        'model_name' => $payload['model'] ?? $group['model'],
                        'width' => $payload['width'] ?? null,
                        'height' => $payload['height'] ?? null,
                        'rim_size' => $payload['rim_size'] ?? null,
                        'full_size' => $payload['canonical_size'] ?? ($payload['full_size'] ?? $group['full_size']),
                        'load_index' => $payload['load_index'] ?? null,
                        'speed_rating' => $payload['speed_rating'] ?? null,
                        'dot_year' => $firstRow?->normalized_dot_year ?? ($payload['dot'] ?? null),
                        'country' => $payload['country'] ?? null,
                        'tyre_type' => $payload['type'] ?? null,
                        'runflat' => $payload['runflat'] ?? null,
                        'rfid' => $payload['rfid'] ?? null,
                        'sidewall' => $payload['sidewall'] ?? null,
                        'warranty' => $payload['warranty'] ?? null,
                        'reference_resolution' => [
                            'brand' => $brandResolution,
                            'model' => $modelResolution,
                        ],
                    ],
                    'offer_targets' => $offerTargets,
                    'blocked_surfaces' => [
                        'media' => collect($offerTargets)->where('media_status', 'mapped_storage_pattern')->count(),
                        'inventory' => collect($offerTargets)->where('inventory_status', 'blocked_warehouse_mapping')->count(),
                    ],
                ];
            })
            ->values();

        return [
            'summary_cards' => [
                [
                    'label' => 'Mapped groups',
                    'value' => $groupTargets->count(),
                    'note' => 'Tyre groups now mapped onto dedicated tyre target tables.',
                ],
                [
                    'label' => 'Existing brands',
                    'value' => $groupTargets->filter(
                        fn (array $group): bool => ($group['catalog_group_target']['reference_resolution']['brand']['status'] ?? null) === 'existing'
                    )->count(),
                    'note' => 'Groups whose brand already exists in CRM reference data.',
                ],
                [
                    'label' => 'Mapped media',
                    'value' => $groupTargets->sum(fn (array $group): int => $group['blocked_surfaces']['media']),
                    'note' => 'Image targets now follow the same S3 path pattern as products and add-ons under tyres/.',
                ],
                [
                    'label' => 'Blocked inventory',
                    'value' => $groupTargets->sum(fn (array $group): int => $group['blocked_surfaces']['inventory']),
                    'note' => 'Inventory targets stay blocked until warehouse mapping is introduced.',
                ],
            ],
            'group_targets' => $groupTargets->all(),
            'target_tables' => [
                'tyre_catalog_groups',
                'tyre_account_offers',
            ],
            'scope_note' => 'Target mapping is read-only. It prepares dedicated tyre target entities without applying imported rows live.',
        ];
    }

    /**
     * @return Collection<string, Collection<int, TyreImportRow>>
     */
    private function rowsByMergeKey(TyreImportBatch $batch): Collection
    {
        return TyreImportRow::query()
            ->where('batch_id', $batch->id)
            ->where('status', 'valid')
            ->whereNotNull('storefront_merge_key')
            ->orderBy('source_row_number')
            ->get()
            ->groupBy('storefront_merge_key');
    }

    /**
     * @return array{status: string, id: int|null, note: string}
     */
    private function resolveBrand(?string $brandName): array
    {
        if (! filled($brandName)) {
            return [
                'status' => 'unresolved',
                'id' => null,
                'note' => 'No brand value was available in the staged payload.',
            ];
        }

        $brand = Brand::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($brandName)])
            ->first();

        if ($brand instanceof Brand) {
            return [
                'status' => 'existing',
                'id' => $brand->id,
                'note' => 'CRM brand reference already exists.',
            ];
        }

        return [
            'status' => 'proposed',
            'id' => null,
            'note' => 'A new brand reference would need to be created during import apply.',
        ];
    }

    /**
     * @return array{status: string, id: int|null, note: string}
     */
    private function resolveModel(?string $modelName): array
    {
        if (! filled($modelName)) {
            return [
                'status' => 'unresolved',
                'id' => null,
                'note' => 'No model value was available in the staged payload.',
            ];
        }

        $model = ProductModel::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($modelName)])
            ->first();

        if ($model instanceof ProductModel) {
            return [
                'status' => 'existing_global_model',
                'id' => $model->id,
                'note' => 'Legacy model reference exists, but the current models table is global and not brand-scoped.',
            ];
        }

        return [
            'status' => 'proposed_global_model',
            'id' => null,
            'note' => 'A new model reference would be needed, but the legacy models table is still global and not brand-scoped.',
        ];
    }

    /**
     * @param  Collection<int, TyreImportRow>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function buildOfferTargets(TyreImportBatch $batch, Collection $rows): array
    {
        return $rows
            ->map(function (TyreImportRow $row) use ($batch): array {
                $payload = $row->normalized_payload ?? [];
                $hasMedia = collect(TyreCatalogContract::blueprint()['image_fields'] ?? [])
                    ->contains(fn (string $field): bool => filled($payload[$field] ?? null));

                return [
                    'target_table' => 'tyre_account_offers',
                    'account_id' => $batch->account_id,
                    'source_batch_id' => $batch->id,
                    'source_row_id' => $row->id,
                    'source_sku' => $row->source_sku,
                    'retail_price' => $payload['retail_price'] ?? null,
                    'wholesale_price_lvl1' => $payload['wholesale_price_lvl1'] ?? null,
                    'wholesale_price_lvl2' => $payload['wholesale_price_lvl2'] ?? null,
                    'wholesale_price_lvl3' => $payload['wholesale_price_lvl3'] ?? null,
                    'brand_image' => TyreImageStorage::normalizeImportPath($payload['brand_image'] ?? null),
                    'product_image_1' => TyreImageStorage::normalizeImportPath($payload['product_image_1'] ?? null),
                    'product_image_2' => TyreImageStorage::normalizeImportPath($payload['product_image_2'] ?? null),
                    'product_image_3' => TyreImageStorage::normalizeImportPath($payload['product_image_3'] ?? null),
                    'media_status' => $hasMedia ? 'mapped_storage_pattern' : 'not_provided',
                    'inventory_status' => 'blocked_warehouse_mapping',
                    'offer_payload' => [
                        'source_row_number' => $row->source_row_number,
                        'validation_warnings' => $row->validation_warnings ?? [],
                    ],
                ];
            })
            ->values()
            ->all();
    }
}
