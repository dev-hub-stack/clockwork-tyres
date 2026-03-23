<?php

namespace App\Http\Controllers\Wholesale;

use App\Modules\Products\Models\ProductVariant;
use App\Modules\Products\Models\Product;
use App\Modules\Wholesale\Helpers\WholesaleProductTransformer;
use App\Modules\Products\Models\AddOn;
use App\Modules\Customers\Services\DealerPricingService;
use App\Services\ActivityLogService;
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
        $inventory = $variant->inventories
            ->filter(function ($inv) {
                $warehouseCode = $inv->warehouse?->code;

                if ($warehouseCode === 'NON-STOCK') {
                    return false;
                }

                return (int) $inv->quantity > 0
                    || (int) ($inv->eta_qty ?? 0) > 0
                    || ! empty($inv->eta);
            })
            ->sortBy([
                fn($inv) => ($inv->warehouse?->is_primary && (int) $inv->quantity > 0) ? 0 : 1,
                fn($inv) => ((int) $inv->quantity > 0) ? 0 : 1,
                fn($inv) => $inv->warehouse?->is_primary ? 0 : 1,
                fn($inv) => $inv->warehouse?->warehouse_name ?? $inv->warehouse?->name ?? 'Warehouse',
            ])
            ->values()
            ->map(fn($inv) => [
                'id'                 => $inv->id,
                'product_id'         => $variant->product_id,
                'product_variant_id' => $variant->id,
                'warehouse_id'       => $inv->warehouse_id,
                'quantity'           => (int) $inv->quantity,
                'eta_qty'            => (int) ($inv->eta_qty ?? 0),
                'eta'                => $inv->eta,
                'warehouse'          => [
                    'id'             => $inv->warehouse?->id,
                    'code'           => $inv->warehouse?->code,
                    'is_primary'     => (bool) ($inv->warehouse?->is_primary ?? false),
                    'warehouse_name' => $inv->warehouse?->warehouse_name ?? $inv->warehouse?->name,
                ]
            ])->toArray();

        // Pre-calculate price to avoid extra service call if information is available
        $retail  = (float) ($variant->uae_retail_price ?? $variant->price ?? 0);
        $salePr  = $variant->sale_price ? (float) $variant->sale_price : null;
        $basePrice = ($salePr && $salePr < $retail) ? $salePr : $retail;
        $pricing = $this->pricingService->calculateProductPrice(
            $dealer,
            $basePrice,
            $variant->product?->model_id,
            $variant->product?->brand_id
        );

        if ($dealer) {
            $productName = $variant->product?->name ?? 'Product';
            $sku = $variant->sku ? " ({$variant->sku})" : '';

            ActivityLogService::logForCustomer(
                'dealer_viewed_product',
                "Viewed {$productName}{$sku}",
                $variant,
                $dealer->id,
            );
        }

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
