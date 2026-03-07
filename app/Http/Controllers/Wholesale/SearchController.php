<?php

namespace App\Http\Controllers\Wholesale;

use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductVariant;
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
            return $this->success(['query' => '', 'products' => [], 'variants' => []]);
        }

        // Basic keyword search across Product and ProductVariant titles/SKUs
        $products = Product::with(['brand'])
            ->where('status', 1)
            ->where('available_on_wholesale', true)
            ->where(function ($q) use ($keyword) {
                $q->where('name', 'like', '%' . $keyword . '%')
                  ->orWhereHas('brand', fn($bq) => $bq->where('name', 'like', '%' . $keyword . '%'));
            })
            ->take(10)
            ->get();

        $variants = ProductVariant::with(['product.brand', 'finish'])
            ->whereHas('product', fn($q) => $q->where('status', 1)->where('available_on_wholesale', true))
            ->where('sku', 'like', '%' . $keyword . '%')
            ->take(10)
            ->get();

        // Format to a generic search result structure
        $results = [
            'query'    => $keyword,
            'products' => $products->map(fn($p) => [
                'id'       => $p->id,
                'name'     => $p->name,
                'brand'    => $p->brand->name ?? null,
                'slug'     => $p->name,
            ]),
            'variants' => $variants->map(fn($v) => [
                'id'            => $v->id,
                'product_title' => $v->product->name ?? 'Unknown',
                'brand'         => $v->product->brand->name ?? null,
                'finish'        => $v->finish->finish ?? 'Standard',
                'sku'           => $v->sku,
                'slug'          => $v->sku,
            ]),
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
