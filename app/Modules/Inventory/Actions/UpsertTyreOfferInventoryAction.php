<?php

namespace App\Modules\Inventory\Actions;

use App\Models\User;
use App\Modules\Inventory\Models\InventoryLog;
use App\Modules\Inventory\Models\TyreOfferInventory;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Support\SyncTyreOfferInventoryStatus;
use App\Modules\Products\Models\TyreAccountOffer;
use Illuminate\Support\Facades\DB;

final class UpsertTyreOfferInventoryAction
{
    public function __construct(
        private readonly SyncTyreOfferInventoryStatus $statusSync,
    ) {
    }

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
        string $action = InventoryLog::ACTION_ADJUSTMENT,
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

            $this->statusSync->sync($offer);

            InventoryLog::create([
                'warehouse_id' => $warehouse->id,
                'tyre_account_offer_id' => $offer->id,
                'action' => $action,
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
}
