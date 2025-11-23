<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProductSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProductSyncController extends Controller
{
    protected ProductSyncService $syncService;

    public function __construct(ProductSyncService $syncService)
    {
        $this->syncService = $syncService;
    }

    public function sync(Request $request)
    {
        // Simple API Key Authentication
        $apiKey = $request->header('X-API-KEY');
        if ($apiKey !== config('services.crm.api_key', 'secret-key')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        try {
            $data = $request->validate([
                'sku' => 'required|string',
                'name' => 'required|string',
                'brand' => 'array',
                'model' => 'array',
                'finish' => 'array',
                'variants' => 'array',
                'price' => 'nullable|numeric',
                'images' => 'nullable',
                'construction' => 'nullable|string',
                'status' => 'boolean',
            ]);

            Log::info('ProductSyncController: Received payload', ['sku' => $data['sku'], 'variant_count' => count($data['variants'] ?? [])]);

            $product = $this->syncService->syncProduct($data);

            return response()->json([
                'message' => 'Product synced successfully',
                'product_id' => $product->id
            ]);

        } catch (\Exception $e) {
            Log::error('Product Sync Failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Sync failed: ' . $e->getMessage()], 500);
        }
    }
}
