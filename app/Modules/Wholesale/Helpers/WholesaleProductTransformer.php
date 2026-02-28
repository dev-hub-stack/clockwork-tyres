<?php

namespace App\Modules\Wholesale\Helpers;

use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Services\DealerPricingService;
use App\Modules\Inventory\Models\ProductInventory;
use App\Modules\Products\Models\ProductVariant;
use App\Modules\Products\Models\AddOn;

/**
 * WholesaleProductTransformer
 *
 * Single source of truth for shaping product/variant data for the
 * wholesale Angular frontend. Guarantees a consistent JSON shape
 * across ProductController, ProductVariantController, BrandController,
 * and AddOnController — matching Angular's Wheels & ProductDetailResponse interfaces.
 *
 * Dealer-specific pricing is applied here via DealerPricingService.
 * Per-warehouse stock data is fetched and structured here.
 */
class WholesaleProductTransformer
{
    public function __construct(
        protected DealerPricingService $pricingService
    ) {}

    /**
     * Format a single ProductVariant for API response.
     * Includes dealer price, per-warehouse stock, and ETA.
     */
    public function formatVariant(ProductVariant $variant, Customer $dealer): array
    {
        $product    = $variant->product;
        $brand      = $product?->brand;
        $model      = $product?->model;
        $finishRel  = $variant->finishRelation ?? $variant->finish();

        // Calculate dealer price using DealerPricingService (3-tier hierarchy)
        $priceResult = $this->dealerPrice($variant, $dealer);
        $stockData   = $this->stockData($variant);
        $totalStock  = collect($stockData)->sum('qty');

        return [
            // Identity
            'id'               => $variant->id,
            'product_id'       => $variant->product_id,
            'sku'              => $variant->sku,
            'name'             => $product?->name ?? '',
            'slug'             => $product?->slug ?? $product?->name ?? '',

            // Brand & Model
            'brand'            => $brand?->name ?? '',
            'brand_id'         => $brand?->id,
            'brand_slug'       => $brand?->slug ?? '',
            'brand_logo'       => $brand?->logo ?? '',
            'model'            => $model?->name ?? '',
            'model_id'         => $model?->id,

            // Finish
            'finish'           => $finishRel?->finish ?? $variant->finish ?? '',
            'finish_id'        => $variant->finish_id,
            'hex_color'        => $finishRel?->hex_color ?? null,

            // Media
            'image'            => $variant->image,
            'images'           => $product?->images ?? [],

            // Wheel specs
            'rim_diameter'     => $variant->rim_diameter,
            'rim_width'        => $variant->rim_width,
            'bolt_pattern'     => $variant->bolt_pattern,
            'hub_bore'         => $variant->hub_bore,
            'offset'           => $variant->offset,
            'weight'           => $variant->weight,
            'backspacing'      => $variant->backspacing,
            'lipsize'          => $variant->lipsize,
            'max_wheel_load'   => $variant->max_wheel_load,
            'construction'     => $product?->construction ?? '',
            'size'             => $variant->size ?? ($variant->rim_diameter . 'x' . $variant->rim_width),

            // Pricing
            'price'            => $priceResult['final_price'],
            'retail_price'     => (float) ($variant->uae_retail_price ?? $variant->price ?? 0),
            'us_retail_price'  => (float) ($variant->us_retail_price ?? 0),
            'sale_price'       => $variant->sale_price ? (float) $variant->sale_price : null,
            'discount_amount'  => $priceResult['discount_amount'],
            'discount_pct'     => $priceResult['discount_percentage'],
            'discount_type'    => $priceResult['discount_type'],
            'clearance'        => (bool) $variant->clearance_corner,

            // Stock
            'stock'            => $stockData,   // per-warehouse breakdown
            'in_stock'         => $totalStock > 0,
            'total_stock'      => $totalStock,
            'supplier_stock'   => (int) ($variant->supplier_stock ?? 0),
            'eta'              => $this->etaData($variant),
        ];
    }

    /**
     * Format a list of variants (for paginated lists).
     */
    public function formatVariants(iterable $variants, Customer $dealer): array
    {
        $result = [];
        foreach ($variants as $variant) {
            $result[] = $this->formatVariant($variant, $dealer);
        }
        return $result;
    }

    /**
     * Format a single AddOn for API response with dealer pricing applied.
     */
    public function formatAddon(AddOn $addon, Customer $dealer): array
    {
        // Apply dealer addon pricing via DealerPricingService
        $priceResult = $this->pricingService->calculateAddonPrice(
            $dealer,
            (float) $addon->price,
            $addon->addon_category_id
        );

        return [
            'id'              => $addon->id,
            'title'           => $addon->title,
            'part_number'     => $addon->part_number,
            'description'     => $addon->description,
            'category'        => $addon->category?->name ?? '',
            'category_id'     => $addon->addon_category_id,

            // Pricing
            'price'           => $priceResult['final_price'],
            'retail_price'    => (float) $addon->price,
            'wholesale_price' => (float) ($addon->wholesale_price ?? $addon->price),
            'discount_amount' => $priceResult['discount_amount'],
            'discount_pct'    => $priceResult['discount_percentage'],

            // Media & specs
            'image'           => $addon->image,
            'stock_status'    => $addon->stock_status_text,
            'bolt_pattern'    => $addon->bolt_pattern,
            'thread_size'     => $addon->thread_size,
            'thread_length'   => $addon->thread_length,
            'center_bore'     => $addon->center_bore,
            'color'           => $addon->color,
            'lug_nut_length'  => $addon->lug_nut_length,
            'lug_bolt_diameter' => $addon->lug_bolt_diameter,
            'size'            => $addon->size,
        ];
    }

    // ─── Private helpers ────────────────────────────────────────────────────

    /**
     * Calculate dealer price using DealerPricingService (3-tier hierarchy).
     * Model-specific > Brand-specific > base price.
     */
    private function dealerPrice(ProductVariant $variant, Customer $dealer): array
    {
        $basePrice = (float) ($variant->uae_retail_price ?? $variant->price ?? 0);

        return $this->pricingService->calculateProductPrice(
            $dealer,
            $basePrice,
            $variant->product?->model_id,
            $variant->product?->brand_id
        );
    }

    /**
     * Get per-warehouse stock breakdown for a variant.
     * Returns array of warehouses with qty, eta_qty, and ETA date.
     */
    private function stockData(ProductVariant $variant): array
    {
        $inventories = ProductInventory::where('product_variant_id', $variant->id)
            ->with('warehouse')
            ->get();

        return $inventories->map(fn($inv) => [
            'warehouse_id'   => $inv->warehouse_id,
            'warehouse'      => $inv->warehouse?->name ?? 'Unknown',
            'qty'            => (int) $inv->quantity,
            'eta_qty'        => (int) $inv->eta_qty,
            'eta'            => $inv->eta,
            'in_stock'       => $inv->quantity > 0,
            'stock_color'    => $inv->stock_status_color,
        ])->toArray();
    }

    /**
     * Get the earliest ETA record with inbound stock for a variant.
     */
    private function etaData(ProductVariant $variant): ?array
    {
        $eta = ProductInventory::where('product_variant_id', $variant->id)
            ->where('eta_qty', '>', 0)
            ->whereNotNull('eta')
            ->orderBy('eta')
            ->first();

        if (! $eta) {
            return null;
        }

        return [
            'date'      => $eta->eta,
            'qty'       => (int) $eta->eta_qty,
            'warehouse' => $eta->warehouse?->name,
        ];
    }
}
