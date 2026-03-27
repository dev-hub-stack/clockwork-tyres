<?php

namespace App\Observers;

use App\Modules\Inventory\Models\ProductInventory;
use App\Services\RestockNotificationService;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProductInventoryObserver
{
    public function saved(ProductInventory $inventory): void
    {
        try {
            app(RestockNotificationService::class)->handleInventoryAvailabilityChange($inventory);
        } catch (Throwable $exception) {
            Log::warning('Failed to process restock notification after inventory change', [
                'inventory_id' => $inventory->id,
                'product_variant_id' => $inventory->product_variant_id,
                'add_on_id' => $inventory->add_on_id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}