<?php

namespace App\Http\Controllers\Wholesale;

use App\Modules\Products\Models\ProductVariant;
use App\Modules\Products\Models\Brand;
use App\Modules\Products\Models\Finish;
use App\Modules\Wholesale\Helpers\WholesaleProductTransformer;
use Illuminate\Http\Request;

/**
 * Wholesale Product Controller
 *
 * Handles product catalog browsing, filtering, and search for the Angular frontend.
 * All queries are scoped through ProductVariant (the primary purchasable unit).
 *
 * Maps to Angular ApiServices:
 *   getAllProducts()      → GET  /api/products
 *   getFilters()          → GET  /api/filters
 *   searchWheels()        → GET  /api/filter-wheels
 *   searchSize()          → POST /api/search-sizes
 *   searchSizeParams()    → GET  /api/search-form-params
 *   searchVehicles()      → POST /api/search-vehicles
 */
class ProductController extends BaseWholesaleController
{
    public function __construct(
        protected WholesaleProductTransformer $transformer
    ) {}

    /**
     * GET /api/products
     * Paginated product variant listing with optional filters.
     * Angular sends filters as query string (via qs.stringify).
     */
    public function index(Request $request)
    {
        $dealer  = $this->dealer();
        $perPage = 20;

        $query = ProductVariant::with([
            'product.brand',
            'product.model',
            'finishRelation',
            'inventories.warehouse',
        ])
        ->whereHas('product', fn($q) => $q->where('status', 1));

        // ── Apply filters ────────────────────────────────────────────────────
        $this->applyVariantFilters($query, $request);

        // ── Sorting ──────────────────────────────────────────────────────────
        match ($request->sort) {
            'price_asc'  => $query->orderBy('uae_retail_price', 'asc'),
            'price_desc' => $query->orderBy('uae_retail_price', 'desc'),
            'name_asc'   => $query->join('products as p', 'p.id', '=', 'product_variants.product_id')
                                  ->orderBy('p.name', 'asc'),
            'newest'     => $query->orderBy('product_variants.created_at', 'desc'),
            default      => $query->orderBy('product_variants.id', 'desc'),
        };

        $paginator = $query->paginate($perPage, ['product_variants.*'], 'page', $request->pagination ?? $request->page ?? 1);

        $data = $paginator->toArray();
        $data['data'] = $this->transformer->formatVariants($paginator->items(), $dealer);

        return $this->success($data);
    }

    /**
     * GET /api/filters
     * Returns available filter options based on current product catalog.
     * Angular uses this to build the filter sidebar dynamically.
     */
    public function filters(Request $request)
    {
        $baseQuery = ProductVariant::whereHas('product', fn($q) => $q->where('status', 1));

        // Optionally scope to a brand
        if ($request->filled('brand_id')) {
            $baseQuery->whereHas('product', fn($q) => $q->where('brand_id', $request->brand_id));
        }

        $diameters   = (clone $baseQuery)->distinct()->pluck('rim_diameter')->filter()->sort()->values();
        $widths      = (clone $baseQuery)->distinct()->pluck('rim_width')->filter()->sort()->values();
        $boltPatterns= (clone $baseQuery)->distinct()->pluck('bolt_pattern')->filter()->sort()->values();
        $offsets     = (clone $baseQuery)->whereNotNull('offset')->selectRaw('MIN(offset) as min_offset, MAX(offset) as max_offset')->first();
        $finishes    = (clone $baseQuery)->with('finishRelation')->get()
                        ->pluck('finishRelation.finish')->filter()->unique()->sort()->values();
        $brands      = Brand::active()->ordered()->get(['id', 'name', 'slug', 'logo']);

        return $this->success([
            'diameters'    => $diameters,
            'widths'       => $widths,
            'bolt_patterns'=> $boltPatterns,
            'offset_range' => $offsets ? ['min' => $offsets->min_offset, 'max' => $offsets->max_offset] : null,
            'finishes'     => $finishes,
            'brands'       => $brands,
        ]);
    }

    /**
     * GET /api/filter-wheels
     * Same as index() but called from Angular searchWheels() with flat query params.
     */
    public function filterWheels(Request $request)
    {
        return $this->index($request);
    }

