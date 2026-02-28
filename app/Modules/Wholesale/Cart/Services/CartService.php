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
    public function getOrCreateCart(Customer $dealer, string $sessionId): Cart
    {
        return Cart::withTrashed(false)
            ->where(function ($q) use ($dealer, $sessionId) {
                $q->where('session_id', $sessionId)
                  ->orWhere('dealer_id', $dealer->id);
            })
            ->with(['items.variant.product.brand', 'addons.addon', 'coupon'])
            ->firstOrCreate(
                ['session_id' => $sessionId],
                ['dealer_id' => $dealer->id]
            );
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
     * Set shipping cost based on selected option (from config/wholesale.php).
     */
    public function calculateShipping(Cart $cart, string $option): Cart
    {
        $rates = config('wholesale.shipping_rates', []);

        // Check if order qualifies for free shipping
        if ((float) $cart->sub_total >= config('wholesale.free_shipping_threshold', 9999999)) {
            $cart->shipping = 0;
            $cart->shipping_option = 'free';
        } else {
            $cart->shipping = $rates[$option] ?? $rates['standard'] ?? 50.00;
            $cart->shipping_option = $option;
        }

        return $this->recalculateAndReturn($cart);
    }

    /**
     * Apply VAT to the cart based on the configured VAT rate (default 5%).
     */
    public function calculateVat(Cart $cart): Cart
    {
        $vatRate  = config('wholesale.vat_rate', 0.05);
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
        $cart->load('items', 'addons');

        $itemsTotal  = $cart->items->sum('total_price');
        $addonsTotal = $cart->addons->sum('total_price');
        $subTotal    = round($itemsTotal + $addonsTotal, 2);

        $discount    = (float) $cart->discount;
        $shipping    = (float) $cart->shipping;
        $vatRate     = config('wholesale.vat_rate', 0.05);
        $taxable     = max(0, $subTotal - $discount + $shipping);
        $vat         = round($taxable * $vatRate, 2);
        $total       = round($taxable + $vat, 2);

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
        $cart->load(['items.variant.product.brand', 'items.variant.finishRelation', 'items.warehouse', 'addons.addon.category', 'coupon', 'shippingAddress']);
        $dealer = $cart->dealer ?? Customer::find($cart->dealer_id);

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
                'warehouse'          => $item->warehouse ? ['id' => $item->warehouse->id, 'name' => $item->warehouse->name] : null,
                'type'               => $item->type,
                'quantity'           => $item->quantity,
                'price'              => (float) $item->unit_price,
                'total'              => (float) $item->total_price,
                'eta'                => $item->eta,
                'discount'           => 0,
            ];
        });

        $addons = $cart->addons->map(function (CartAddon $a) use ($dealer) {
            return [
                'id'         => $a->id,
                'cart_id'    => $a->cart_id,
                'addon_id'   => $a->addon_id,
                'addon'      => $a->addon ? $this->transformer->formatAddon($a->addon, $dealer) : [],
                'quantity'   => $a->quantity,
                'price'      => (float) $a->unit_price,
                'total'      => (float) $a->total_price,
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
