<?php

namespace App\Http\Controllers\Wholesale;

use App\Modules\Wholesale\Wishlists\Models\Wishlist;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;

/**
 * Wishlist Controller (Phase 5)
 *
 * Maps to Angular:
 *   addWishlist()       → POST /api/wishlist/add
 *   deleteWishlist()    → DELETE /api/wishlist/{id}/delete
 *   Note: Wishlist fetching is integrated into the 'profile' endpoint.
 */
class WishlistController extends BaseWholesaleController
{
    /**
     * POST /api/wishlist/add
     */
    public function store(Request $request)
    {
        $request->validate([
            'product_variant_id' => 'required|integer|exists:product_variants,id',
        ]);

        $dealer = $this->dealer();

        $wishlist = Wishlist::firstOrCreate([
            'dealer_id'          => $dealer->id,
            'product_variant_id' => $request->product_variant_id,
        ]);

        // Eager load data required by Angular frontend (like product, brand)
        $wishlist->load(['productVariant.product.brand']);

        if ($wishlist->wasRecentlyCreated && $wishlist->productVariant) {
            $productName = $wishlist->productVariant->product?->name ?? 'Product';
            $sku = $wishlist->productVariant->sku ? " ({$wishlist->productVariant->sku})" : '';

            ActivityLogService::logForCustomer(
                'dealer_added_to_wishlist',
                "Added to wishlist {$productName}{$sku}",
                $wishlist->productVariant,
                $dealer->id,
            );
        }

        return $this->success($wishlist, 'Item added to wishlist.');
    }

    /**
     * DELETE /api/wishlist/{id}/delete
     */
    public function destroy(Request $request, int $id)
    {
        $dealer = $this->dealer();

        // Ensure dealer only deletes their own wishlist items
        $wishlist = Wishlist::where('dealer_id', $dealer->id)
            ->with('productVariant.product')
            ->findOrFail($id);

        if ($wishlist->productVariant) {
            $productName = $wishlist->productVariant->product?->name ?? 'Product';
            $sku = $wishlist->productVariant->sku ? " ({$wishlist->productVariant->sku})" : '';

            ActivityLogService::logForCustomer(
                'dealer_removed_from_wishlist',
                "Removed from wishlist {$productName}{$sku}",
                $wishlist->productVariant,
                $dealer->id,
            );
        }

        $wishlist->delete();

        return $this->success(null, 'Item removed from wishlist.');
    }
}
