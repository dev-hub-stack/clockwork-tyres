<?php

namespace App\Modules\Inventory\Support;

use App\Modules\Inventory\Models\TyreOfferInventory;
use App\Modules\Products\Models\TyreAccountOffer;
use Illuminate\Support\Collection;

final class TyreOfferAvailabilityResolver
{
    /**
     * @param  Collection<int, TyreAccountOffer>  $offers
     * @return array{
     *   origin: 'own'|'supplier',
     *   label: string,
     *   quantity: int,
     *   show_quantity: bool,
     *   supplier_count: int,
     *   has_own_stock: bool,
     *   has_supplier_stock: bool,
     *   own_quantity: int,
     *   supplier_quantity: int
     * }
     */
    public function resolve(Collection $offers, int $ownAccountId): array
    {
        $ownOffers = $offers->where('account_id', $ownAccountId)->values();
        $supplierOffers = $offers->where('account_id', '!=', $ownAccountId)->values();

        $ownQuantity = $this->currentQuantity($ownOffers);
        $supplierQuantity = $this->currentQuantity($supplierOffers);
        $supplierCount = $supplierOffers
            ->filter(fn (TyreAccountOffer $offer) => $this->offerCurrentQuantity($offer) > 0)
            ->pluck('account_id')
            ->unique()
            ->count();

        if ($ownQuantity > 0) {
            return [
                'origin' => 'own',
                'label' => 'in stock',
                'quantity' => $ownQuantity,
                'show_quantity' => $ownQuantity <= 4,
                'supplier_count' => $supplierCount,
                'has_own_stock' => true,
                'has_supplier_stock' => $supplierQuantity > 0,
                'own_quantity' => $ownQuantity,
                'supplier_quantity' => $supplierQuantity,
            ];
        }

        if ($supplierQuantity > 0) {
            return [
                'origin' => 'supplier',
                'label' => 'available',
                'quantity' => $supplierQuantity,
                'show_quantity' => $supplierQuantity <= 4,
                'supplier_count' => $supplierCount,
                'has_own_stock' => false,
                'has_supplier_stock' => true,
                'own_quantity' => $ownQuantity,
                'supplier_quantity' => $supplierQuantity,
            ];
        }

        return [
            'origin' => $ownOffers->isNotEmpty() ? 'own' : 'supplier',
            'label' => 'out of stock',
            'quantity' => 0,
            'show_quantity' => false,
            'supplier_count' => 0,
            'has_own_stock' => false,
            'has_supplier_stock' => false,
            'own_quantity' => 0,
            'supplier_quantity' => 0,
        ];
    }

    /**
     * @param  Collection<int, TyreAccountOffer>  $offers
     */
    public function currentQuantity(Collection $offers): int
    {
        return (int) $offers->sum(fn (TyreAccountOffer $offer) => $this->offerCurrentQuantity($offer));
    }

    public function offerCurrentQuantity(TyreAccountOffer $offer): int
    {
        return (int) $offer->inventories
            ->filter(fn (TyreOfferInventory $inventory) => $inventory->warehouse?->code !== 'NON-STOCK')
            ->sum('quantity');
    }
}
