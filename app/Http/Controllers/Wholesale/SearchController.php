<?php

namespace App\Http\Controllers\Wholesale;

use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductVariant;
use App\Modules\Products\Models\AddOn;
use Illuminate\Http\Request;

/**
 * Search Controller (Phase 5)
 *
 * Maps to Angular:
 *   search(query) → GET /api/search?query=...
 *
 * Provides a global keyword search across brands, products, and variants.
 */
class SearchController extends BaseWholesaleController
{
    /**
     * GET /api/search
     * Search products by keyword (title, brand, finish, sku)
     */
    public function index(Request $request)
    {
        $keyword = $request->get('query');

        if (! $keyword) {
            return $this->success(['query' => '', 'products' => [], 'addons' => [], 'variants' => []]);
        }

        $term = '%' . $keyword . '%';

        // 1. Search Products
        $products = Product::with(['brand:id,name'])
            ->where('status', 1)
            ->where('available_on_wholesale', 1)
            ->where(function ($query) use ($term) {
                $query->where('name', 'like', $term)
                    ->orWhere('product_full_name', 'like', $term)
                    ->orWhere('sku', 'like', $term);
            })
            ->select(['id', 'name', 'product_full_name', 'sku', 'images', 'brand_id'])
            ->take(15)
            ->get();

        // 2. Search AddOns
        $addons = AddOn::with(['category:id,name'])
            ->where('stock_status', '!=', 0)
            ->where(function ($q) use ($term) {
                $q->where('title', 'like', $term)
                  ->orWhere('part_number', 'like', $term);
            })
            ->select(['id', 'title', 'part_number', 'image_1', 'addon_category_id'])
            ->take(10)
            ->get();

        // 3. Search Product Variants
        $variants = ProductVariant::with(['product:id,name,brand_id', 'product.brand:id,name'])
            ->whereHas('product', function ($q) {
                $q->where('status', 1)->where('available_on_wholesale', 1);
            })
            ->where('sku', 'like', $term)
            ->select(['id', 'product_id', 'sku', 'image', 'finish'])
            ->take(10)
            ->get();

        // Standardize output for Frontend
        $results = [
            'query' => $keyword,
            'products' => $products->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'brand' => $p->brand->name ?? null,
                'images' => $p->images,
                'sku' => $p->sku,
                'type' => 'product'
            ]),
            'addons' => $addons->map(fn($a) => [
                'id' => $a->id,
                'name' => $a->title,
                'brand' => $a->category->name ?? null,
                'sku' => $a->part_number,
                'images' => $a->image_1,
                'type' => 'addon'
            ]),
            'variants' => $variants->map(fn($v) => [
                'id' => $v->id,
                'name' => ($v->product->name ?? 'Unknown') . ' (' . ($v->finish ?? 'N/A') . ')',
                'brand' => $v->product->brand->name ?? null,
                'sku' => $v->sku,
                'images' => $v->image,
                'type' => 'variant'
            ])
        ];

        return $this->success($results, 'Search results loaded.');
    }

    /**
     * GET /api/countries
     * Return list of countries for address forms
     */
    public function countries(Request $request)
    {
        // Angular expects 'countryName' and 'id'
        $countries = \App\Modules\Customers\Models\Country::active()
            ->orderBy('name')
            ->get()
            ->map(function ($c) {
                return [
                    'id' => $c->id,
                    'countryName' => $c->name,
                    'countryCode' => $c->code
                ];
            });

        return $this->success(['countries' => $countries]);
    }

    /**
     * GET /api/states/us
     * Return list of US States for address forms
     */
    public function usStates(Request $request)
    {
        // Angular expects 'name' and 'id'
        $states = [
            ['id' => 1,  'name' => 'Alabama'], ['id' => 2,  'name' => 'Alaska'], 
            ['id' => 3,  'name' => 'Arizona'], ['id' => 4,  'name' => 'Arkansas'], 
            ['id' => 5,  'name' => 'California'], ['id' => 6,  'name' => 'Colorado'],
            ['id' => 10, 'name' => 'Florida'], ['id' => 11, 'name' => 'Georgia'],
            ['id' => 14, 'name' => 'Illinois'], ['id' => 33, 'name' => 'New York'],
            ['id' => 44, 'name' => 'Texas'], ['id' => 48, 'name' => 'Washington']
        ];

        return $this->success(['states' => $states]);
    }
}
