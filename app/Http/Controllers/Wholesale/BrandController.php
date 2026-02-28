<?php

namespace App\Http\Controllers\Wholesale;

use App\Modules\Products\Models\Brand;
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
        $query = Brand::active()->ordered();

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $brands = $query->withCount(['products' => fn($q) => $q->where('status', 1)])->get();

        $formatted = $brands->map(fn(Brand $b) => [
            'id'            => $b->id,
            'name'          => $b->name,
            'slug'          => $b->slug,
            'logo'          => $b->logo,
            'description'   => $b->description,
            'product_count' => $b->products_count,
        ]);

        return $this->success($formatted);
    }

    /**
     * GET /api/all-brand-products/{brand}
     * All product variants for a specific brand, paginated with filters.
     * Angular calls: getAllBrandProducts(params, brand)
     */
    public function brandProducts(Request $request, string $brandSlug)
    {
        $dealer = $this->dealer();

        $brand = Brand::where('slug', $brandSlug)
            ->orWhere('name', urldecode($brandSlug))
            ->firstOrFail();

        $query = ProductVariant::with([
            'product.brand',
            'product.model',
            'finishRelation',
            'inventories.warehouse',
        ])
        ->whereHas('product', fn($q) => $q->where('brand_id', $brand->id)->where('status', 1));

        // Apply variant-level filters (diameter, width, finish, offset, etc.)
        $this->applyFilters($query, $request);

        $paginator = $query->paginate(20, ['product_variants.*'], 'page', $request->page ?? 1);

        $data         = $paginator->toArray();
        $data['brand'] = ['id' => $brand->id, 'name' => $brand->name, 'slug' => $brand->slug, 'logo' => $brand->logo];
        $data['data'] = $this->transformer->formatVariants($paginator->items(), $dealer);

        return $this->success($data);
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

        $variants = ProductVariant::with([
            'product.brand',
            'product.model',
            'finishRelation',
            'inventories.warehouse',
        ])
        ->whereHas('product', function ($q) use ($brand, $productSlug) {
            $q->where('brand_id', $brand->id)
              ->where('status', 1)
              ->where(function ($inner) use ($productSlug) {
                  $inner->where('name', urldecode($productSlug))
                        ->orWhere('slug', $productSlug);
              });
        })
        ->get();

        if ($variants->isEmpty()) {
            return $this->error('Product not found.', null, 404);
        }

        return $this->success($this->transformer->formatVariants($variants, $dealer));
    }

    /**
     * GET /api/brand-product-more-sizes/{id}/{type}
     * Returns staggered size variants for a product variant on brand pages.
     * Angular calls: getProductMoreSizes(id, type)
     */
    public function productMoreSizes(Request $request, int $variantId, string $type)
    {
        $dealer = $this->dealer();

        $variant = ProductVariant::with(['product'])->findOrFail($variantId);

        $query = ProductVariant::with(['finishRelation', 'inventories.warehouse'])
            ->where('product_id', $variant->product_id)
            ->where('id', '!=', $variantId);

        // Try type-based filter if the column exists (staggered fitment)
        if (in_array($type, ['front', 'rear'])) {
            $query->when(
                \Schema::hasColumn('product_variants', 'fitment_type'),
                fn($q) => $q->where('fitment_type', $type)
            );
        }

        $variants = $query->orderBy('rim_diameter')->orderBy('rim_width')->get();

        return $this->success($this->transformer->formatVariants($variants, $dealer));
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
