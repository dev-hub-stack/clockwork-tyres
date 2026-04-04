<?php

namespace App\Modules\Inventory\Actions;

use App\Models\User;
use App\Modules\Inventory\Models\InventoryLog;
use App\Modules\Inventory\Models\TyreOfferInventory;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Products\Models\TyreAccountOffer;
use Illuminate\Support\Facades\DB;

final class UpsertTyreOfferInventoryAction
{
    public function execute(
        TyreAccountOffer $offer,
        Warehouse $warehouse,
        int $quantity,
        ?string $eta = null,
        int $etaQty = 0,
        ?User $actor = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $notes = null,
    ): TyreOfferInventory {
        $normalizedQuantity = max(0, $quantity);
        $normalizedEtaQty = max(0, $etaQty);
        $normalizedEta = filled($eta) ? trim((string) $eta) : null;

        return DB::transaction(function () use (
            $offer,
            $warehouse,
            $normalizedQuantity,
            $normalizedEta,
            $normalizedEtaQty,
            $actor,
            $referenceType,
            $referenceId,
            $notes,
        ): TyreOfferInventory {
            $inventory = TyreOfferInventory::query()->firstOrNew([
                'tyre_account_offer_id' => $offer->id,
                'warehouse_id' => $warehouse->id,
            ]);

            $inventory->account_id = $offer->account_id;

            $beforeQuantity = (int) ($inventory->quantity ?? 0);
            $beforeEta = $inventory->eta;
            $beforeEtaQty = (int) ($inventory->eta_qty ?? 0);

            $inventory->quantity = $normalizedQuantity;
            $inventory->eta = $normalizedEta;
            $inventory->eta_qty = $normalizedEtaQty;
            $inventory->save();

            $offer->forceFill([
                'inventory_status' => $this->resolveOfferInventoryStatus($offer),
            ])->save();

            InventoryLog::create([
                'warehouse_id' => $warehouse->id,
                'tyre_account_offer_id' => $offer->id,
                'action' => InventoryLog::ACTION_ADJUSTMENT,
                'quantity_before' => $beforeQuantity,
                'quantity_after' => $inventory->quantity,
                'quantity_change' => $inventory->quantity - $beforeQuantity,
                'eta_before' => $beforeEta,
                'eta_after' => $inventory->eta,
                'eta_qty_before' => $beforeEtaQty,
                'eta_qty_after' => $inventory->eta_qty,
                'reference_type' => $referenceType ?? 'tyre_offer',
                'reference_id' => $referenceId ?? $offer->id,
                'notes' => $notes ?? 'Updated via tyre offer inventory action',
                'user_id' => $actor?->id,
            ]);

            return $inventory;
        });
    }

    private function resolveOfferInventoryStatus(TyreAccountOffer $offer): string
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
