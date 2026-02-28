<?php

namespace App\Http\Controllers\Wholesale;

use App\Modules\Products\Models\ProductVariant;
use App\Modules\Products\Models\Product;
use App\Modules\Wholesale\Helpers\WholesaleProductTransformer;
use App\Modules\Products\Models\AddOn;
use App\Modules\Customers\Services\DealerPricingService;
use Illuminate\Http\Request;

/**
 * Wholesale Product Variant Controller
 *
 * Handles individual product detail views and staggered size selectors.
 *
 * Maps to Angular ApiServices:
 *   getWheelDetail()         → GET /api/product/{slug}/{sku}
 *   getWheelDetailMoreSize() → GET /api/product-more-sizes/{id}/{variantId}/{type}
 */
class ProductVariantController extends BaseWholesaleController
{
    public function __construct(
        protected WholesaleProductTransformer $transformer,
        protected DealerPricingService $pricingService
    ) {}

    /**
     * GET /api/product/{slug}/{sku}
     * Full product detail view: variant data + other sizes + add-ons.
     *
     * Angular sends product name as 'slug' and variant SKU as 'sku'.
     * The 'slug' is URL-encoded (encodeURIComponent) in Angular.
     */
    public function show(Request $request, string $slug, string $sku)
    {
        $dealer = $this->dealer();

        // Decode URL-encoded slug from Angular
        $slug = urldecode($slug);

        // Find the specific variant by SKU, joined to product by name/slug
        $variant = ProductVariant::with([
            'product.brand',
            'product.model',
            'finishRelation',
            'inventories.warehouse',
        ])
        ->whereHas('product', function ($q) use ($slug) {
            $q->where('name', $slug)
              ->orWhere('slug', $slug);
        })
        ->where('sku', $sku)
        ->firstOrFail();

        // All other variants for the same product (for "other sizes" sidebar)
        $otherVariants = ProductVariant::with(['finishRelation', 'inventories.warehouse'])
            ->where('product_id', $variant->product_id)
            ->where('id', '!=', $variant->id)
            ->get();

        // Add-ons for this product (all active add-ons with dealer pricing)
        $addons = AddOn::with('category')
            ->whereNull('deleted_at')
            ->get()
            ->map(fn($addon) => $this->transformer->formatAddon($addon, $dealer));

        return $this->success([
            'product'        => $this->transformer->formatVariant($variant, $dealer),
            'other_variants' => $this->transformer->formatVariants($otherVariants, $dealer),
            'addons'         => $addons,
        ]);
    }

    /**
     * GET /api/product-more-sizes/{id}/{variantId}/{type}
     * Returns additional size variants for staggered fitment setups.
     *
     * Angular calls: getWheelDetailMoreSize(id, variantID, type)
     * 'type' is 'front' or 'rear' for staggered setups, or 'standard'.
     */
    public function moreSizes(Request $request, int $productId, int $variantId, string $type)
    {
        $dealer = $this->dealer();

        $query = ProductVariant::with(['finishRelation', 'inventories.warehouse'])
            ->where('product_id', $productId)
            ->where('id', '!=', $variantId);

        // For staggered fitment, return matching sizes filtered by type tag if stored
        // If no type column exists, return all other variants for the product
        if (in_array($type, ['front', 'rear'])) {
            // Try to filter by type if the column exists
            $query->when(
                \Schema::hasColumn('product_variants', 'fitment_type'),
                fn($q) => $q->where('fitment_type', $type)
            );
        }

        $variants = $query->orderBy('rim_diameter')->orderBy('rim_width')->get();

        return $this->success($this->transformer->formatVariants($variants, $dealer));
    }
}
