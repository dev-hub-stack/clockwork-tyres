<?php

namespace App\Http\Controllers\Wholesale;

use App\Modules\Products\Models\ProductVariant;
use App\Modules\Products\Models\Product;
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
        $perPage = min((int) ($request->perPage ?? $request->per_page ?? 20), 100);

        // Optimization: Unified Eager Loading
        $query = ProductVariant::with([
            'product.brand',
            'product.model',
            'finishRelation',
            'inventories.warehouse:id,warehouse_name,code',
        ])
        ->join('products', 'products.id', '=', 'product_variants.product_id')
        ->where('products.status', 1)
        ->where('products.available_on_wholesale', true)
        ->select('product_variants.*');

        $this->applyVariantFilters($query, $request);

        // Sorting
        match ($request->sort) {
            'price_asc'  => $query->orderBy('product_variants.uae_retail_price', 'asc'),
            'price_desc' => $query->orderBy('product_variants.uae_retail_price', 'desc'),
            'name_asc'   => $query->orderBy('products.name', 'asc'),
            'newest'     => $query->orderBy('product_variants.created_at', 'desc'),
            default      => $query->orderBy('product_variants.id', 'desc'),
        };

        $page      = $request->pagination ?? $request->page ?? 1;
        $cacheKey  = 'products_' . ($dealer?->id ?? 'guest') . '_' . md5(serialize($request->except(['_token'])));
        
        $paginator = \Cache::remember($cacheKey, 300, function () use ($query, $perPage, $page) {
            return $query->paginate($perPage, ['product_variants.*'], 'page', $page);
        });

        $data         = $paginator->toArray();
        $data['data'] = $this->transformer->formatVariants($paginator->items(), $dealer);

        return $this->success([
            'products' => $data,
            'filters'  => $this->getFormattedFilters($request)
        ]);
    }

    /**
     * GET /api/filters
     * Returns available filter options based on current product catalog.
     * Angular uses this to build the filter sidebar dynamically.
     */
    public function filters(Request $request)
    {
        return $this->success([
            'filters' => $this->getFormattedFilters($request)
        ]);
    }

    /**
     * Helper to get and format filters for the frontend.
     */
    protected function getFormattedFilters(Request $request): array
    {
        $brandId = $request->brand_id;
        $cacheKey = "wholesale_filters_" . ($brandId ?? 'all');

        return \Cache::remember($cacheKey, 600, function () use ($brandId) {
            $baseQuery = ProductVariant::active();

            if ($brandId) {
                $baseQuery->whereHas('product', fn($q) => $q->where('brand_id', $brandId)->where('available_on_wholesale', true));
            } else {
                $baseQuery->whereHas('product', fn($q) => $q->where('available_on_wholesale', true));
            }

            // Optimize: Use distinct on variant table where possible
            $diameters    = (clone $baseQuery)->distinct()->orderBy('rim_diameter')->pluck('rim_diameter')->filter()->values();
            $widths       = (clone $baseQuery)->distinct()->orderBy('rim_width')->pluck('rim_width')->filter()->values();
            $boltPatterns = (clone $baseQuery)->distinct()->orderBy('bolt_pattern')->pluck('bolt_pattern')->filter()->values();
            $offsets      = (clone $baseQuery)->whereNotNull('offset')->selectRaw('MIN(offset) as min_offset, MAX(offset) as max_offset')->first();

            $finishQuery = (clone $baseQuery)
                ->join('products', 'product_variants.product_id', '=', 'products.id')
                ->join('finishes', 'products.finish_id', '=', 'finishes.id')
                ->whereNotNull('products.finish_id')
                ->distinct()
                ->orderBy('finishes.finish')
                ->pluck('finishes.finish');

            $finishes = $finishQuery->filter()->values();

            $constructions = Product::active()->whereNotNull('construction')->distinct()->pluck('construction')->filter()->sort()->values();
            $brands       = Brand::active()->ordered()->get(['id', 'name', 'slug', 'logo']);

            $formatter = fn($items) => $items->map(fn($item) => ['name' => (string)$item, 'checked' => false]);

            return [
                'rim_diameter'  => $formatter($diameters)->all(),
                'rim_width'     => $formatter($widths)->all(),
                'bolt_pattern'  => $formatter($boltPatterns)->all(),
                'offset'        => $offsets ? [['name' => $offsets->min_offset . ' to ' . $offsets->max_offset, 'checked' => false]] : [],
                'finish'        => $formatter($finishes)->all(),
                'brand'         => $formatter($brands->pluck('name'))->all(),
                'construction'  => $formatter($constructions)->all(),
            ];
        });
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
            ->whereHas('product', fn($q) => $q->where('status', 1)->where('available_on_wholesale', true));

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
        $base = ProductVariant::whereHas('product', fn($q) => $q->where('status', 1)->where('available_on_wholesale', true));

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
            ->whereHas('product', fn($q) => $q->where('status', 1)->where('available_on_wholesale', true));

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

        return $this->success([
            'products' => $data,
            'filters'  => $this->getFormattedFilters($request)
        ]);
    }

    // ─── Private: Shared filter logic ────────────────────────────────────────

    /**
     * Apply query string filters to a ProductVariant query builder.
     * Called from index(), filterWheels().
     */
    private function applyVariantFilters($query, Request $request): void
    {
        // Optimization: Use direct joins for filters instead of subqueries
        if ($request->filled('brand_id')) {
            $brandIds = explode(',', $request->brand_id);
            $query->whereIn('products.brand_id', $brandIds);
        }
        if ($request->filled('brand')) {
            $brandNames = explode(',', $request->brand);
            // Join brands only if not already joined or needed
            $query->join('brands', 'brands.id', '=', 'products.brand_id')
                  ->whereIn('brands.name', $brandNames);
        }
        if ($request->filled('model_id')) {
            $query->where('products.model_id', $request->model_id);
        }
        if ($request->filled('finish_id')) {
            $query->where(function ($q) use ($request) {
                // finish_id check on both tables is common in Tunerstop schema
                $q->where('product_variants.finish_id', $request->finish_id)
                  ->orWhere('products.finish_id', $request->finish_id);
            });
        }
        if ($request->filled('finish')) {
            $values = explode(',', $request->finish);
            $finishIds = \App\Modules\Products\Models\Finish::where(function($q) use ($values) {
                foreach($values as $v) $q->orWhere('finish', 'LIKE', '%' . trim($v) . '%');
            })->pluck('id');
            $query->whereIn('products.finish_id', $finishIds);
        }
        if ($request->filled('construction')) {
            $values = explode(',', $request->construction);
            $query->whereIn('products.construction', $values);
        }

        $rimDiameter = $request->rim_diameter ?? $request->front_rim_diameter;
        if ($rimDiameter) {
            $query->where('product_variants.rim_diameter', $rimDiameter);
        }
        $rimWidth = $request->rim_width ?? $request->front_rim_width;
        if ($rimWidth) {
            $query->where('product_variants.rim_width', $rimWidth);
        }
        $boltPattern = $request->bolt_pattern ?? $request->front_bolt_pattern;
        if ($boltPattern) {
            $query->where('product_variants.bolt_pattern', $boltPattern);
        }

        if ($request->filled('offset_min') || $request->filled('offset_max')) {
            $query->whereBetween('product_variants.offset', [
                $request->get('offset_min', -200),
                $request->get('offset_max', 200),
            ]);
        }
        if ($request->filled('clearance') && $request->clearance) {
            $query->where('product_variants.clearance_corner', true);
        }
        if ($request->filled('search')) {
            $term = '%' . $request->search . '%';
            $query->where(function ($q) use ($term) {
                $q->where('product_variants.sku', 'like', $term)
                  ->orWhere('products.name', 'like', $term);
            });
        }
    }

    /**
     * GET /api/addons/{productId}
     * Retrieve suggested addons (lug nuts, TPMS, etc.) for a specific product.
     * Maps to Angular: apiService.getAddsOnByProduct()
     */
    public function getAddons($productId)
    {
        $dealer = $this->dealer();
        
        // Find add-ons explicitly linked to this product via pivot
        $linkedAddons = \App\Modules\Products\Models\AddOn::with('inventories')
            ->whereHas('products', function ($q) use ($productId) {
                $q->where('product_id', $productId);
            })->get();

        // If no specifically linked addons, fallback to global addons (products = 1)
        if ($linkedAddons->isEmpty()) {
            $linkedAddons = \App\Modules\Products\Models\AddOn::with('inventories')
                ->where('products', 1)->get();
        }

        $formatted = $linkedAddons->map(function ($addon) use ($dealer) {
            return $this->transformer->formatAddon($addon, $dealer);
        });

        return $this->success($formatted);
    }
}
