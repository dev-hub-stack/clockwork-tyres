<?php

namespace App\Modules\Wholesale\Cart\Services;

use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Services\DealerPricingService;
use App\Modules\Products\Models\ProductVariant;
use App\Modules\Products\Models\AddOn;
use App\Modules\Wholesale\Cart\Models\Cart;
use App\Modules\Wholesale\Cart\Models\CartItem;
use App\Modules\Wholesale\Cart\Models\CartAddon;
use App\Modules\Wholesale\Cart\Models\Coupon;
use App\Modules\Settings\Models\TaxSetting;
use App\Modules\Wholesale\Helpers\WholesaleProductTransformer;
use Illuminate\Support\Facades\DB;

/**
 * CartService — Central business logic for the wholesale shopping cart.
 *
 * Responsibilities:
 *  - Resolve/create carts by session_id + dealer
 *  - Add/remove/update wheel items and addon items
 *  - Apply coupons and validate eligibility
 *  - Calculate shipping and VAT (from config/wholesale.php)
 *  - Recalculate totals after every mutation
 *  - Format cart into the exact shape Angular Cart interface expects
 */
class CartService
{
    public function __construct(
        protected DealerPricingService $pricingService,
        protected WholesaleProductTransformer $transformer
    ) {}

    /**
     * Find an existing cart by session_id or dealer_id, or create a new one.
     * Ensures a dealer never has two active carts simultaneously.
     */
    public function getOrCreateCart(?Customer $dealer, string $sessionId): Cart
    {
        $query = Cart::withTrashed(false)->where('session_id', $sessionId);

        if ($dealer) {
            $query->orWhere('dealer_id', $dealer->id);
        }

        $cart = $query->with([
            'items.variant.product.brand', 
            'items.variant.product.model', 
            'items.variant.finishRelation', 
            'items.variant.inventories.warehouse', 
            'addons.addon.category', 
            'coupon'
        ])
            ->first();

        if (!$cart) {
            $cart = Cart::create([
                'session_id' => $sessionId,
                'dealer_id'  => $dealer?->id
            ]);
        }

        // If guest cart found, but dealer now logged in, associate it
        if ($dealer && !$cart->dealer_id) {
            $cart->update(['dealer_id' => $dealer->id]);
        }

        return $cart;
    }

    /**
     * Add a wheel variant to the cart. Increments quantity if same variant+warehouse exists.
     */
    public function addItem(Cart $cart, array $data): Cart
    {
        $variantId   = $data['product_variant_id'];
        $warehouseId = $data['warehouse_id'] ?? null;
        $quantity    = (int) ($data['quantity'] ?? 1);

        $variant = ProductVariant::with('product.brand', 'product.model')->findOrFail($variantId);

        $dealer     = $cart->dealer;
        $priceInfo  = $this->pricingService->calculateProductPrice(
            $dealer,
            (float) ($variant->uae_retail_price ?? $variant->price ?? 0),
            $variant->product?->model_id,
            $variant->product?->brand_id
        );
        $unitPrice = $priceInfo['final_price'];

        // Check if same variant + warehouse already in cart — merge if so
        $existing = CartItem::where('cart_id', $cart->id)
            ->where('product_variant_id', $variantId)
            ->when($warehouseId, fn($q) => $q->where('warehouse_id', $warehouseId))
            ->first();

        if ($existing) {
            $existing->quantity   += $quantity;
            $existing->total_price = round($existing->quantity * $unitPrice, 2);
            $existing->save();
        } else {
            CartItem::create([
                'cart_id'            => $cart->id,
                'product_variant_id' => $variantId,
                'warehouse_id'       => $warehouseId,
                'quantity'           => $quantity,
                'unit_price'         => $unitPrice,
                'total_price'        => round($quantity * $unitPrice, 2),
                'type'               => $data['type'] ?? 'wheel',
                'eta'                => $data['eta'] ?? false,
            ]);
        }

        return $this->recalculateAndReturn($cart);
    }

    /**
     * Change the quantity of a wheel item. Quantity 0 removes item.
     */
    public function changeQuantity(Cart $cart, int $itemId, int $quantity): Cart
    {
        $item = CartItem::where('cart_id', $cart->id)->findOrFail($itemId);

        if ($quantity <= 0) {
            $item->delete();
        } else {
            $item->quantity    = $quantity;
            $item->total_price = round($quantity * $item->unit_price, 2);
            $item->save();
        }

        return $this->recalculateAndReturn($cart);
    }

