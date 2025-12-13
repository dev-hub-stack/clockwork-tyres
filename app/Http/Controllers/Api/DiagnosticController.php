<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Schema;
use App\Modules\Products\Models\AddOn;
use App\Modules\Products\Models\AddOnCategory;

class DiagnosticController extends Controller
{
    /**
     * Check addon sync requirements
     */
    public function checkAddonSync(): JsonResponse
    {
        $checks = [];

        // Check if tables exist
        $checks['tables'] = [
            'addons' => Schema::hasTable('addons'),
            'addon_categories' => Schema::hasTable('addon_categories'),
        ];

        // Check addon columns
        if ($checks['tables']['addons']) {
            $addonColumns = Schema::getColumnListing('addons');
            $checks['addon_columns'] = [
                'external_addon_id' => in_array('external_addon_id', $addonColumns),
                'external_source' => in_array('external_source', $addonColumns),
                'addon_category_id' => in_array('addon_category_id', $addonColumns),
                'part_number' => in_array('part_number', $addonColumns),
                'stock_status' => in_array('stock_status', $addonColumns),
                'image_1' => in_array('image_1', $addonColumns),
            ];
        }

        // Check category columns
        if ($checks['tables']['addon_categories']) {
            $categoryColumns = Schema::getColumnListing('addon_categories');
            $checks['category_columns'] = [
                'external_id' => in_array('external_id', $categoryColumns),
                'external_source' => in_array('external_source', $categoryColumns),
                'slug' => in_array('slug', $categoryColumns),
            ];
        }

        // Check if models can be instantiated
        try {
            $checks['models'] = [
                'AddOn' => class_exists(AddOn::class),
                'AddOnCategory' => class_exists(AddOnCategory::class),
            ];
        } catch (\Exception $e) {
            $checks['models_error'] = $e->getMessage();
        }

        // Check if services exist
        try {
            $checks['services'] = [
                'AddonSyncService' => class_exists(\App\Services\AddonSyncService::class),
                'AddonCategorySyncService' => class_exists(\App\Services\AddonCategorySyncService::class),
            ];
        } catch (\Exception $e) {
            $checks['services_error'] = $e->getMessage();
        }

        // Overall status
        $allOk = true;
        foreach ($checks as $key => $values) {
            if (is_array($values)) {
                foreach ($values as $check) {
                    if ($check === false) {
                        $allOk = false;
                        break 2;
                    }
                }
            }
        }

        return response()->json([
            'status' => $allOk ? 'ok' : 'issues_found',
            'environment' => app()->environment(),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'checks' => $checks,
        ]);
    }

    /**
     * Test addon sync with sample data
     */
    public function testAddonSync(Request $request): JsonResponse
    {
        try {
            $service = app(\App\Services\AddonSyncService::class);
            
            $testData = [
                'external_addon_id' => 99999,
                'external_source' => 'diagnostic-test',
                'title' => 'Diagnostic Test Addon',
                'part_number' => 'DIAG-TEST-99999',
                'price' => 100,
                'stock_status' => 'in_stock',
                'category' => [
                    'external_id' => 99999,
                    'name' => 'Test Category',
                    'slug' => 'test-category',
                    'display_name' => 'Test Category',
                    'external_source' => 'diagnostic-test',
                ]
            ];
            
            $addon = $service->syncAddon($testData);
            
            // Clean up
            $addon->delete();
            if ($addon->category && $addon->category->external_source === 'diagnostic-test') {
                $addon->category->delete();
            }
            
            return response()->json([
                'status' => 'success',
                'message' => 'Addon sync test passed',
                'addon_id' => $addon->id,
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'trace' => explode("\n", $e->getTraceAsString()),
            ], 500);
        }
    }
}
