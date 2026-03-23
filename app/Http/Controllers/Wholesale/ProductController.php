<?php

namespace App\Http\Controllers\Wholesale;

use App\Modules\Inventory\Models\ProductInventory;
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
            'inventories.warehouse:id,warehouse_name,code,is_primary',
        ])
        ->join('products', 'products.id', '=', 'product_variants.product_id')
        ->where('products.status', 1)
        ->where('products.available_on_wholesale', true)
        ->select('product_variants.*');

        $this->applyVariantFilters($query, $request);

        // For search-by-size with rear dimensions: only return front variants that also have a matching rear variant
        if ($request->get('search_by_size') && $request->filled('rear_rim_diameter')) {
            $rearDiameter    = $request->rear_rim_diameter;
            $rearWidth       = $request->rear_rim_width;
            $rearBoltPattern = $request->front_bolt_pattern ?? $request->bolt_pattern;
            $rearOffset      = $this->parseOffsetParam($request->get('rear_offset'));
            $query->whereExists(function ($sub) use ($rearDiameter, $rearWidth, $rearBoltPattern, $rearOffset) {
                $sub->selectRaw('1')
                    ->from('product_variants as pv_rear')
                    ->whereColumn('pv_rear.product_id', 'product_variants.product_id')
                    ->where('pv_rear.rim_diameter', $rearDiameter)
                    ->when($rearWidth, fn($q) => $q->where('pv_rear.rim_width', $rearWidth))
                    ->when($rearBoltPattern, fn($q) => $q->where('pv_rear.bolt_pattern', $rearBoltPattern))
                    ->when($rearOffset, fn($q) => $q->whereBetween('pv_rear.offset', [$rearOffset['min'], $rearOffset['max']]));
            });
        }

        // Priority Sorting: In-Stock items first
        $query->orderByRaw('(SELECT SUM(quantity) FROM product_inventories WHERE product_variants.id = product_inventories.product_variant_id) > 0 DESC');

        // User Sorting — accept both CRM-style `sort` and tunerstop-style `sortBy`
        $sortParam = $request->sort ?? $request->sortBy;
        match ($sortParam) {
            'price_asc', 'price_low_to_high'   => $query->orderBy('product_variants.uae_retail_price', 'asc'),
            'price_desc', 'price_high_to_low'  => $query->orderBy('product_variants.uae_retail_price', 'desc'),
            'name_asc', 'brand'                => $query->orderBy('products.name', 'asc'),
            'newest'                           => $query->orderBy('product_variants.created_at', 'desc'),
            default                            => $query->orderBy('product_variants.uae_retail_price', 'asc'),
        };

        $page      = $request->page ?? $request->pagination ?? 1;
        $cacheKey  = 'products_' . ($dealer?->id ?? 'guest') . '_' . md5(serialize($request->except(['_token'])));
        
        $paginator = \Cache::remember($cacheKey, 300, function () use ($query, $perPage, $page) {
            return $query->paginate($perPage, ['product_variants.*'], 'page', $page);
        });

        $data      = $paginator->toArray();
        $formatted = $this->transformer->formatVariants($paginator->items(), $dealer);

        // Pair rear variants for search-by-size queries
        if ($request->get('search_by_size') && $request->filled('rear_rim_diameter')) {
            $formatted = $this->attachRearVariants($formatted, $request, $dealer);
        }

        $data['data'] = $formatted;

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
            $baseQuery = ProductVariant::join('products', 'products.id', '=', 'product_variants.product_id')
                ->where('products.status', 1)
                ->where('products.available_on_wholesale', true);

            if ($brandId) {
                $baseQuery->where('products.brand_id', $brandId);
            }

            // Optimize: Use distinct on variant table where possible
            // Note: Use select() to avoid column collisions before pluck() if needed, 
            // but pluck works on the column name so should be fine.
            $diameters    = (clone $baseQuery)->distinct()->orderBy('rim_diameter')->pluck('rim_diameter')->filter()->values();
            $widths       = (clone $baseQuery)->distinct()->orderBy('rim_width')->pluck('rim_width')->filter()->values();
            $boltPatterns = (clone $baseQuery)->distinct()->orderBy('bolt_pattern')->pluck('bolt_pattern')->filter()->values();
            
            // For aggregates, we can keep the join
            $offsets      = (clone $baseQuery)->whereNotNull('product_variants.offset')
                ->selectRaw('MIN(product_variants.offset) as min_offset, MAX(product_variants.offset) as max_offset')
                ->first();

            $finishQuery = (clone $baseQuery)
                ->join('finishes', 'products.finish_id', '=', 'finishes.id')
                ->whereNotNull('products.finish_id')
                ->distinct()
                ->orderBy('finishes.finish')
                ->pluck('finishes.finish');

            $finishes = $finishQuery->filter()->values();

            // Brands and constructions can be pulled directly from Product/Brand with active scopes
            $constructions = Product::active()
                ->where('available_on_wholesale', true)
                ->whereNotNull('construction')
                ->distinct()
                ->pluck('construction')
                ->filter()
                ->sort()
                ->values();
                
            $brandIds = Product::active()
                ->where('available_on_wholesale', true)
                ->distinct()
                ->pluck('brand_id')
                ->filter()
                ->values();

            $brands = Brand::active()
                ->ordered()
                ->whereIn('id', $brandIds)
                ->get(['id', 'name', 'slug', 'logo']);

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
     * POST /api/product/inventory
     * Returns warehouse inventory rows for the stock selection modal.
     */
    public function inventory(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'variant_id' => 'required|integer|exists:product_variants,id',
        ]);

        $variant = ProductVariant::query()
            ->whereKey($validated['variant_id'])
            ->where('product_id', $validated['product_id'])
            ->first();

        if (! $variant) {
            return $this->error('Product variant does not belong to the requested product.', null, 404);
        }

        $dealer = $this->dealer();
        $inventoryQuery = ProductInventory::query()
            ->with('warehouse:id,warehouse_name,code,lat,lng,is_primary')
            ->join('warehouses', 'warehouses.id', '=', 'product_inventories.warehouse_id')
            ->select('product_inventories.*')
            ->where('product_id', $validated['product_id'])
            ->where('product_variant_id', $validated['variant_id'])
            ->where('warehouses.code', '!=', 'NON-STOCK')
            ->where(function ($query) {
                $query->where('quantity', '>', 0)
                    ->orWhere('eta_qty', '>', 0)
                    ->orWhereNotNull('eta');
            })
            ->orderByRaw("CASE
                WHEN warehouses.is_primary = 1 AND product_inventories.quantity > 0 THEN 0
                WHEN product_inventories.quantity > 0 THEN 1
                WHEN warehouses.is_primary = 1 THEN 2
                ELSE 3
            END")
            ->orderBy('warehouses.warehouse_name');

        if ($dealer?->lat && $dealer?->lng) {
            $inventoryQuery->selectRaw(
                    '( 6371 * acos( cos( radians(?) ) * cos( radians( warehouses.lat ) ) * cos( radians( warehouses.lng ) - radians(?) ) + sin( radians(?) ) * sin( radians( warehouses.lat ) ) ) ) AS distance',
                    [$dealer->lat, $dealer->lng, $dealer->lat]
                )
                ->orderByRaw('distance IS NULL')
                ->orderBy('distance');
        }

        $inventory = $inventoryQuery
            ->get()
            ->map(function (ProductInventory $item) {
                return [
                    'id' => $item->warehouse_id,
                    'warehouse_id' => $item->warehouse_id,
                    'code' => $item->warehouse?->code,
                    'warehouse_name' => $item->warehouse?->warehouse_name ?? $item->warehouse?->name ?? 'Warehouse',
                    'is_primary' => (bool) ($item->warehouse?->is_primary ?? false),
                    'quantity' => (int) $item->quantity,
                    'eta' => $item->eta,
                    'eta_qty' => (int) $item->eta_qty,
                ];
            })
            ->all();

        return $this->success($inventory);
    }

    /**
     * POST /api/search-sizes
     * Find variants matching an exact size combination.
     * Accepts both legacy wholesale keys and tunerstop-style keys.
     */
    public function searchSizes(Request $request)
    {
        $dealer = $this->dealer();

        $rimDiameter = $request->input('rim_diameter', $request->input('diameter'));
        $rimWidth = $request->input('rim_width', $request->input('width'));
        $productId = $request->input('product_id');
        $modelId = $request->input('model_id');
        $brandId = $request->input('brand_id');
        $resolvedProductId = null;

        $request->validate([
            'rim_diameter'  => 'sometimes|numeric',
            'diameter'      => 'sometimes|numeric',
            'rim_width'     => 'sometimes|numeric',
            'width'         => 'sometimes|numeric',
            'bolt_pattern'  => 'sometimes|string',
            'product_id'    => 'sometimes|integer',
            'model_id'      => 'sometimes|integer',
            'brand_id'      => 'sometimes|integer',
            'rear_diameter' => 'sometimes|numeric',
            'rear_width'    => 'sometimes|numeric',
        ]);

        if ($productId) {
            $resolvedProductId = Product::query()->whereKey($productId)->value('id');

            if (!$resolvedProductId) {
                $resolvedProductId = ProductVariant::query()->whereKey($productId)->value('product_id');
            }

            if (!$resolvedProductId) {
                return $this->success([]);
            }
        }

        $query = ProductVariant::with(['product.brand', 'product.model', 'finishRelation', 'inventories.warehouse'])
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->where('products.status', 1)
            ->where('products.available_on_wholesale', true);

        if ($rimDiameter !== null && $rimDiameter !== '') {
            $query->where('product_variants.rim_diameter', $rimDiameter);
        }
        if ($rimWidth !== null && $rimWidth !== '') {
            $query->where('product_variants.rim_width', $rimWidth);
        }
        if ($request->filled('bolt_pattern')) {
            $query->where('product_variants.bolt_pattern', $request->bolt_pattern);
        }
        if ($resolvedProductId) {
            $query->where('products.id', $resolvedProductId);
        }
        if ($modelId) {
            $query->where('products.model_id', $modelId);
        }
        if ($brandId) {
            $query->where('products.brand_id', $brandId);
        }

        $variants = $query->select('product_variants.*')->get();
        $frontFormatted = $this->transformer->formatVariants($variants, $dealer);

        // If rear dimensions are provided, query rear variants separately
        $rearDiameter = $request->input('rear_diameter');
        $rearWidth = $request->input('rear_width');

        if ($rearDiameter && $rearWidth) {
            $rearQuery = ProductVariant::with(['product.brand', 'product.model', 'finishRelation', 'inventories.warehouse'])
                ->join('products', 'products.id', '=', 'product_variants.product_id')
                ->where('products.status', 1)
                ->where('products.available_on_wholesale', true)
                ->where('product_variants.rim_diameter', $rearDiameter)
                ->where('product_variants.rim_width', $rearWidth);

            if ($request->filled('bolt_pattern')) {
                $rearQuery->where('product_variants.bolt_pattern', $request->bolt_pattern);
            }
            if ($resolvedProductId) {
                $rearQuery->where('products.id', $resolvedProductId);
            }
            if ($modelId) {
                $rearQuery->where('products.model_id', $modelId);
            }
            if ($brandId) {
                $rearQuery->where('products.brand_id', $brandId);
            }

            $rearVariants = $rearQuery->select('product_variants.*')->get();

            return $this->success([
                'front' => $frontFormatted,
                'rear'  => $this->transformer->formatVariants($rearVariants, $dealer),
            ]);
        }

        return $this->success($frontFormatted);
    }

    /**
     * GET /api/search-form-params
     * Returns all available size options for the search form dropdowns.
     */
    public function searchSizeParams(Request $request)
    {
        $base = ProductVariant::join('products', 'products.id', '=', 'product_variants.product_id')
            ->where('products.status', 1)
            ->where('products.available_on_wholesale', true);

        $diameters    = (clone $base)->distinct()->orderBy('rim_diameter')->pluck('rim_diameter')->filter()->values();
        $widths       = (clone $base)->distinct()->orderBy('rim_width')->pluck('rim_width')->filter()->values();
        $boltPatterns = (clone $base)->distinct()->orderBy('bolt_pattern')->pluck('bolt_pattern')->filter()->values();

        $formatter = fn($items) => $items->map(fn($item) => ['name' => (string)$item]);

        return $this->success([
            'diameter'     => $formatter($diameters)->all(),
            'width'        => $formatter($widths)->all(),
            'bolt_pattern' => $formatter($boltPatterns)->all(),
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
        $page   = $request->page ?? $request->pagination ?? 1;

        $query = ProductVariant::with(['product.brand', 'product.model', 'finishRelation', 'inventories.warehouse'])
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->where('products.status', 1)
            ->where('products.available_on_wholesale', true);

        // Filter by bolt pattern from vehicle fitment (primary fitment filter)
        if ($request->filled('bolt_pattern')) {
            $query->where('bolt_pattern', $request->bolt_pattern);
        }

        // Optional diameter constraint from vehicle fitment
        if ($request->filled('rim_diameter')) {
            $query->where('rim_diameter', $request->rim_diameter);
        }

        $paginator = $query->select('product_variants.*')->paginate(20, ['*'], 'page', $page);
        $data      = $paginator->toArray();
        $data['data'] = $this->transformer->formatVariants($paginator->items(), $dealer);

        return $this->success([
            'products' => $data,
            'filters'  => $this->getFormattedFilters($request)
        ]);
    }

    // ─── Private: Shared filter logic ────────────────────────────────────────

    /**
     * Save the authenticated dealer's email for restock notification on a variant.
     * GET /api/products/notify-restock/{variantId}
     */
    public function notifyRestock(Request $request, int $variantId)
    {
        $variant = ProductVariant::findOrFail($variantId);
        $dealer  = $this->dealer();
        $email   = $dealer?->email ?? $request->user()?->email ?? null;

        if ($email) {
            $emails = $variant->notify_restock ?? [];
            if (! in_array($email, $emails)) {
                $emails[] = $email;
                $variant->update(['notify_restock' => $emails]);
            }
        }

        return $this->success(null, 'You will be notified when this item is back in stock.');
    }

    /**
     * Pair rear variants with front variants for search-by-size results.
     * For each front variant found, looks up a matching rear variant of the same product model.
     * Adds rear_* properties to the formatted data array.
     */
    private function attachRearVariants(array $formatted, Request $request, $dealer): array
    {
        $rearDiameter = $request->rear_rim_diameter;
        $rearWidth    = $request->rear_rim_width;

        // Batch-fetch rear variants for all matching product_ids in one query
        $productIds = array_unique(array_column($formatted, 'product_id'));

        $rearBoltPattern = $request->front_bolt_pattern ?? $request->bolt_pattern;
        $rearOffset      = $this->parseOffsetParam($request->get('rear_offset'));

        $rearVariants = ProductVariant::with(['inventories.warehouse'])
            ->whereIn('product_id', $productIds)
            ->where('rim_diameter', $rearDiameter)
            ->when($rearWidth, fn($q) => $q->where('rim_width', $rearWidth))
            ->when($rearBoltPattern, fn($q) => $q->where('bolt_pattern', $rearBoltPattern))
            ->when($rearOffset, fn($q) => $q->whereBetween('offset', [$rearOffset['min'], $rearOffset['max']]))
            ->get()
            ->keyBy('product_id');

        foreach ($formatted as $i => $variant) {
            $rearVariant = $rearVariants->get($variant['product_id'] ?? null);
            if (!$rearVariant) continue;

            $rearPriceResult = $this->transformer->publicDealerPrice($rearVariant, $dealer);
            $rearStock = $rearVariant->inventories->sum('quantity');

            $formatted[$i]['rear_sku']               = $rearVariant->sku;
            $formatted[$i]['rear_size']               = $rearVariant->size ?? ($rearVariant->rim_diameter . 'x' . $rearVariant->rim_width);
            $formatted[$i]['rear_rim_diameter']       = $rearVariant->rim_diameter;
            $formatted[$i]['rear_rim_width']          = $rearVariant->rim_width;
            $formatted[$i]['rear_bolt_pattern']       = $rearVariant->bolt_pattern;
            $formatted[$i]['rear_offset']             = $rearVariant->offset;
            $formatted[$i]['rear_us_retail_price']    = (float) ($rearVariant->us_retail_price ?? 0);
            $formatted[$i]['rear_discounted_price']   = $rearPriceResult['final_price'];
            $formatted[$i]['rear_price']              = $rearPriceResult['final_price'];
            $formatted[$i]['rear_product_id']         = $rearVariant->product_id;
            $formatted[$i]['rear_variant_id']         = $rearVariant->id;
            $formatted[$i]['rear_varient_id']         = $rearVariant->id; // legacy spelling
            $formatted[$i]['rear_in_stock_quantity']  = $rearStock;
            $formatted[$i]['rear_total_stock']        = $rearStock;
        }

        return $formatted;
    }

    /**
     * Parse tunerstop-style offset "XtoY" string into min/max array, or null.
     */
    private function parseOffsetParam(?string $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (str_contains($value, 'to')) {
            $parts = explode('to', $value, 2);
            $min = is_numeric($parts[0]) ? (float) $parts[0] : null;
            $max = is_numeric($parts[1]) ? (float) $parts[1] : null;
            if ($min !== null || $max !== null) {
                return ['min' => $min ?? -200, 'max' => $max ?? 200];
            }
        }
        if (is_numeric($value)) {
            return ['min' => (float) $value, 'max' => (float) $value];
        }
        return null;
    }

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

        // Accept offset_min/offset_max, min_offset/max_offset, or tunerstop-style "XtoY" in `offset`
        $offsetMin = $request->get('offset_min') ?? $request->get('min_offset');
        $offsetMax = $request->get('offset_max') ?? $request->get('max_offset');
        if ($offsetMin === null && $offsetMax === null) {
            $parsed = $this->parseOffsetParam($request->get('offset'));
            if ($parsed) {
                $offsetMin = $parsed['min'];
                $offsetMax = $parsed['max'];
            }
        }
        if ($offsetMin !== null || $offsetMax !== null) {
            $query->whereBetween('product_variants.offset', [
                (float) ($offsetMin ?? -200),
                (float) ($offsetMax ?? 200),
            ]);
        }
        if ($request->filled('hub_bore') || $request->filled('centre_bore')) {
            $hubBore = $request->get('hub_bore') ?? $request->get('centre_bore');
            $query->where('product_variants.hub_bore', '>=', (float) $hubBore);
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

        if ($request->filled('min_quantity')) {
            $minQty = (int)$request->min_quantity;
            $query->whereRaw('(SELECT SUM(quantity) FROM product_inventories WHERE product_variants.id = product_inventories.product_variant_id) >= ?', [$minQty]);
        }

        if ($request->get('hide_out_of_stock') == '1' || $request->get('hide_out_of_stock') === 'true') {
             $query->whereRaw('(SELECT SUM(quantity) FROM product_inventories WHERE product_variants.id = product_inventories.product_variant_id) > 0');
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
