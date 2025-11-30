<?php

namespace App\Services;

use App\Modules\Products\Models\AddOnCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AddonCategorySyncService
{
    /**
     * Sync an addon category from external source
     *
     * @param array $data
     * @return AddOnCategory
     */
    public function syncCategory(array $data): AddOnCategory
    {
        return DB::transaction(function () use ($data) {
            Log::info('AddonCategorySyncService: Syncing category', [
                'external_id' => $data['external_id'],
                'slug' => $data['slug']
            ]);

            $category = AddOnCategory::updateOrCreate(
                [
                    'external_id' => $data['external_id'],
                    'external_source' => $data['external_source'] ?? 'tunerstop'
                ],
                [
                    'name' => $data['name'],
                    'slug' => $data['slug'],
                    'display_name' => $data['display_name'] ?? $data['name'],
                    'image' => $data['image'] ?? null,
                    'order_sort' => $data['order_sort'] ?? 0,
                    'is_active' => $data['is_active'] ?? true,
                ]
            );

            Log::info('AddonCategorySyncService: Category synced', [
                'id' => $category->id,
                'slug' => $category->slug,
                'was_created' => $category->wasRecentlyCreated
            ]);

            return $category;
        });
    }
}
