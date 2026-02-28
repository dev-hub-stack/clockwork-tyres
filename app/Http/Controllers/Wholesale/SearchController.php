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
            ->where('title', 'like', '%' . $keyword . '%')
            ->orWhereHas('brand', fn($q) => $q->where('name', 'like', '%' . $keyword . '%'))
            ->take(10)
            ->get();

        $variants = ProductVariant::with(['product.brand', 'finish'])
            ->where('slug', 'like', '%' . $keyword . '%')
            ->orWhere('sku', 'like', '%' . $keyword . '%')
            ->take(10)
            ->get();

        // Format to a generic search result structure
        $results = [
            'query'    => $keyword,
            'products' => $products->map(fn($p) => [
                'id'       => $p->id,
                'title'    => $p->title,
                'brand'    => $p->brand->name ?? null,
                'slug'     => $p->slug,
            ]),
            'variants' => $variants->map(fn($v) => [
                'id'            => $v->id,
                'product_title' => $v->product->title ?? 'Unknown',
                'brand'         => $v->product->brand->name ?? null,
                'finish'        => $v->finish->name ?? 'Standard',
                'sku'           => $v->sku,
                'slug'          => $v->slug,
            ]),
        ];

        return $this->success($results, 'Search results loaded.');
    }
}
