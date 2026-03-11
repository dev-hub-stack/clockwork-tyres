<?php

namespace App\Http\Controllers\Wholesale;

use App\Modules\Products\Models\Brand;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductVariant;
use App\Modules\Wholesale\Helpers\WholesaleProductTransformer;
use Illuminate\Http\Request;

/**
 * Wholesale Brand Controller
 *
 * Handles brand listings, brand product pages, and variant selectors.
 *
 * Maps to Angular ApiServices:
 *   getBrands()                   → GET /api/brands
 *   getAllBrands()                 → GET /api/all-brands
 *   getAllBrandProducts()          → GET /api/all-brand-products/{brand}
 *   getAllBrandProductVariants()   → GET /api/all-brand-products/{brand}/{productSlug}
 *   getProductMoreSizes()         → GET /api/brand-product-more-sizes/{id}/{type}
 */
class BrandController extends BaseWholesaleController
{
    public function __construct(
        protected WholesaleProductTransformer $transformer
    ) {}

    /**
     * GET /api/brands
     * Compact brand list for the navigation bar / brand logo strip.
     * Angular calls: getBrands()
     */
    public function index()
    {
        $brands = Brand::active()
            ->ordered()
            ->whereHas('products', fn($q) => $q->where('status', 1)->where('available_on_wholesale', true))
            ->get(['id', 'name', 'slug', 'logo', 'description']);

        return $this->success($brands);
    }

    /**
     * GET /api/all-brands
     * Full brand listing page with product counts.
     * Angular calls: getAllBrands(params)
     */
    public function all(Request $request)
    {
        $query = Brand::active()
            ->ordered()
            ->whereHas('products', fn($q) => $q->where('status', 1)->where('available_on_wholesale', true));

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $brands = $query->withCount(['products' => fn($q) => $q->where('status', 1)->where('available_on_wholesale', true)])->get();

        $formatted = $brands->map(fn(Brand $b) => [
            'id'            => $b->id,
            'name'          => $b->name,
            'slug'          => $b->slug,
            'image'         => $b->logo,   // Angular template uses brand.image
            'description'   => $b->description,
            'product_count' => $b->products_count,
        ])->values()->all();

        // Angular brand component expects: response.data.brands.data + response.data.brands.next_page_url
        return $this->success([
            'brands' => [
                'data'          => $formatted,
                'next_page_url' => null,
            ],
        ]);
    }

    /**
     * GET /api/all-brand-products/{brand}
     * All product variants for a specific brand, paginated with filters.
     * Angular calls: getAllBrandProducts(params, brand)
     */
    public function brandProducts(Request $request, string $brandSlug)
    {
        $brand = Brand::where('slug', $brandSlug)
            ->orWhere('name', urldecode($brandSlug))
            ->firstOrFail();

        // Return unique products for this brand that actually have variants
        $products = Product::where('brand_id', $brand->id)
            ->where('status', 1)
            ->where('available_on_wholesale', true)
            ->whereHas('variants')   // only products that have at least one variant
            ->get(['id', 'name', 'images']);

        $formatted = $products
            ->unique('name')          // deduplicate same-named products
            ->map(fn(Product $p) => [
                'name'   => $p->name,
                'slug'   => $p->name,
                'images' => $p->images ?? [],
            ])->values()->all();

        // Angular template expects: brandProducts.image, brandProducts.name, brandProducts.products[]
        return $this->success([
            'products' => [
                'image'    => $brand->logo,   // brand logo
                'name'     => $brand->name,
                'products' => $formatted,
            ],
        ]);
    }

    /**
     * GET /api/all-brand-products/{brand}/{slug}
     * All variants for a specific product within a brand.
     * Angular calls: getAllBrandProductVariants(params, brand, productSlug)
     */
    public function brandProductVariants(Request $request, string $brandSlug, string $productSlug)
    {
        $dealer = $this->dealer();

        $brand = Brand::where('slug', $brandSlug)
            ->orWhere('name', urldecode($brandSlug))
            ->firstOrFail();

        // products table has no slug column — match by name only
        $variants = ProductVariant::with([
            'product.brand',
            'product.model',
        ])
        ->whereHas('product', function ($q) use ($brand, $productSlug) {
            $q->where('brand_id', $brand->id)
              ->where('status', 1)
              ->where('available_on_wholesale', true)
              ->where('name', urldecode($productSlug));
        })
        ->get();

        if ($variants->isEmpty()) {
            return $this->error('Product not found.', null, 404);
        }

        $firstVariant = $variants->first();
        $product      = $firstVariant->product;

        // Distinct rim diameters for the size tabs
        $moreSizes = ProductVariant::where('product_id', $product->id)
            ->distinct()
            ->orderBy('rim_diameter')
            ->pluck('rim_diameter')
            ->filter()
            ->values()
            ->all();

        // Angular template expects: brandProductVariants.product.{ name, finish, images, id }
        //                           brandProductVariants.image  (brand logo)
        //                           wheelSizeTypes = response.data.more_sizes
        return $this->success([
            'products' => [
                'image'   => $brand->logo,
                'product' => [
                    'id'     => $product->id,
                    'name'   => $product->name,
                    'images' => $product->images ?? [],
                    'finish' => [
                    'finish' => $firstVariant->getRawOriginal('finish') ?? '',   // avoid finish() relation shadowing column
                ],
                ],
            ],
            'more_sizes' => $moreSizes,
        ]);
    }

    /**
     * GET /api/brand-product-more-sizes/{id}/{type}
     * Returns variants for a product filtered by rim_diameter.
     * Angular calls: getProductMoreSizes(product.id, rim_diameter)
     * Response: { data: { sizes: [ { sku, size, bolt_pattern, offset, weight, total_quantity } ] } }
     */
    public function productMoreSizes(Request $request, int $productId, string $type)
    {
        $query = ProductVariant::with(['finishRelation'])
            ->where('product_id', $productId)
            ->whereHas('product', fn($q) => $q->where('available_on_wholesale', true)->where('status', 1));

        // $type is a rim diameter (numeric string like "18", "19")
        if (is_numeric($type)) {
            $query->where('rim_diameter', $type);
        }

        $variants = $query->orderBy('rim_width')->get();

        $sizes = $variants->map(fn(ProductVariant $v) => [
            'sku'            => $v->sku,
            'finish'         => $v->getRawOriginal('finish') ?? '',  // avoid finish() relation method shadowing the column
            'size'           => $v->rim_diameter . 'x' . $v->rim_width,
            'bolt_pattern'   => $v->bolt_pattern,
            'offset'         => $v->offset,
            'weight'         => $v->weight,
            'total_quantity' => $v->supplier_stock ?? 0,
        ])->values()->all();

        return $this->success(['sizes' => $sizes]);
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    private function applyFilters($query, Request $request): void
    {
        if ($request->filled('rim_diameter')) {
            $query->where('rim_diameter', $request->rim_diameter);
        }
        if ($request->filled('rim_width')) {
            $query->where('rim_width', $request->rim_width);
        }
        if ($request->filled('bolt_pattern')) {
            $query->where('bolt_pattern', $request->bolt_pattern);
        }
        if ($request->filled('finish_id')) {
            $query->where('finish_id', $request->finish_id);
        }
        if ($request->filled('clearance') && $request->clearance) {
            $query->where('clearance_corner', true);
        }
        if ($request->filled('search')) {
            $term = '%' . $request->search . '%';
            $query->where(function ($q) use ($term) {
                $q->where('sku', 'like', $term)
                  ->orWhereHas('product', fn($p) => $p->where('name', 'like', $term));
            });
        }
    }
}