    /**
     * Remove a wheel item from the cart.
     */
    public function removeItem(Cart $cart, int $itemId): Cart
    {
        CartItem::where('cart_id', $cart->id)->findOrFail($itemId)->delete();
        return $this->recalculateAndReturn($cart);
    }

    /**
     * Add an addon to the cart. Increments quantity if same addon already exists.
     */
    public function addAddon(Cart $cart, array $data): Cart
    {
        $addonId  = $data['addon_id'];
        $quantity = (int) ($data['quantity'] ?? 1);

        $addon    = AddOn::findOrFail($addonId);
        $dealer   = $cart->dealer;

        $priceInfo = $this->pricingService->calculateAddonPrice(
            $dealer,
            (float) $addon->price,
            $addon->addon_category_id
        );
        $unitPrice = $priceInfo['final_price'];

        $existing = CartAddon::where('cart_id', $cart->id)->where('addon_id', $addonId)->first();

        if ($existing) {
            $existing->quantity   += $quantity;
            $existing->total_price = round($existing->quantity * $unitPrice, 2);
            $existing->save();
        } else {
            CartAddon::create([
                'cart_id'    => $cart->id,
                'addon_id'   => $addonId,
                'quantity'   => $quantity,
                'unit_price' => $unitPrice,
                'total_price'=> round($quantity * $unitPrice, 2),
            ]);
        }

        return $this->recalculateAndReturn($cart);
    }

    /**
     * Remove an addon from the cart.
     */
    public function removeAddon(Cart $cart, int $addonId): Cart
    {
        CartAddon::where('cart_id', $cart->id)->where('addon_id', $addonId)->delete();
        return $this->recalculateAndReturn($cart);
    }

    /**
     * Change the quantity of an addon item. Quantity 0 removes it.
     */
    public function changeAddonQuantity(Cart $cart, int $addonId, int $quantity): Cart
    {
        $addonItem = CartAddon::where('cart_id', $cart->id)->where('addon_id', $addonId)->firstOrFail();

        if ($quantity <= 0) {
            $addonItem->delete();
        } else {
            $addonItem->quantity    = $quantity;
            $addonItem->total_price = round($quantity * $addonItem->unit_price, 2);
            $addonItem->save();
        }

        return $this->recalculateAndReturn($cart);
    }

    /**
     * Apply a coupon code to the cart.
     * Validates eligibility and applies discount + free_shipping if applicable.
     */
    public function applyCoupon(Cart $cart, string $code): array
    {
        $coupon = Coupon::where('code', strtoupper(trim($code)))->first();

        if (! $coupon) {
            return ['applied' => false, 'message' => 'Coupon code not found.'];
        }

        // Refresh sub_total before validation
        $this->recalculateTotals($cart);
        $cart->refresh();

        if (! $coupon->isValid((float) $cart->sub_total)) {
            return ['applied' => false, 'message' => 'Coupon is expired, invalid, or minimum spend not met.'];
        }

        // Calculate discount — optionally scoped to specific brands/models
        $applicableSubtotal = $this->getApplicableSubtotal($cart, $coupon);
        $discountAmount     = $coupon->calculateDiscount($applicableSubtotal);

        // Apply free shipping if coupon includes it
        if ($coupon->free_shipping) {
            $cart->shipping = 0;
        }

        $cart->coupon_id = $coupon->id;
        $cart->discount  = $discountAmount;
        $this->recalculateTotals($cart);

        return [
            'applied'  => true,
            'message'  => 'Coupon applied successfully.',
            'discount' => $discountAmount,
            'coupon'   => $coupon,
        ];
    }

    /**
     * Set shipping cost based on selected option.
     *
     * - Pickup → always AED 0
     * - Delivery → tiered rate from CRM SystemSetting:
     *     admin.shipping_rate_upto_four  = base rate for up to 4 items  (default 200)
     *     admin.shipping_rate_per_item   = rate per item beyond 4        (default 50)
     */
    public function calculateShipping(Cart $cart, string $option): Cart
    {
        if (strtolower($option) === 'pickup') {
            $cart->shipping        = 0;
            $cart->shipping_option = 'Pickup';
            return $this->recalculateAndReturn($cart);
        }

        // Delivery: tiered rate from CRM admin settings
        $baseRate  = (float) \App\Modules\Settings\Models\SystemSetting::get('admin.shipping_rate_upto_four', 200);
        $extraRate = (float) \App\Modules\Settings\Models\SystemSetting::get('admin.shipping_rate_per_item', 50);

        $totalQty = (int) $cart->items->sum('quantity');
        $extraQty = max(0, $totalQty - 4);
        $shipping = $baseRate + ($extraQty * $extraRate);

        $cart->shipping        = round($shipping, 2);
        $cart->shipping_option = $option;

        return $this->recalculateAndReturn($cart);
    }

