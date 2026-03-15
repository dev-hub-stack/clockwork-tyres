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
        $slug = urldecode($slug);

        // Optimization: Unified Eager Loading for variant
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

        // Optimized: Only load essential data for "other sizes"
        $otherVariants = ProductVariant::with(['finishRelation', 'inventories.warehouse'])
            ->where('product_id', $variant->product_id)
            ->where('id', '!=', $variant->id)
            ->get();

        $moreSizes = ProductVariant::where('product_id', $variant->product_id)
            ->distinct()
            ->orderBy('rim_diameter')
            ->pluck('rim_diameter')
            ->values()
            ->toArray();

        // Optimized: Eager load category and inventories for addons
        $addons = AddOn::with(['category', 'inventories'])
            ->whereNull('deleted_at')
            ->limit(20)
            ->get()
            ->map(fn($addon) => $this->transformer->formatAddon($addon, $dealer));

        // Optimized inventory mapping to avoid warehouse lookup loops
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
                'warehouse_name' => $inv->warehouse?->warehouse_name ?? $inv->warehouse?->name,
            ]
        ])->toArray();

        // Pre-calculate price to avoid extra service call if information is available
        $pricing = $this->pricingService->calculateProductPrice(
            $dealer, 
            (float)($variant->uae_retail_price ?? $variant->price), 
            $variant->product?->model_id, 
            $variant->product?->brand_id
        );

        return $this->success([
            'product'          => $this->transformer->formatVariant($variant, $dealer),
            'other_variants'   => $this->transformer->formatVariants($otherVariants, $dealer),
            'addons'           => $addons,
            'product_reviews'  => [],
            'inventory'        => $inventory,
            'finishes'         => [],
            'more_sizes'       => $moreSizes,
            'discounted_price' => $pricing['final_price'],
        ]);
    }

    /**
     * GET /api/product-more-sizes/{id}/{variantId}/{type}
     * Returns additional size variants for staggered fitment setups.
     */
    public function moreSizes(Request $request, int $productId, int $variantId, string $type)
    {
        $dealer = $this->dealer();

        $variants = ProductVariant::with(['finishRelation', 'inventories.warehouse'])
            ->where('product_id', $productId)
            ->where('rim_diameter', $type)
            ->orderBy('rim_width')
            ->get();

        return $this->success([
            'sizes' => $this->transformer->formatVariants($variants, $dealer)
        ]);
    }
}
