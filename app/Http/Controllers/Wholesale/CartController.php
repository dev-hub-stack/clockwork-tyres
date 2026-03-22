<?php

namespace App\Http\Controllers\Wholesale;

use App\Modules\Wholesale\Cart\Services\CartService;
use App\Modules\Customers\Models\AddressBook;
use App\Modules\Settings\Models\SystemSetting;
use App\Modules\Settings\Models\TaxSetting;
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
        // The frontend sends `{ session_id: '...', items: [ { product_variant_id: X, quantity: Y } ] }`
        $payload = $request->all();
        if ($request->has('items') && is_array($request->items) && count($request->items) > 0) {
            $item = $request->items[0];
            $payload['product_variant_id'] = $item['product_variant_id'] ?? null;
            $payload['quantity'] = $item['quantity'] ?? null;
            
            // Map the warehouse quantities if present
            if (isset($item['warehouse_qunatity']) && is_array($item['warehouse_qunatity']) && count($item['warehouse_qunatity']) > 0) {
                $payload['warehouse_qunatity'] = $item['warehouse_qunatity'];
                // Extract first warehouse_id so CartService can save it
                $payload['warehouse_id'] = $item['warehouse_qunatity'][0]['warehouse_id'] ?? null;
                // Use quantity from warehouse_qunatity if not already set
                if (empty($payload['quantity'])) {
                    $payload['quantity'] = $item['warehouse_qunatity'][0]['quantity'] ?? 1;
                }
            }
        }

        $validator = validator($payload, [
            'product_variant_id' => 'required|integer|exists:product_variants,id',
            'quantity'           => 'required|integer|min:1',
            'session_id'         => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors()
            ], 422);
        }

        $dealer = $this->dealer();
        $cart   = $this->cartService->getOrCreateCart($dealer, $payload['session_id']);

        try {
            $cart = $this->cartService->addItem($cart, $payload);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage());
        }

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

        try {
            $cart = $this->cartService->changeQuantity($cart, $request->cart_item_id, $request->quantity);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage());
        }

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
        // Frontend sends { session_id, cart_id, addons: [{ addon_id, quantity }] }
        // Unwrap the addons array into flat fields so validation works.
        $payload = $request->all();
        if (isset($payload['addons'][0])) {
            $payload['addon_id'] = $payload['addons'][0]['addon_id'] ?? null;
            $payload['quantity'] = $payload['addons'][0]['quantity'] ?? null;
        }

        $validator = validator($payload, [
            'addon_id'   => 'required|integer|exists:addons,id',
            'quantity'   => 'required|integer|min:1',
            'session_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $dealer = $this->dealer();
        $cart   = $this->cartService->getOrCreateCart($dealer, $payload['session_id']);
        $cart   = $this->cartService->addAddon($cart, $payload);

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

        $addresses = $dealer
            ? AddressBook::where('customer_id', $dealer->id)->get()
            : collect();

        // Read checkout option flags from system_settings (managed from CRM Settings page)
        $settings = SystemSetting::whereIn('key', [
            'admin.pickup',
            'admin.delivery',
            'admin.cod',
            'admin.bank',
            'admin.bank_detail',
            'admin.eta_item_message',
            'admin.shipping_rate_upto_four',
            'admin.shipping_rate_per_item',
            'credit_account_enable',
        ])->pluck('value', 'key')->toArray();

        // Tax rate comes from the main Tax Settings (not system_settings)
        $taxSetting = TaxSetting::getDefault();
        $taxRate = optional($taxSetting)->rate ?? 0;
        $taxInclusiveDefault = (bool) optional($taxSetting)->tax_inclusive_default;

        return $this->success(array_merge($settings, [
            'admin.tax_rate' => (string) $taxRate,
            'admin.tax_inclusive_default' => $taxInclusiveDefault,
            'addresses'      => $addresses,
        ]));
    }
}
