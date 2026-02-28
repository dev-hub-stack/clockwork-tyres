<?php

namespace App\Http\Controllers\Wholesale;

use App\Modules\Products\Models\AddOn;
use App\Modules\Wholesale\Helpers\WholesaleProductTransformer;
use Illuminate\Http\Request;

/**
 * Wholesale Add-On Controller
 *
 * Returns add-ons (accessories like lug nuts, center caps, TPMS) with
 * dealer-specific pricing applied via DealerPricingService.
 *
 * Maps to Angular ApiServices:
 *   getAddsOnByProduct(prodId) → GET /api/addons/{productId}
 *
 * Note: wholesaleadmin returns ALL active add-ons regardless of productId.
 * The productId param is kept for API compatibility but currently returns all.
 * This can be narrowed later if product-to-addon relationships are defined.
 */
class AddOnController extends BaseWholesaleController
{
    public function __construct(
        protected WholesaleProductTransformer $transformer
    ) {}

    /**
     * GET /api/addons/{productId}
     * Returns all active add-ons with dealer pricing applied.
     * Grouped by category for the Angular add-ons panel.
     */
    public function byProduct(Request $request, int $productId)
    {
        $dealer = $this->dealer();

        $addons = AddOn::with('category')
            ->whereNull('deleted_at')
            ->orderBy('addon_category_id')
            ->orderBy('title')
            ->get();

        $formatted = $addons->map(fn($addon) => $this->transformer->formatAddon($addon, $dealer));

        // Group by category for cleaner frontend rendering
        $grouped = $formatted->groupBy('category')->map(fn($items, $category) => [
            'category' => $category,
            'items'    => $items->values(),
        ])->values();

        return $this->success([
            'addons'  => $formatted,
            'grouped' => $grouped,
        ]);
    }
}
