<?php

namespace App\Http\Controllers\Api;

use App\Services\AddonSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AddonSyncController extends Controller
{
    protected $syncService;

    public function __construct(AddonSyncService $syncService)
    {
        $this->syncService = $syncService;
    }

    /**
     * Handle addon sync from tunerstop-admin
     */
    public function sync(Request $request): JsonResponse
    {
        try {
            Log::info('AddonSyncController: Received payload', [
                'external_addon_id' => $request->input('external_addon_id'),
                'part_number' => $request->input('part_number')
            ]);

            // Validate request
            $validator = Validator::make($request->all(), [
                'external_addon_id' => 'required|integer',
                'external_source' => 'required|string',
                'title' => 'required|string|max:255',
                'part_number' => 'nullable|string|max:255',
                'category' => 'required|array',
                'category.external_id' => 'required|integer',
                'category.slug' => 'required|string',
                'stock_status' => 'nullable|in:in_stock,out_of_stock,pre_order,backorder,discontinued',
                'price' => 'nullable|numeric|min:0',
                'wholesale_price' => 'nullable|numeric|min:0',
                'wh2_california' => 'nullable|integer|min:0',
                'wh1_chicago' => 'nullable|integer|min:0',
            ]);

            if ($validator->fails()) {
                Log::warning('AddonSyncController: Validation failed', [
                    'errors' => $validator->errors()->toArray()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $addon = $this->syncService->syncAddon($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Addon synced successfully',
                'addon_id' => $addon->id
            ]);

        } catch (\Exception $e) {
            Log::error('AddonSyncController: Sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to sync addon',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
