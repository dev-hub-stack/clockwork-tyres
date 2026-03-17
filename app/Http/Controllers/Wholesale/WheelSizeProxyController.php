<?php

namespace App\Http\Controllers\Wholesale;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class WheelSizeProxyController extends BaseWholesaleController
{
    protected string $apiKey;
    protected string $apiUrl;

    public function __construct()
    {
        $this->apiKey = env('WHEEL_SIZE_API_KEY');
        $this->apiUrl = env('WHEEL_SIZE_API_URL', 'https://api.wheel-size.com/v2/');
    }

    /**
     * Helper to perform GET requests to Wheel-Size API.
     */
    protected function proxyGet(string $endpoint, array $queryParams = [])
    {
        $queryParams['user_key'] = $this->apiKey;

        try {
            $response = Http::get($this->apiUrl . $endpoint . '/', $queryParams);
            
            if ($response->successful()) {
                return response()->json($response->json());
            }

            return $response->json();
        } catch (\Exception $e) {
            return $this->error('Proxy request failed: ' . $e->getMessage(), null, 500);
        }
    }

    public function makes(Request $request)
    {
        return $this->proxyGet('makes', $request->all());
    }

    public function models(Request $request)
    {
        return $this->proxyGet('models', $request->all());
    }

    public function years(Request $request)
    {
        return $this->proxyGet('years', $request->all());
    }

    public function modifications(Request $request)
    {
        return $this->proxyGet('modifications', $request->all());
    }

    public function searchByModel(Request $request)
    {
        return $this->proxyGet('search/by_model', $request->all());
    }
}
