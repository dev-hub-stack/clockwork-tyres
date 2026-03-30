<?php

namespace App\Modules\Products\Actions;

use App\Models\User;
use App\Modules\Products\Enums\TyreImportBatchStatus;
use App\Modules\Products\Models\TyreAccountOffer;
use App\Modules\Products\Models\TyreCatalogGroup;
use App\Modules\Products\Models\TyreImportBatch;
use App\Modules\Products\Support\TyreImportTargetMapper;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ApplyTyreImportBatchAction
{
    public function __construct(
        private readonly TyreImportTargetMapper $targetMapper,
    ) {}

    /**
     * @return array{groups_created: int, groups_updated: int, offers_created: int, offers_updated: int}
     */
    public function execute(TyreImportBatch $batch, User $actor): array
    {
        if (! in_array($batch->status, [TyreImportBatchStatus::STAGED, TyreImportBatchStatus::APPLIED], true)) {
            throw new InvalidArgumentException('Only staged or previously applied tyre batches can be applied.');
        }

        $mapped = $this->targetMapper->map($batch);
        $groupTargets = $mapped['group_targets'] ?? [];

        if ($groupTargets === []) {
            throw new InvalidArgumentException('This tyre batch has no mapped groups ready for apply.');
        }

        return DB::transaction(function () use ($batch, $actor, $mapped, $groupTargets): array {
            $counts = [
                'groups_created' => 0,
                'groups_updated' => 0,
                'offers_created' => 0,
                'offers_updated' => 0,
            ];

            foreach ($groupTargets as $groupTarget) {
                $catalogGroupTarget = $groupTarget['catalog_group_target'];
                $existingGroup = TyreCatalogGroup::query()
                    ->where('storefront_merge_key', $catalogGroupTarget['storefront_merge_key'])
                    ->first();

                $group = TyreCatalogGroup::query()->updateOrCreate(
                    ['storefront_merge_key' => $catalogGroupTarget['storefront_merge_key']],
                    [
                        'brand_id' => $catalogGroupTarget['brand_id'],
                        'brand_name' => $catalogGroupTarget['brand_name'],
                        'model_id' => $catalogGroupTarget['model_id'],
                        'model_name' => $catalogGroupTarget['model_name'],
                        'width' => $catalogGroupTarget['width'],
                        'height' => $catalogGroupTarget['height'],
                        'rim_size' => $catalogGroupTarget['rim_size'],
                        'full_size' => $catalogGroupTarget['full_size'],
                        'load_index' => $catalogGroupTarget['load_index'],
                        'speed_rating' => $catalogGroupTarget['speed_rating'],
                        'dot_year' => $catalogGroupTarget['dot_year'],
                        'country' => $catalogGroupTarget['country'],
                        'tyre_type' => $catalogGroupTarget['tyre_type'],
                        'runflat' => $catalogGroupTarget['runflat'],
                        'rfid' => $catalogGroupTarget['rfid'],
                        'sidewall' => $catalogGroupTarget['sidewall'],
                        'warranty' => $catalogGroupTarget['warranty'],
                        'reference_resolution' => $catalogGroupTarget['reference_resolution'],
                        'meta' => [
                            'last_source_batch_id' => $batch->id,
                            'apply_actor_id' => $actor->id,
                        ],
                    ],
                );

                if ($existingGroup instanceof TyreCatalogGroup) {
                    $counts['groups_updated']++;
                } else {
                    $counts['groups_created']++;
                }

                foreach ($groupTarget['offer_targets'] as $offerTarget) {
                    $existingOffer = TyreAccountOffer::query()
                        ->where('account_id', $offerTarget['account_id'])
                        ->where('source_sku', $offerTarget['source_sku'])
                        ->first();

                    TyreAccountOffer::query()->updateOrCreate(
                        [
                            'account_id' => $offerTarget['account_id'],
                            'source_sku' => $offerTarget['source_sku'],
                        ],
                        [
                            'tyre_catalog_group_id' => $group->id,
                            'source_batch_id' => $offerTarget['source_batch_id'],
                            'source_row_id' => $offerTarget['source_row_id'],
                            'retail_price' => $offerTarget['retail_price'],
                            'wholesale_price_lvl1' => $offerTarget['wholesale_price_lvl1'],
                            'wholesale_price_lvl2' => $offerTarget['wholesale_price_lvl2'],
                            'wholesale_price_lvl3' => $offerTarget['wholesale_price_lvl3'],
                            'brand_image' => $offerTarget['brand_image'],
                            'product_image_1' => $offerTarget['product_image_1'],
                            'product_image_2' => $offerTarget['product_image_2'],
                            'product_image_3' => $offerTarget['product_image_3'],
                            'media_status' => $offerTarget['media_status'],
                            'inventory_status' => $offerTarget['inventory_status'],
                            'offer_payload' => $offerTarget['offer_payload'],
                        ],
                    );

                    if ($existingOffer instanceof TyreAccountOffer) {
                        $counts['offers_updated']++;
                    } else {
                        $counts['offers_created']++;
                    }
                }
            }

            $batch->update([
                'status' => TyreImportBatchStatus::APPLIED,
                'applied_at' => now(),
                'applied_by_user_id' => $actor->id,
                'apply_summary' => array_merge($counts, [
                    'mapped_summary_cards' => $mapped['summary_cards'] ?? [],
                    'target_tables' => $mapped['target_tables'] ?? [],
                ]),
            ]);

            return $counts;
        });
    }
}
