<?php

namespace App\Services;

use App\Modules\Orders\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TunerstopOrderStatusSyncService
{
    public function sync(Order $order, string $trigger): bool
    {
        if (! config('services.tunerstop.order_status_sync_enabled', false)) {
            Log::info('Tunerstop order status sync skipped because it is disabled.', [
                'order_id' => $order->id,
                'trigger' => $trigger,
            ]);

            return false;
        }

        $url = config('services.tunerstop.order_status_sync_url');

        if (! $url) {
            Log::warning('Tunerstop order status sync skipped because no callback URL is configured.', [
                'order_id' => $order->id,
                'trigger' => $trigger,
            ]);

            return false;
        }

        try {
            $request = Http::acceptJson()->timeout(10);
            $token = config('services.tunerstop.token');

            if ($token) {
                $request = $request->withToken($token);
            }

            $response = $request->post($url, [
                'crm_order_id' => $order->id,
                'order_number' => $order->order_number,
                'quote_number' => $order->quote_number,
                'status' => $order->order_status?->value ?? $order->quote_status,
                'trigger' => $trigger,
                'source' => 'reporting-crm',
            ]);

            if (! $response->successful()) {
                Log::warning('Tunerstop order status sync failed.', [
                    'order_id' => $order->id,
                    'trigger' => $trigger,
                    'status_code' => $response->status(),
                    'response' => $response->body(),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $exception) {
            Log::warning('Tunerstop order status sync threw an exception.', [
                'order_id' => $order->id,
                'trigger' => $trigger,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}