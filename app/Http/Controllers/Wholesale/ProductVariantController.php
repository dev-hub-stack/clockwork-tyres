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
            $q->where('name', $slug);
        })
        ->where('sku', $sku)
        ->firstOrFail();

        // All other variants for the same product (for "other sizes" sidebar)
        $otherVariants = ProductVariant::with(['finishRelation', 'inventories.warehouse'])
            ->where('product_id', $variant->product_id)
            ->where('id', '!=', $variant->id)
            ->get();

        // Get distinct rim diameters for "more sizes" tabs
        $moreSizes = ProductVariant::where('product_id', $variant->product_id)
            ->distinct()
            ->orderBy('rim_diameter')
            ->pluck('rim_diameter')
            ->values()
            ->toArray();

        // Add-ons for this product (all active add-ons with dealer pricing)
        // Limits to 20 to prevent excessive load on detail page
        $addons = AddOn::with('category')
            ->whereNull('deleted_at')
            ->limit(20)
            ->get()
            ->map(fn($addon) => $this->transformer->formatAddon($addon, $dealer));

        // Format inventory records to match Angular Inventory interface
        $inventory = $variant->inventories->map(fn($inv) => [
            'id'                 => $inv->id,
            'product_id'         => $variant->product_id,
            'product_variant_id' => $variant->id,
            'warehouse_id'       => $inv->warehouse_id,
            'quantity'           => $inv->quantity,
            'eta'                => $inv->eta,
            'warehouse'          => [
                'id'             => $inv->warehouse?->id,
                'code'           => $inv->warehouse?->code,
                'warehouse_name' => $inv->warehouse?->name,
            ]
        ])->toArray();

        return $this->success([
            'product'         => $this->transformer->formatVariant($variant, $dealer),
            'other_variants'  => $this->transformer->formatVariants($otherVariants, $dealer),
            'addons'          => $addons,
            'product_reviews' => [],
            'inventory'       => $inventory,
            'finishes'        => [],
            'more_sizes'      => $moreSizes,
            'discounted_price' => $this->pricingService->calculateProductPrice($dealer, (float)$variant->uae_retail_price, $variant->product?->model_id, $variant->product?->brand_id)['final_price'],
        ]);
    }

    /**
     * GET /api/product-more-sizes/{id}/{variantId}/{type}
     * Returns additional size variants for staggered fitment setups.
     *
     * Angular calls: getWheelDetailMoreSize(id, variantID, type)
     * 'type' is diameter value (e.g. "17") in this version of frontend.
     */
    public function moreSizes(Request $request, int $productId, int $variantId, string $type)
    {
        $dealer = $this->dealer();

        $query = ProductVariant::with(['finishRelation', 'inventories.warehouse'])
            ->where('product_id', $productId)
            ->where('rim_diameter', $type);

        $variants = $query->orderBy('rim_diameter')->orderBy('rim_width')->get();

        return $this->success([
            'sizes' => $this->transformer->formatVariants($variants, $dealer)
        ]);
    }
}
