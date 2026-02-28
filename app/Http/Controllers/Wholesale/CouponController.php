<?php

namespace App\Http\Controllers\Wholesale;

use App\Modules\Wholesale\Cart\Services\CartService;
use App\Modules\Wholesale\Cart\Models\Cart;
use Illuminate\Http\Request;

/**
 * Coupon Controller
 * Maps to Angular: applyCoupon() → POST /api/coupon/apply
 */
class CouponController extends BaseWholesaleController
{
    public function __construct(protected CartService $cartService) {}

    /**
     * POST /api/coupon/apply
     * Body: { code, cart_id, session_id }
     */
    public function apply(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
        ]);

        $dealer = $this->dealer();

        // Resolve cart via session_id or cart_id
        if ($request->filled('session_id')) {
            $cart = $this->cartService->getOrCreateCart($dealer, $request->session_id);
        } else {
            $cart = Cart::where('dealer_id', $dealer->id)->findOrFail($request->cart_id);
        }

        $result = $this->cartService->applyCoupon($cart, $request->code);

        if (! $result['applied']) {
            return $this->error($result['message'], null, 422);
        }

        return $this->success([
            'discount' => $result['discount'],
            'coupon'   => $result['coupon'],
            'cart'     => $this->cartService->formatCartResponse($cart->refresh()),
        ], $result['message']);
    }
}