    /**
     * POST /api/search-sizes
     * Find variants matching an exact size combination.
     * Angular sends: { rim_diameter, rim_width, bolt_pattern }
     */
    public function searchSizes(Request $request)
    {
        $dealer = $this->dealer();

        $request->validate([
            'rim_diameter' => 'sometimes|numeric',
            'rim_width'    => 'sometimes|numeric',
            'bolt_pattern' => 'sometimes|string',
        ]);

        $query = ProductVariant::with(['product.brand', 'product.model', 'finishRelation', 'inventories.warehouse'])
            ->whereHas('product', fn($q) => $q->where('status', 1));

        if ($request->filled('rim_diameter')) {
            $query->where('rim_diameter', $request->rim_diameter);
        }
        if ($request->filled('rim_width')) {
            $query->where('rim_width', $request->rim_width);
        }
        if ($request->filled('bolt_pattern')) {
            $query->where('bolt_pattern', $request->bolt_pattern);
        }

        $variants = $query->get();

        return $this->success($this->transformer->formatVariants($variants, $dealer));
    }

    /**
     * GET /api/search-form-params
     * Returns all available size options for the search form dropdowns.
     */
    public function searchSizeParams(Request $request)
    {
        $base = ProductVariant::whereHas('product', fn($q) => $q->where('status', 1));

        return $this->success([
            'diameters'    => (clone $base)->distinct()->orderBy('rim_diameter')->pluck('rim_diameter')->filter()->values(),
            'widths'       => (clone $base)->distinct()->orderBy('rim_width')->pluck('rim_width')->filter()->values(),
            'bolt_patterns'=> (clone $base)->distinct()->orderBy('bolt_pattern')->pluck('bolt_pattern')->filter()->values(),
        ]);
    }

    /**
     * POST /api/search-vehicles
     * Filter variants by vehicle bolt pattern + diameter (fitment-based search).
     * Frontend calls wheel-size.com directly, then passes bolt_pattern/rim specs here.
     */
    public function searchVehicles(Request $request)
    {
        $dealer = $this->dealer();
        $page   = $request->get('pagination', 1);

        $query = ProductVariant::with(['product.brand', 'product.model', 'finishRelation', 'inventories.warehouse'])
            ->whereHas('product', fn($q) => $q->where('status', 1));

        // Filter by bolt pattern from vehicle fitment (primary fitment filter)
        if ($request->filled('bolt_pattern')) {
            $query->where('bolt_pattern', $request->bolt_pattern);
        }

        // Optional diameter constraint from vehicle fitment
        if ($request->filled('rim_diameter')) {
            $query->where('rim_diameter', $request->rim_diameter);
        }

        $paginator = $query->paginate(20, ['*'], 'page', $page);
        $data      = $paginator->toArray();
        $data['data'] = $this->transformer->formatVariants($paginator->items(), $dealer);

        return $this->success($data);
    }

    // ─── Private: Shared filter logic ────────────────────────────────────────

    /**
     * Apply query string filters to a ProductVariant query builder.
     * Called from index(), filterWheels().
     */
    private function applyVariantFilters($query, Request $request): void
    {
        if ($request->filled('brand_id')) {
            $query->whereHas('product', fn($q) => $q->where('brand_id', $request->brand_id));
        }
        if ($request->filled('model_id')) {
            $query->whereHas('product', fn($q) => $q->where('model_id', $request->model_id));
        }
        if ($request->filled('finish_id')) {
            $query->where(function ($q) use ($request) {
                $q->where('finish_id', $request->finish_id)
                  ->orWhereHas('product', fn($p) => $p->where('finish_id', $request->finish_id));
            });
        }
        if ($request->filled('rim_diameter')) {
            $query->where('rim_diameter', $request->rim_diameter);
        }
        if ($request->filled('rim_width')) {
            $query->where('rim_width', $request->rim_width);
        }
        if ($request->filled('bolt_pattern')) {
            $query->where('bolt_pattern', $request->bolt_pattern);
        }
        if ($request->filled('offset_min') || $request->filled('offset_max')) {
            $query->whereBetween('offset', [
                $request->get('offset_min', -100),
                $request->get('offset_max', 100),
            ]);
        }
        if ($request->filled('clearance') && $request->clearance) {
            $query->where('clearance_corner', true);
        }
        if ($request->filled('search')) {
            $term = '%' . $request->search . '%';
            $query->where(function ($q) use ($term) {
                $q->where('product_variants.sku', 'like', $term)
                  ->orWhereHas('product', fn($p) => $p->where('name', 'like', $term));
            });
        }
    }
}
