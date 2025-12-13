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

            // First, try to find by external_id + external_source
            $category = AddOnCategory::where('external_id', $data['external_id'])
                ->where('external_source', $data['external_source'] ?? 'tunerstop')
                ->first();

            if ($category) {
                // Update existing category (found by external_id)
                $category->update([
                    'name' => $data['name'],
                    'slug' => $data['slug'],
                    'display_name' => $data['display_name'] ?? $data['name'],
                    'image' => $data['image'] ?? null,
                    'order_sort' => $data['order_sort'] ?? 0,
                    'is_active' => $data['is_active'] ?? true,
                ]);
                
                Log::info('AddonCategorySyncService: Category updated by external_id', [
                    'id' => $category->id,
                    'slug' => $category->slug
                ]);
                
                return $category;
            }

            // Not found by external_id - try to find by slug (might exist without external tracking)
            $category = AddOnCategory::where('slug', $data['slug'])->first();

            if ($category) {
                // Category exists with same slug but no external_id - link it
                $category->update([
                    'external_id' => $data['external_id'],
                    'external_source' => $data['external_source'] ?? 'tunerstop',
                    'name' => $data['name'],
                    'display_name' => $data['display_name'] ?? $data['name'],
                    'image' => $data['image'] ?? null,
                    'order_sort' => $data['order_sort'] ?? 0,
                    'is_active' => $data['is_active'] ?? true,
                ]);
                
                Log::info('AddonCategorySyncService: Existing category linked to external source', [
                    'id' => $category->id,
                    'slug' => $category->slug,
                    'external_id' => $data['external_id']
                ]);
                
                return $category;
            }

            // Category doesn't exist at all - create new
            $category = AddOnCategory::create([
                'external_id' => $data['external_id'],
                'external_source' => $data['external_source'] ?? 'tunerstop',
                'name' => $data['name'],
                'slug' => $data['slug'],
                'display_name' => $data['display_name'] ?? $data['name'],
                'image' => $data['image'] ?? null,
                'order_sort' => $data['order_sort'] ?? 0,
                'is_active' => $data['is_active'] ?? true,
            ]);

            Log::info('AddonCategorySyncService: New category created', [
                'id' => $category->id,
                'slug' => $category->slug
            ]);

            return $category;
        });
    }
}