    /**
     * Apply VAT to the cart based on the admin global VAT setting.
     */
    public function calculateVat(Cart $cart): Cart
    {
        $vatRate  = $this->getVatRate();
        $taxable  = (float) $cart->sub_total - (float) $cart->discount + (float) $cart->shipping;
        $cart->vat = round($taxable * $vatRate, 2);
        $cart->total = round($taxable + $cart->vat, 2);
        $cart->save();

        return $cart->refresh();
    }

    /**
     * Recalculate all cart financial totals from line items.
     * Called automatically after every cart mutation.
     */
    public function recalculateTotals(Cart $cart): void
    {
        $cart->load('items', 'addons.addon');

        // ── Tax settings ──────────────────────────────────────────────────────
        $tax              = TaxSetting::getDefault();
        $vatRate          = $tax ? (float) $tax->rate / 100 : 0.0;
        $globalInclusive  = $tax ? (bool) $tax->tax_inclusive_default : false;

        // ── Item totals ───────────────────────────────────────────────────────
        $itemsTotal = (float) $cart->items->sum('total_price');

        // An item is tax-inclusive if the global default is inclusive (all prices include VAT),
        // OR if the addon itself has been explicitly marked as tax-inclusive.
        // The global setting cannot be overridden to exclusive per-addon — only to inclusive.
        $isAddonInclusive = fn($a) => $globalInclusive || ($a->addon && $a->addon->tax_inclusive);

        // Split addons by effective tax-inclusive status
        $inclusiveAddonsTotal  = (float) $cart->addons
            ->filter($isAddonInclusive)
            ->sum('total_price');
        $exclusiveAddonsTotal  = (float) $cart->addons
            ->reject($isAddonInclusive)
            ->sum('total_price');

        $subTotal = round($itemsTotal + $inclusiveAddonsTotal + $exclusiveAddonsTotal, 2);
        $discount = (float) $cart->discount;
        $shipping = (float) $cart->shipping;

        // ── VAT calculation ───────────────────────────────────────────────────
        // Tax-inclusive addons: VAT is embedded → extract it for display
        $vatFromInclusiveAddons = $vatRate > 0
            ? round($inclusiveAddonsTotal * $vatRate / (1 + $vatRate), 2)
            : 0.0;

        if ($globalInclusive) {
            // Wheel item prices already contain VAT → extract
            $vatFromItems = $vatRate > 0
                ? round($itemsTotal * $vatRate / (1 + $vatRate), 2)
                : 0.0;
            // Tax-exclusive addons override global → add VAT on top
            $vatFromExclusiveAddons = round($exclusiveAddonsTotal * $vatRate, 2);

            // Shipping is never subject to VAT
            $vat   = round($vatFromItems + $vatFromInclusiveAddons + $vatFromExclusiveAddons, 2);
            $total = round($subTotal - $discount + $shipping + $vatFromExclusiveAddons, 2);
        } else {
            // Wheel item prices are ex-VAT → add VAT on top
            $vatFromItems = round($itemsTotal * $vatRate, 2);
            // Tax-exclusive addons: same, add on top
            $vatFromExclusiveAddons = round($exclusiveAddonsTotal * $vatRate, 2);

            // Shipping is never subject to VAT
            $vat   = round($vatFromItems + $vatFromInclusiveAddons + $vatFromExclusiveAddons, 2);
            $total = round($subTotal - $discount + $shipping + $vatFromItems + $vatFromExclusiveAddons, 2);
        }

        $cart->sub_total = $subTotal;
        $cart->vat       = $vat;
        $cart->total     = $total;
        $cart->save();
    }

    /**
     * Clear all items and addons from the cart and reset totals to zero.
     */
    public function clearCart(Cart $cart): void
    {
        CartItem::where('cart_id', $cart->id)->delete();
        CartAddon::where('cart_id', $cart->id)->delete();

        $cart->sub_total  = 0;
        $cart->discount   = 0;
        $cart->shipping   = 0;
        $cart->vat        = 0;
        $cart->total      = 0;
        $cart->coupon_id  = null;
        $cart->save();
    }

