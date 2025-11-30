<?php

namespace App\Services;

use App\Modules\Products\Models\AddOn;
use App\Modules\Products\Models\AddOnCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AddonSyncService
{
    protected $categorySyncService;

    public function __construct(AddonCategorySyncService $categorySyncService)
    {
        $this->categorySyncService = $categorySyncService;
    }

    /**
     * Sync an addon from external source
     * Includes embedded category sync for data integrity
     *
     * @param array $data
     * @return AddOn
     */
    public function syncAddon(array $data): AddOn
    {
        return DB::transaction(function () use ($data) {
            Log::info('AddonSyncService: Syncing addon', [
                'external_addon_id' => $data['external_addon_id'],
                'part_number' => $data['part_number']
            ]);

            // EDGE CASE 1: Ensure category exists (embedded sync)
            $category = $this->ensureCategory($data['category']);

            // EDGE CASE 2: Check for duplicate part_number from different source
            $this->handleDuplicatePartNumber($data);

            // Prepare addon data
            $addonData = $this->prepareAddonData($data, $category);

            // EDGE CASE 3: Use updateOrCreate for idempotency
            $addon = AddOn::updateOrCreate(
                [
                    'external_addon_id' => $data['external_addon_id'],
                    'external_source' => $data['external_source'] ?? 'tunerstop'
                ],
                $addonData
            );

            Log::info('AddonSyncService: Addon synced', [
                'id' => $addon->id,
                'part_number' => $addon->part_number,
                'was_created' => $addon->wasRecentlyCreated
            ]);

            return $addon;
        });
    }

    /**
     * EDGE CASE HANDLER: Ensure category exists via embedded sync
     */
    protected function ensureCategory(array $categoryData): AddOnCategory
    {
        // First try to find existing category by external_id
        $category = AddOnCategory::where('external_id', $categoryData['external_id'])
            ->where('external_source', $categoryData['external_source'] ?? 'tunerstop')
            ->first();

        if ($category) {
            Log::debug('AddonSyncService: Category already exists', ['category_id' => $category->id]);
            return $category;
        }

        // Category doesn't exist - create it via embedded sync
        Log::info('AddonSyncService: Category not found, creating via embedded sync', [
            'external_id' => $categoryData['external_id']
        ]);

        return $this->categorySyncService->syncCategory($categoryData + [
            'external_source' => 'tunerstop'
        ]);
    }

    /**
     * EDGE CASE HANDLER: Check for duplicate part_number
     */
    protected function handleDuplicatePartNumber(array $data): void
    {
        $existingAddon = AddOn::where('part_number', $data['part_number'])
            ->where(function ($query) use ($data) {
                $query->whereNull('external_source')
                    ->orWhere('external_source', '!=', $data['external_source'] ?? 'tunerstop');
            })
            ->first();

        if ($existingAddon) {
            Log::warning('AddonSyncService: Duplicate part_number from different source', [
                'part_number' => $data['part_number'],
                'existing_id' => $existingAddon->id,
                'existing_source' => $existingAddon->external_source,
                'new_source' => $data['external_source'] ?? 'tunerstop'
            ]);
            // Not blocking - just log for manual review
        }
    }

    /**
     * Prepare addon data with all edge cases handled
     */
    protected function prepareAddonData(array $data, AddOnCategory $category): array
    {
        $addonData = [
            'addon_category_id' => $category->id,
            'title' => $data['title'],
            'part_number' => $data['part_number'],
            
            // EDGE CASE: Price validation
            'price' => $this->sanitizePrice($data['price'] ?? null),
            'wholesale_price' => $this->sanitizePrice($data['wholesale_price'] ?? null),
            
            'size' => $data['size'] ?? null,
            'unit' => $data['unit'] ?? null,
            'image' => $data['image'] ?? null,
            
            // EDGE CASE: Stock status with default
            'stock_status' => $this->sanitizeStockStatus($data['stock_status'] ?? null),
            
            'description' => $data['description'] ?? null,
        ];

        // Add category-specific fields if present
        $categorySpecificFields = [
            'thread_size', 'color', 'lug_nut_length', 'lug_nut_diameter',
            'thread_length', 'lug_bolt_diameter', 'ext_center_bore',
            'center_bore', 'vehicle', 'bolt_pattern', 'width'
        ];

        foreach ($categorySpecificFields as $field) {
            if (isset($data[$field])) {
                $addonData[$field] = $data[$field];
            }
        }

        return $addonData;
    }

    /**
     * EDGE CASE: Sanitize price values
     */
    protected function sanitizePrice($price): ?float
    {
        if (is_null($price) || $price === '') {
            return null;
        }

        $price = floatval($price);
        
        // Negative prices not allowed
        if ($price < 0) {
            Log::warning('AddonSyncService: Negative price detected, setting to null', ['price' => $price]);
            return null;
        }

        return round($price, 2);
    }

    /**
     * EDGE CASE: Sanitize stock status
     */
    protected function sanitizeStockStatus($status): string
    {
        $validStatuses = ['in_stock', 'out_of_stock', 'pre_order'];
        
        if (!in_array($status, $validStatuses)) {
            Log::warning('AddonSyncService: Invalid stock_status, defaulting to in_stock', ['status' => $status]);
            return 'in_stock';
        }

        return $status;
    }
}
