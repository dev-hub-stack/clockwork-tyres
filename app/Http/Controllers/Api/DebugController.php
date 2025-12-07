<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DebugController extends Controller
{
    /**
     * Echo back everything received - for debugging payload transmission
     */
    public function echoPayload(Request $request)
    {
        $allData = $request->all();
        
        Log::info('DEBUG ECHO: Received payload', [
            'total_keys' => count($allData),
            'keys' => array_keys($allData),
            'has_customer' => isset($allData['customer']),
            'has_items' => isset($allData['items']) || isset($allData['order_items']),
            'payload_size_bytes' => strlen(json_encode($allData)),
        ]);
        
        return response()->json([
            'success' => true,
            'received_keys' => array_keys($allData),
            'has_customer' => isset($allData['customer']),
            'has_items' => isset($allData['items']) || isset($allData['order_items']),
            'items_count' => isset($allData['items']) ? count($allData['items']) : (isset($allData['order_items']) ? count($allData['order_items']) : 0),
            'payload_size_bytes' => strlen(json_encode($allData)),
            'customer_first_name' => $allData['customer']['first_name'] ?? 'NOT PRESENT',
        ]);
    }
}
