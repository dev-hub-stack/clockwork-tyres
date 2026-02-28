<?php

namespace App\Http\Controllers\Wholesale;

use App\Modules\Wholesale\Cart\Services\CartService;
use App\Modules\Wholesale\Cart\Models\Cart;
use Illuminate\Http\Request;

/**
 * Shipping + VAT Controller
 *
 * Maps to Angular:
 *   calculateShipping() → GET /api/calculate-shipping/{options}/{cartId}
 *   claculateVat()      → GET /api/calculate-vat/{cartId}
 */
class ShippingController extends BaseWholesaleController
{
    public function __construct(protected CartService $cartService) {}

    /**
     * GET /api/calculate-shipping/{options}/{cartId}
     * options = shipping method string (standard, express, DHL)
     */
    public function calculate(Request $request, string $options, int $cartId)
    {
        $dealer = $this->dealer();
        $cart   = Cart::where('dealer_id', $dealer->id)->findOrFail($cartId);
        $cart   = $this->cartService->calculateShipping($cart, $options);

        return $this->success($this->cartService->formatCartResponse($cart));
    }

    /**
     * GET /api/calculate-vat/{cartId}
     */
    public function calculateVat(Request $request, int $cartId)
    {
        $dealer = $this->dealer();
        $cart   = Cart::where('dealer_id', $dealer->id)->findOrFail($cartId);
        $cart   = $this->cartService->calculateVat($cart);

        return $this->success($this->cartService->formatCartResponse($cart));
    }
}