    /**
     * Format a Cart into the exact JSON shape the Angular Cart interface expects.
     */
    public function formatCartResponse(Cart $cart): array
    {
        $cart->load([
            'items.variant.product.brand', 
            'items.variant.product.model',
            'items.variant.finishRelation', 
            'items.variant.inventories.warehouse',
            'items.warehouse', 
            'addons.addon.category', 
            'coupon', 
            'shippingAddress'
        ]);
        $dealer = $cart->dealer ?? ($cart->dealer_id ? Customer::find($cart->dealer_id) : null);

        $cartItems = $cart->items->map(function (CartItem $item) use ($dealer) {
            $variantData = $item->variant
                ? $this->transformer->formatVariant($item->variant, $dealer)
                : [];

            return [
                'id'                 => $item->id,
                'cart_id'            => $item->cart_id,
                'product_id'         => $item->variant?->product_id,
                'product_variant_id' => $item->product_variant_id,
                'product_variant'    => $variantData,
                'warehouse'          => $item->warehouse ? ['id' => $item->warehouse->id, 'name' => $item->warehouse->warehouse_name] : null,
                'type'               => $item->type,
                'quantity'           => $item->quantity,
                'warehouse_quantity' => [
                    [
                        'quantity'   => $item->quantity,
                        'ware_house' => [
                            'warehouse_name' => $item->warehouse?->warehouse_name ?? 'Unknown Warehouse'
                        ]
                    ]
                ],
                'price'              => (float) $item->unit_price,
                'sale_price'         => (float) $item->unit_price, // Added for frontend compatibility
                'total'              => (float) $item->total_price,
                'eta'                => $item->eta,
                'discount'           => 0,
            ];
        });

        $addons = $cart->addons->map(function (CartAddon $a) use ($dealer) {
            $addonData = $a->addon ? $this->transformer->formatAddon($a->addon, $dealer) : [];
            return [
                'id'          => $a->id,
                'cart_id'     => $a->cart_id,
                'addon_id'    => $a->addon_id,
                'image'       => $addonData['image'] ?? null,
                'title'       => $addonData['title'] ?? '',
                'quantity'    => $a->quantity,
                'unit_price'  => (float) $a->unit_price,
                'total_price' => (float) $a->total_price,
                'stock_status' => $addonData['stock_status'] ?? false,
                'addon'       => $addonData,
            ];
        });

        return [
            'id'           => $cart->id,
            'session_id'   => $cart->session_id,
            'order_number' => $cart->order_number,
            'user_id'      => $cart->dealer_id,
            'sub_total'    => (float) $cart->sub_total,
            'shipping'     => (float) $cart->shipping,
            'vat'          => (float) $cart->vat,
            'discount'     => (float) $cart->discount,
            'total'        => (float) $cart->total,
            'count'        => $cart->count,
            'eta'          => $cart->eta,
            'cart_items'   => $cartItems->values(),
            'addons'       => $addons->values(),
            'coupon'       => $cart->coupon,
            'shipping_address' => $cart->shippingAddress,
        ];
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    private function recalculateAndReturn(Cart $cart): Cart
    {
        $this->recalculateTotals($cart);
        return $cart->refresh();
    }

    /**
     * Resolve VAT rate from the admin's default TaxSetting (e.g. 5 → 0.05).
     * Falls back to 0 if no active default is configured.
     */
    private function getVatRate(): float
    {
        $tax = TaxSetting::getDefault();
        return $tax ? (float) $tax->rate / 100 : 0.0;
    }

    /**
     * If coupon has brand/model restrictions, only count matching items' subtotal.
     * If no restriction, use full sub_total.
     */
    private function getApplicableSubtotal(Cart $cart, Coupon $coupon): float
    {
        $hasBrandFilter = ! empty($coupon->brand_ids);
        $hasModelFilter = ! empty($coupon->model_ids);

        if (! $hasBrandFilter && ! $hasModelFilter) {
            return (float) $cart->sub_total;
        }

        $cart->load('items.variant.product');
        $applicable = 0;

        foreach ($cart->items as $item) {
            $brandId = $item->variant?->product?->brand_id;
            $modelId = $item->variant?->product?->model_id;

            $matchesBrand = $hasBrandFilter ? in_array($brandId, $coupon->brand_ids) : true;
            $matchesModel = $hasModelFilter ? in_array($modelId, $coupon->model_ids) : true;

            if ($matchesBrand && $matchesModel) {
                $applicable += (float) $item->total_price;
            }
        }

        return $applicable;
    }
}
