<?php

namespace App\Http\Controllers\Wholesale;

use App\Modules\Wholesale\Cart\Services\CartService;
use App\Modules\Customers\Models\AddressBook;
use Illuminate\Http\Request;

/**
 * Wholesale Cart Controller
 *
 * Handles all cart operations for the Angular shopping experience.
 * All heavy logic is delegated to CartService — controllers stay thin.
 *
 * Maps to Angular ApiServices:
 *   addToCart()              → POST   /api/cart/add
 *   getCart()                → GET    /api/cart/{sessionId}/get
 *   changeQuantity()         → POST   /api/cart/change-quantity
 *   changeWareouseQuantity() → POST   /api/cart/change-quantity  (same endpoint)
 *   deleteCartItem()         → DELETE /api/cart/{itemId}/delete
 *   deleteAllCartItem()      → DELETE /api/cart/clear/{sessionId}
 *   addonsAddToCart()        → POST   /api/cart/add-addon
 *   addonsRemoveFromCart()   → DELETE /api/cart/{addonId}/delete-addon
 *   changeAddonQuantity()    → POST   /api/cart/add-on-change-quantity
 *   checkoutOptions()        → GET    /api/checkout-options
 */
class CartController extends BaseWholesaleController
{
    public function __construct(
        protected CartService $cartService
    ) {}

    /**
     * POST /api/cart/add
     * Add a product variant to the cart.
     */
    public function add(Request $request)
    {
        $request->validate([
            'product_variant_id' => 'required|integer|exists:product_variants,id',
            'quantity'           => 'required|integer|min:1',
            'session_id'         => 'required|string',
        ]);

        $dealer = $this->dealer();
        $cart   = $this->cartService->getOrCreateCart($dealer, $request->session_id);
        $cart   = $this->cartService->addItem($cart, $request->all());

        return $this->success($this->cartService->formatCartResponse($cart));
    }

    /**
     * GET /api/cart/{sessionId}/get
     * Retrieve the cart for a given session.
     */
    public function get(Request $request, string $sessionId)
    {
        $dealer = $this->dealer();
        $cart   = $this->cartService->getOrCreateCart($dealer, $sessionId);

        return $this->success($this->cartService->formatCartResponse($cart));
    }

    /**
     * POST /api/cart/change-quantity
     * Update quantity of a cart item (wheel), optionally with warehouse change.
     */
    public function changeQuantity(Request $request)
    {
        $request->validate([
            'cart_item_id' => 'required|integer',
            'quantity'     => 'required|integer|min:0',
        ]);

        $dealer = $this->dealer();
        $cart   = $this->cartService->getOrCreateCart($dealer, $request->session_id ?? '');
        $cart   = $this->cartService->changeQuantity($cart, $request->cart_item_id, $request->quantity);

        return $this->success($this->cartService->formatCartResponse($cart));
    }

    /**
     * DELETE /api/cart/{itemId}/delete
     * Remove a wheel item from the cart.
     */
    public function deleteItem(Request $request, int $itemId)
    {
        $dealer = $this->dealer();
        // Find the cart that owns this item (scoped to dealer for security)
        $cart = \App\Modules\Wholesale\Cart\Models\Cart::where('dealer_id', $dealer->id)
            ->whereHas('items', fn($q) => $q->where('id', $itemId))
            ->firstOrFail();

        $cart = $this->cartService->removeItem($cart, $itemId);

        return $this->success($this->cartService->formatCartResponse($cart));
    }

    /**
     * DELETE /api/cart/clear/{sessionId}
     * Clear all items from the cart.
     */
    public function clearCart(Request $request, string $sessionId)
    {
        $dealer = $this->dealer();
        $cart   = $this->cartService->getOrCreateCart($dealer, $sessionId);
        $this->cartService->clearCart($cart);

        return $this->success($this->cartService->formatCartResponse($cart->refresh()));
    }

    /**
     * POST /api/cart/add-addon
     * Add an accessory add-on to the cart.
     */
    public function addAddon(Request $request)
    {
        $request->validate([
            'addon_id'   => 'required|integer|exists:addons,id',
            'quantity'   => 'required|integer|min:1',
            'session_id' => 'required|string',
        ]);

        $dealer = $this->dealer();
        $cart   = $this->cartService->getOrCreateCart($dealer, $request->session_id);
        $cart   = $this->cartService->addAddon($cart, $request->all());

        return $this->success($this->cartService->formatCartResponse($cart));
    }

    /**
     * DELETE /api/cart/{addonId}/delete-addon
     * Remove an add-on from the cart.
     */
    public function removeAddon(Request $request, int $addonId)
    {
        $dealer = $this->dealer();
        $cart   = \App\Modules\Wholesale\Cart\Models\Cart::where('dealer_id', $dealer->id)
            ->whereHas('addons', fn($q) => $q->where('addon_id', $addonId))
            ->firstOrFail();

        $cart = $this->cartService->removeAddon($cart, $addonId);

        return $this->success($this->cartService->formatCartResponse($cart));
    }

    /**
     * POST /api/cart/add-on-change-quantity
     * Update quantity of an add-on in the cart.
     */
    public function changeAddonQuantity(Request $request)
    {
        $request->validate([
            'addon_id'   => 'required|integer',
            'quantity'   => 'required|integer|min:0',
            'session_id' => 'required|string',
        ]);

        $dealer = $this->dealer();
        $cart   = $this->cartService->getOrCreateCart($dealer, $request->session_id);
        $cart   = $this->cartService->changeAddonQuantity($cart, $request->addon_id, $request->quantity);

        return $this->success($this->cartService->formatCartResponse($cart));
    }

    /**
     * GET /api/checkout-options
     * Returns available shipping methods, payment gateways, and dealer's saved addresses.
     */
    public function checkoutOptions(Request $request)
    {
        $dealer = $this->dealer();

        $addresses = AddressBook::where('customer_id', $dealer->id)->get();

        return $this->success([
            'shipping_methods' => collect(config('wholesale.shipping_rates', []))->map(fn($rate, $key) => [
                'id'   => $key,
                'name' => ucfirst($key),
                'rate' => $rate,
            ])->values(),
            'payment_gateways' => config('wholesale.payment_gateways', []),
            'addresses'        => $addresses,
        ]);
    }
}
