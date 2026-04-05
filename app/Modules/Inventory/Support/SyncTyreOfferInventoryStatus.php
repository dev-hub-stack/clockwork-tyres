<?php

namespace App\Modules\Inventory\Support;

use App\Modules\Products\Models\TyreAccountOffer;

final class SyncTyreOfferInventoryStatus
{
    public function sync(TyreAccountOffer $offer): void
    {
        $offer->forceFill([
            'inventory_status' => $this->resolve($offer),
        ])->save();
    }

    public function resolve(TyreAccountOffer $offer): string
    {
        $hasInventory = $offer->inventories()->exists();

        if (! $hasInventory) {
            return 'blocked_warehouse_mapping';
        }

        $hasCurrentStock = $offer->inventories()->where('quantity', '>', 0)->exists();

        if ($hasCurrentStock) {
            return 'configured_in_stock';
        }

        $hasInboundStock = $offer->inventories()->where('eta_qty', '>', 0)->exists();

        return $hasInboundStock ? 'configured_inbound' : 'configured_out_of_stock';
    }
}
