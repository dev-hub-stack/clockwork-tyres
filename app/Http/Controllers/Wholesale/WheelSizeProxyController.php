<?php

namespace App\Http\Controllers\Wholesale;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class WheelSizeProxyController extends BaseWholesaleController
{
    protected ?string $apiKey;
    protected ?string $apiUrl;

    public function __construct()
    {
        $this->apiKey = config('wheel_size.key') ?: env('WHEEL_SIZE_API_KEY');
        $this->apiUrl = rtrim(config('wheel_size.url', env('WHEEL_SIZE_API_URL', 'https://api.wheel-size.com/v2/')), '/') . '/';
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
                $json = $response->json();
                // Wheel-size API returns { count, data: [...] } or { count, results: [...] }
                // Angular expects { status, data: [...] }
                $data = $json['data'] ?? $json['results'] ?? $json;
                return response()->json([
                    'status' => true,
                    'data'   => $data,
                ]);
            }

            return response()->json([
                'status'       => false,
                'message'      => 'External API error',
                'http_status'  => $response->status(),
                'body'         => $response->body(),
            ], 200); // always 200 so Angular can read the payload

        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Proxy request failed: ' . $e->getMessage(),
            ], 200);
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
