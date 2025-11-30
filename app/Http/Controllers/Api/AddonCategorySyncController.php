<?php

namespace App\Http\Controllers\Api;

use App\Services\AddonCategorySyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

class AddonCategorySyncController extends Controller
{
    protected $syncService;

    public function __construct(AddonCategorySyncService $syncService)
    {
        $this->syncService = $syncService;
    }

    /**
     * Handle addon category sync from tunerstop-admin
     */
    public function sync(Request $request): JsonResponse
    {
        try {
            Log::info('Addon CategorySyncController: Received payload', [
                'external_id' => $request->input('external_id'),
                'slug' => $request->input('slug')
            ]);

            $category = $this->syncService->syncCategory($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Addon category synced successfully',
                'category_id' => $category->id
            ]);

        } catch (\Exception $e) {
            Log::error('AddonCategorySyncController: Sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to sync addon category',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
