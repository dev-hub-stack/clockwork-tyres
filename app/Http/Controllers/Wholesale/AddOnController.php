<?php

namespace App\Http\Controllers\Wholesale;

use App\Modules\Products\Models\AddOn;
use App\Modules\Products\Models\AddOnCategory;
use App\Modules\Inventory\Models\ProductInventory;
use App\Modules\Wholesale\Helpers\WholesaleProductTransformer;
use Illuminate\Http\Request;

/**
 * Wholesale Add-On Controller
 *
 * Returns add-ons (accessories like lug nuts, center caps, TPMS) with
 * dealer-specific pricing applied via DealerPricingService.
 *
 * Maps to Angular ApiServices:
 *   getAddsOnByProduct(prodId)          → GET /api/addons/{productId}
 *   getAddOnCategories()                → GET /api/add-ons/categories
 *   getAddOnsByCategory(slug, params)   → GET /api/add-ons/{slug}/get
 *   getAddOnFilters(params, catSlug)    → GET /api/add-on-filters/{slug}/get
 *   getAddonByAccessory(id)             → GET /api/add-on/{id}
 *   notifyAddonsWhenAvailable(addonId)  → GET /api/dealer/notify/restock-addon/{id}
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

    /**
     * GET /api/add-ons/categories
     * Returns all active addon categories (id, name, slug) for the
     * Angular accessories page tab navigation.
     */
    public function categories(Request $request)
    {
        $categories = AddOnCategory::active()
            ->ordered()
            ->get(['id', 'name', 'slug', 'display_name', 'image']);

        return $this->success($categories);
    }

    /**
     * GET /api/add-ons/{slug}/get
     * Paginated list of addons for a specific category slug.
     * Supports: perPage, sortBy (newest|price_low_to_high|price_high_to_low),
     *           suppliers, thread_size, color, bolt_pattern, etc.
     */
    public function byCategory(Request $request, string $slug)
    {
        $dealer   = $this->dealer();
        $perPage  = (int) $request->get('perPage', 30);
        $sortBy   = $request->get('sortBy', 'newest');

        $query = AddOn::with(['category'])
            ->whereHas('category', fn($q) => $q->where('slug', $slug))
            ->whereNull('addons.deleted_at');

        // --- Filter parameters --------------------------------------------------
        foreach (['thread_size', 'color', 'bolt_pattern', 'center_bore', 'ext_center_bore',
                  'width', 'thread_length', 'lug_net_length', 'lug_net_diameter',
                  'lug_bolt_diameter'] as $field) {
            if ($val = $request->get($field)) {
                $values = array_filter(explode(',', $val));
                if ($values) {
                    $query->whereIn($field, $values);
                }
            }
        }

        if ($supplierIds = $request->get('suppliers')) {
            $ids = array_filter(explode(',', $supplierIds));
            if ($ids) {
                $query->whereIn('addons.id', $ids);
            }
        }

        // --- Sorting ------------------------------------------------------------
        match ($sortBy) {
            'price_low_to_high'  => $query->orderBy('price', 'asc'),
            'price_high_to_low'  => $query->orderBy('price', 'desc'),
            default              => $query->orderBy('addons.created_at', 'desc'),
        };

        $paginated = $query->paginate($perPage);

        $items = collect($paginated->items())
            ->map(fn($addon) => $this->transformer->formatAddon($addon, $dealer));

        return $this->success([
            'data'          => $items->values(),
            'total'         => $paginated->total(),
            'from'          => $paginated->firstItem(),
            'to'            => $paginated->lastItem(),
            'per_page'      => $paginated->perPage(),
            'current_page'  => $paginated->currentPage(),
            'last_page'     => $paginated->lastPage(),
            'next_page_url' => $paginated->nextPageUrl(),
        ]);
    }

    /**
     * GET /api/add-on-filters/{slug}/get
     * Returns distinct filter values for the given category slug.
     * Angular AccessoriesFiltersComponent uses these to populate checkboxes.
     */
    public function filters(Request $request, string $slug)
    {
        $baseQuery = AddOn::whereHas('category', fn($q) => $q->where('slug', $slug))
            ->whereNull('addons.deleted_at');

        $distinct = fn(string $col) => (clone $baseQuery)
            ->whereNotNull($col)
            ->where($col, '!=', '')
            ->pluck($col)
            ->unique()
            ->values()
            ->map(fn($v) => ['value' => (string) $v])
            ->toArray();

        $filters = [
            'thread_size'      => $distinct('thread_size'),
            'color'            => $distinct('color'),
            'bolt_pattern'     => $distinct('bolt_pattern'),
            'lug_net_length'   => $distinct('lug_nut_length'),
            'lug_net_diameter' => $distinct('lug_nut_diameter'),
            'lug_bolt_diameter'=> $distinct('lug_bolt_diameter'),
            'thread_length'    => $distinct('thread_length'),
            'ext_center_bore'  => $distinct('ext_center_bore'),
            'center_bore'      => $distinct('center_bore'),
            'width'            => $distinct('width'),
            'suppliers'        => (clone $baseQuery)
                ->select('addons.id as id', 'addons.part_number as value')
                ->whereNotNull('addons.part_number')
                ->get()
                ->map(fn($r) => ['id' => $r->id, 'value' => $r->part_number])
                ->toArray(),
        ];

        return $this->success(['filters' => $filters]);
    }

    /**
     * GET /api/add-on/{id}
     * Returns warehouse inventory breakdown for an addon.
     * Used by the stock modal on the accessories listing page.
     */
    public function inventory(Request $request, int $id)
    {
        $addon = AddOn::findOrFail($id);

        $inventory = ProductInventory::with('warehouse')
            ->where('add_on_id', $id)
            ->get()
            ->map(fn($inv) => [
                'warehouse_name' => $inv->warehouse?->warehouse_name ?? 'Stock',
                'quantity'       => (int) $inv->quantity,
                'eta_qty'        => (int) ($inv->eta_qty ?? 0),
                'eta'            => $inv->eta,
                'id'             => $inv->id,
            ]);

        return $this->success([
            'addon'     => ['id' => $addon->id, 'title' => $addon->title],
            'inventory' => $inventory,
        ]);
    }

    /**
     * GET /api/dealer/notify/restock-addon/{id}
     * Saves dealer's email against the addon for restock notification.
     */
    public function notifyRestock(Request $request, int $id)
    {
        $dealer = $this->dealer();
        $addon  = AddOn::findOrFail($id);

        $emails = $addon->notify_restock ?? [];
        $email  = $dealer?->email ?? $request->user()?->email ?? null;

        if ($email && ! in_array($email, $emails)) {
            $emails[] = $email;
            $addon->update(['notify_restock' => $emails]);
        }

        return $this->success(null, 'You will be notified when this item is back in stock.');
    }
}
