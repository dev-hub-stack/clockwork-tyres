<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Orders\Services\OrderSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OrderSyncController extends Controller
{
    public function __construct(
        protected OrderSyncService $orderSyncService
    ) {}

    /**
     * Sync an order from external system
     */
    public function sync(Request $request)
    {
        set_time_limit(300);
        try {
            $orderData = $request->all();
            $source = $request->input('external_source', 'tunerstop');

            Log::info('OrderSyncController: Received order sync request', [
                'external_order_id' => $orderData['external_order_id'] ?? null,
                'source' => $source
            ]);

            // Map incoming data to expected format if needed
            // The service expects 'order_id' key for external ID
            if (!isset($orderData['order_id']) && isset($orderData['external_order_id'])) {
                $orderData['order_id'] = $orderData['external_order_id'];
            }

            // FIX: Map order_items to items (Tunerstop sends order_items, CRM expects items)
            if (!isset($orderData['items']) && isset($orderData['order_items'])) {
                $orderData['items'] = $orderData['order_items'];
            }

            $order = $this->orderSyncService->syncFromExternal($orderData, $source);

            return response()->json([
                'success' => true,
                'message' => 'Order synced successfully',
                'data' => [
                    'order_id' => $order->id,
                    'quote_number' => $order->quote_number,
                    'external_order_id' => $order->external_order_id
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('OrderSyncController: Sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to sync order: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify if order exists
     */
    public function verify(Request $request)
    {
        $externalOrderId = $request->input('external_order_id');
        $source = $request->input('external_source', 'tunerstop');

        if (!$externalOrderId) {
            return response()->json([
                'success' => false,
                'message' => 'External order ID required'
            ], 400);
        }

        // Check database directly or use service if it has a check method
        // Service has checkOrderExists but it's on the Tunerstop side service...
        // Wait, the CRM service doesn't have checkOrderExists method exposed?
        // Let's check the service content again.
        // It has syncFromExternal, findOrCreateCustomer, etc.
        
        // I'll just check DB directly here for simplicity
        $exists = \App\Modules\Orders\Models\Order::where('external_order_id', $externalOrderId)
            ->where('external_source', $source)
            ->exists();
            
        $order = null;
        if ($exists) {
            $order = \App\Modules\Orders\Models\Order::where('external_order_id', $externalOrderId)
                ->where('external_source', $source)
                ->first();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'exists' => $exists,
                'order_id' => $order ? $order->id : null
            ]
        ]);
    }

    /**
     * Test connection
     */
    public function testConnection()
    {
        return response()->json([
            'success' => true,
            'message' => 'Connection successful',
            'timestamp' => now()->toIso8601String()
        ]);
    }
}
