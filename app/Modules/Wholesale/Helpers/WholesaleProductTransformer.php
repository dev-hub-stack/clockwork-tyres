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
    public function formatVariant(ProductVariant $variant, ?Customer $dealer): array
    {
        $product    = $variant->product;
        // Optimization: Use relationLoaded or null-coalescing to avoid extra queries
        $brand      = $product?->brand;
        $model      = $product?->model;
        $finishRel  = $variant->finishRelation;

        // Calculate dealer price using DealerPricingService (3-tier hierarchy)
        $priceResult = $this->dealerPrice($variant, $dealer);
        
        // Optimize: map stock data once
        $stockData = [];
        $totalStock = 0;
        
        foreach ($variant->inventories as $inv) {
            $qty = (int) $inv->quantity;
            $etaQty = (int) $inv->eta_qty;
            $warehouseName = $inv->warehouse?->warehouse_name ?? $inv->warehouse?->name ?? 'Unknown';
            $isPrimary = (bool) ($inv->warehouse?->is_primary ?? false);

            $totalStock += $qty;
            $stockData[] = [
                'warehouse_id'   => $inv->warehouse_id,
                'warehouse'      => $warehouseName,
                'warehouse_name' => $warehouseName,
                'qty'            => $qty,
                'eta_qty'        => $etaQty,
                'eta'            => $inv->eta,
                'in_stock'       => $qty > 0,
                'is_primary'     => $isPrimary,
                'stock_color'    => $inv->stock_status_color,
            ];
        }

        usort($stockData, fn(array $left, array $right) => $this->compareStockRows($left, $right));

        return [
            // Identity
            'id'               => $variant->id,
            'variant_id'       => $variant->id,
            'product_variant_id' => $variant->id,
            'product_id'       => $variant->product_id,
            'sku'              => $variant->sku,
            'name'             => $product?->name ?? '',
            'slug'             => $product?->name ?? '',

            // Brand & Model
            'brand'            => [
                'id'          => $brand?->id,
                'name'        => $brand?->name ?? '',
                'slug'        => $brand?->slug ?? '',
                'logo'        => $brand?->logo ?? $brand?->image ?? '',
                'description'   => $brand?->description ?? '',
            ],
            'brand_id'         => $brand?->id,
            'brand_slug'       => $brand?->slug ?? '',
            'brand_logo'       => $brand?->logo ?? '',
            'model'            => [
                'id'    => $model?->id,
                'name'  => $model?->name ?? '',
                'image' => $model?->image ?? '',
            ],
            'model_id'         => $model?->id,

            // Finish
            'finish'           => [
                'id'       => $finishRel?->id,
                'finish'   => $finishRel?->finish ?? $variant->finish ?? '',
                'hex_color' => $finishRel?->hex_color ?? null,
            ],
            'finish_id'        => $variant->finish_id,
            'hex_color'        => $finishRel?->hex_color ?? null,
            'finish_name'      => $finishRel?->finish ?? $variant->finish ?? '',

            // Media
            'image'            => $variant->image,
            'images'           => $product?->images ? $product->images : [],

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

            // Nested Variant for Detail Page Compatibility
            'variant'          => [
                'id'               => $variant->id,
                'sku'              => $variant->sku,
                'rim_diameter'     => $variant->rim_diameter,
                'rim_width'        => $variant->rim_width,
                'bolt_pattern'     => $variant->bolt_pattern,
                'hub_bore'         => $variant->hub_bore,
                'offset'           => $variant->offset,
                'weight'           => $variant->weight,
                'backspacing'      => $variant->backspacing,
                'warranty'         => $variant->backspacing, 
                'lipsize'          => $variant->lipsize,
                'max_wheel_load'   => $variant->max_wheel_load,
                'construction'     => $product?->construction ?? '',
                'size'             => $variant->size ?? ($variant->rim_diameter . 'x' . $variant->rim_width),
                'uae_retail_price' => (float) ($variant->uae_retail_price ?? $variant->price ?? 0),
            ],

            // Pricing
            'price'            => $priceResult['final_price'],
            'retail_price'     => (float) ($variant->uae_retail_price ?? $variant->price ?? 0),
            'uae_retail_price' => (float) ($variant->uae_retail_price ?? $variant->price ?? 0),
            'us_retail_price'  => (float) ($variant->us_retail_price ?? 0),
            'sale_price'       => $variant->sale_price ? (float) $variant->sale_price : null,
            'discounted_price' => $priceResult['final_price'], 
            'discount_amount'  => $priceResult['discount_amount'],
            'discount_pct'     => $priceResult['discount_percentage'],
            'discount_type'    => $priceResult['discount_type'],
            'clearance'        => (bool) $variant->clearance_corner,
            'clearance_corner' => (bool) $variant->clearance_corner,

            // Stock
            'stock'            => $stockData,
            'in_stock'         => $totalStock > 0,
            'total_stock'      => $totalStock,
            'supplier_stock'   => (int) ($variant->supplier_stock ?? 0),
            'eta'              => $this->etaData($variant),

            // Frontend compatibility aliases
            'total_quantity'    => $totalStock,
            'product_inventory' => array_map(fn($s) => [
                'warehouse_id' => $s['warehouse_id'],
                'warehouse_name' => $s['warehouse_name'],
                'is_primary' => $s['is_primary'],
                'quantity'  => $s['qty'],
                'eta'       => $s['eta'],
                'eta_qty'   => $s['eta_qty'],
                'warehouse' => $s['warehouse'],
            ], $stockData),
            'track_inventory'   => (bool) ($product?->track_inventory ?? true),
        ];
    }

    /**
     * Format a list of variants (for paginated lists).
     */
    public function formatVariants(iterable $variants, ?Customer $dealer): array
    {
        return collect($variants)->map(fn($v) => $this->formatVariant($v, $dealer))->toArray();
    }

    /**
     * Format a single AddOn for API response with dealer pricing applied.
     */
    public function formatAddon(AddOn $addon, ?Customer $dealer): array
    {
        $priceResult = $this->addonPrice($addon, $dealer);
        
        // Optimize: Calculate total quantity efficiently
        $totalQty = $addon->relationLoaded('inventories')
                    ? $addon->inventories->sum('quantity')
                    : ProductInventory::where('add_on_id', $addon->id)->sum('quantity');

        $imgUrl = $addon->image
            ? (str_starts_with($addon->image, 'http') ? $addon->image : config('wholesale.image_base_url', 'http://d2iosncs8hpu1u.cloudfront.net') . '/' . ltrim($addon->image, '/'))
            : null;

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
            'image'           => $imgUrl,
            'stock_status'    => (bool) $addon->stock_status,
            'total_quantity'  => (int) $totalQty,
            'bolt_pattern'    => $addon->bolt_pattern,
            'thread_size'     => $addon->thread_size,
            'thread_length'   => $addon->thread_length,
            'center_bore'     => $addon->center_bore,
            'color'           => $addon->color,
            'lug_nut_length'  => $addon->lug_nut_length,
            'lug_bolt_diameter' => $addon->lug_bolt_diameter,
            'size'            => $addon->size,

            // Frontend compatibility aliases
            'discounted_price' => $priceResult['final_price'],
            'image_1'          => $imgUrl,
            'vendor'           => [
                'business_name' => $addon->category?->name ?? 'TunerStop',
                'currency'      => 'USD',
            ],
        ];
    }

    // ─── Private helpers ────────────────────────────────────────────────────

    /**
     * Public wrapper for dealer price calculation — used by controllers that
     * need pricing for rear/secondary variants without a full formatVariant call.
     */
    public function publicDealerPrice(ProductVariant $variant, ?Customer $dealer): array
    {
        return $this->dealerPrice($variant, $dealer);
    }

    /**
     * Calculate dealer price using DealerPricingService (3-tier hierarchy).
     */
    private function dealerPrice(ProductVariant $variant, ?Customer $dealer): array
    {
        $basePrice = (float) ($variant->uae_retail_price ?? $variant->price ?? 0);

        if (!$dealer) {
            return [
                'final_price'         => $basePrice,
                'discount_amount'     => 0,
                'discount_percentage' => 0,
                'discount_type'       => 'none',
                'tier'                => 'Retail',
            ];
        }

        return $this->pricingService->calculateProductPrice(
            $dealer,
            $basePrice,
            $variant->product?->model_id,
            $variant->product?->brand_id
        );
    }

    private function compareStockRows(array $left, array $right): int
    {
        $priorityCompare = $this->stockPriority($left) <=> $this->stockPriority($right);

        if ($priorityCompare !== 0) {
            return $priorityCompare;
        }

        return strcasecmp($left['warehouse_name'] ?? '', $right['warehouse_name'] ?? '');
    }

    private function stockPriority(array $stockRow): int
    {
        $hasStock = (int) ($stockRow['qty'] ?? 0) > 0;
        $hasEta = (int) ($stockRow['eta_qty'] ?? 0) > 0 || !empty($stockRow['eta']);
        $isPrimary = (bool) ($stockRow['is_primary'] ?? false);

        if ($hasStock && $isPrimary) {
            return 0;
        }

        if ($hasStock) {
            return 1;
        }

        if ($hasEta && $isPrimary) {
            return 2;
        }

        if ($hasEta) {
            return 3;
        }

        return 4;
    }

    /**
     * Calculate dealer price for an addon.
     */
    private function addonPrice(AddOn $addon, ?Customer $dealer): array
    {
        $basePrice = (float) $addon->price;

        if (!$dealer) {
            return [
                'final_price'         => $basePrice,
                'discount_amount'     => 0,
                'discount_percentage' => 0,
                'tier'                => 'Retail',
            ];
        }

        return $this->pricingService->calculateAddonPrice(
            $dealer,
            $basePrice,
            $addon->addon_category_id
        );
    }

    /**
     * Get per-warehouse stock breakdown for a variant.
     * @deprecated Use logic inside formatVariant for performance
     */
    private function stockData(ProductVariant $variant): array
    {
        return $variant->inventories->map(fn($inv) => [
            'warehouse_id'   => $inv->warehouse_id,
            'warehouse'      => $inv->warehouse?->warehouse_name ?? 'Unknown',
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
        $eta = $variant->inventories->where('eta_qty', '>', 0)
            ->whereNotNull('eta')
            ->sortBy('eta')
            ->first();

        if (! $eta) {
            return null;
        }

        return [
            'date'      => $eta->eta,
            'qty'       => (int) $eta->eta_qty,
            'warehouse' => $eta->warehouse?->warehouse_name ?? $eta->warehouse?->name,
        ];
    }
}
