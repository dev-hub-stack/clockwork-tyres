<?php

namespace App\Http\Controllers\Wholesale;

use App\Modules\Products\Models\Brand;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductVariant;
use App\Modules\Wholesale\Helpers\WholesaleProductTransformer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
        return \Cache::remember('wholesale_brands_nav_v2', 600, function () {
            return Brand::query()
                ->select('brands.id', 'brands.name', 'brands.slug', 'brands.logo', 'brands.description')
                ->join('products', 'products.brand_id', '=', 'brands.id')
                ->join('product_variants', 'product_variants.product_id', '=', 'products.id')
                ->where('brands.status', 1)
                ->where('products.status', 1)
                ->where('products.available_on_wholesale', true)
                ->distinct()
                ->orderBy('brands.sort_order')
                ->orderBy('brands.name')
                ->get();
        });
    }

    /**
     * GET /api/all-brands
     * Full brand listing page with product counts.
     * Angular calls: getAllBrands(params)
     */
    public function all(Request $request)
    {
        $search = $request->search;
        $cacheKey = "wholesale_all_brands_v2_" . ($search ?? 'none');

        $data = \Cache::remember($cacheKey, 600, function () use ($search) {
            $query = Brand::query()
                ->select('brands.*')
                ->join('products', 'products.brand_id', '=', 'brands.id')
                ->join('product_variants', 'product_variants.product_id', '=', 'products.id')
                ->where('brands.status', 1)
                ->where('products.status', 1)
                ->where('products.available_on_wholesale', true)
                ->distinct()
                ->orderBy('brands.sort_order')
                ->orderBy('brands.name');

            if ($search) {
                $query->where('brands.name', 'like', '%' . $search . '%');
            }

            $brands = $query->withCount(['products' => fn($q) => $q
                ->where('status', 1)
                ->where('available_on_wholesale', true)
                ->whereHas('variants')
            ])->get();

            $formatted = $brands->map(fn(Brand $b) => [
                'id'            => $b->id,
                'name'          => $b->name,
                'slug'          => $b->slug,
                'image'         => $b->logo,
                'description'   => $b->description,
                'product_count' => $b->products_count,
            ])->values()->all();

            return [
                'brands' => [
                    'data'          => $formatted,
                    'next_page_url' => null,
                ],
            ];
        });

        return $this->success($data);
    }

    /**
     * GET /api/all-brand-products/{brand}
     * All product variants for a specific brand, paginated with filters.
     * Angular calls: getAllBrandProducts(params, brand)
     */
    public function brandProducts(Request $request, string $brandSlug)
    {
        $brand = $this->resolveWholesaleBrandOrFail($brandSlug);

        $products = Product::with('finish')
            ->where('brand_id', $brand->id)
            ->where('status', 1)
            ->where('available_on_wholesale', true)
            ->whereHas('variants')
            ->orderBy('name')
            ->orderBy('finish_id')
            ->get(['id', 'name', 'images', 'finish_id']);

        $formatted = $products
            ->map(fn(Product $p) => [
                'id'     => $p->id,
                'name'   => $p->name,
                'finish' => $p->finish?->finish,
                'slug'   => $this->brandProductSlug($p),
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

        $brand = $this->resolveWholesaleBrandOrFail($brandSlug);

        $product = Product::with('finish')
            ->where('brand_id', $brand->id)
            ->where('status', 1)
            ->where('available_on_wholesale', true)
            ->whereHas('variants')
            ->get()
            ->first(fn(Product $candidate) => $this->brandProductSlug($candidate) === urldecode($productSlug));

        if (! $product) {
            return $this->error('Product not found.', null, 404);
        }

        $variants = ProductVariant::with([
            'product.brand',
            'product.model',
        ])
        ->whereHas('product', function ($q) use ($brand, $product) {
            $q->where('brand_id', $brand->id)
              ->where('status', 1)
              ->where('available_on_wholesale', true)
              ->where('id', $product->id);
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

    private function resolveWholesaleBrandOrFail(string $brandSlug): Brand
    {
        return Brand::query()
            ->where('status', 1)
            ->where(function ($query) use ($brandSlug) {
                $query->where('slug', $brandSlug)
                    ->orWhere('name', urldecode($brandSlug));
            })
            ->whereHas('products', function ($query) {
                $query->where('status', 1)
                    ->where('available_on_wholesale', true)
                    ->whereHas('variants');
            })
            ->firstOrFail();
    }

    private function brandProductSlug(Product $product): string
    {
        $parts = [$product->name];

        if ($product->relationLoaded('finish') && $product->finish?->finish) {
            $parts[] = $product->finish->finish;
        }

        $parts[] = (string) $product->id;

        return Str::slug(implode(' ', array_filter($parts)));
    }
}
