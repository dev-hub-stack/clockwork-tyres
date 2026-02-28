<?php

namespace App\Http\Controllers\Wholesale;

use App\Modules\Wholesale\Cart\Services\CartService;
use App\Modules\Wholesale\Cart\Models\Cart;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Orders\Enums\DocumentType;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use Illuminate\Http\Request;

/**
 * Wholesale Order Controller
 *
 * Bridges the wholesale cart into the CRM's native order system.
 * Order placement converts a cart into an Order via the existing OrderService.
 *
 * Maps to Angular ApiServices:
 *   placeOrder()     → POST /api/order/store
 *   getOrder()       → GET  /api/order/{sessionId}/get
 *   getOrderById()   → GET  /api/order/getById/{orderId}
 *   getAllUserOrder() → POST /api/order/all  (we infer this from pattern)
 *   confirmOrder()   → POST /api/order/completed
 *   updateOrder()    → PUT  /api/order/update/{cartId}
 *   processOrder()   → GET  /api/order/process
 */
class OrderController extends BaseWholesaleController
{
    public function __construct(
        protected OrderService $orderService,
        protected CartService $cartService,
    ) {}

    /**
     * POST /api/order/store
     * Convert the dealer's cart into a CRM Order.
     */
    public function store(Request $request)
    {
        $request->validate([
            'session_id'         => 'sometimes|string',
            'cart_id'            => 'sometimes|integer',
            'shipping_address_id'=> 'nullable|integer',
            'payment_method'     => 'nullable|string',
            'notes'              => 'nullable|string',
        ]);

        $dealer = $this->dealer();

        // Resolve cart
        if ($request->filled('session_id')) {
            $cart = $this->cartService->getOrCreateCart($dealer, $request->session_id);
        } else {
            $cart = Cart::where('dealer_id', $dealer->id)->findOrFail($request->cart_id);
        }

        $cart->load(['items.variant.product.brand', 'items.variant.product.model', 'addons.addon']);

        if ($cart->items->isEmpty() && $cart->addons->isEmpty()) {
            return $this->error('Cart is empty.', null, 422);
        }

        // --- Create the CRM order ---
        $order = $this->orderService->createOrder([
            'document_type'      => 'order',
            'customer_id'        => $dealer->id,
            'shipping_address_id'=> $request->shipping_address_id,
            'notes'              => $request->notes,
            'payment_method'     => $request->payment_method ?? 'pending',
            'sub_total'          => $cart->sub_total,
            'discount'           => $cart->discount,
            'shipping'           => $cart->shipping,
            'tax'                => $cart->vat,
            'total'              => $cart->total,
        ]);

        // --- Add wheel items ---
        foreach ($cart->items as $item) {
            $this->orderService->addItem($order, [
                'product_variant_id' => $item->product_variant_id,
                'warehouse_id'       => $item->warehouse_id,
                'quantity'           => $item->quantity,
                'unit_price'         => $item->unit_price,
                'total_price'        => $item->total_price,
                'type'               => $item->type,
                'eta'                => $item->eta,
            ]);
        }

        // --- Add addon items ---
        foreach ($cart->addons as $addon) {
            $this->orderService->addItem($order, [
                'add_on_id'          => $addon->addon_id,
                'quantity'           => $addon->quantity,
                'unit_price'         => $addon->unit_price,
                'total_price'        => $addon->total_price,
                'type'               => 'addon',
            ]);
        }

        // Recalculate final CRM order totals
        $this->orderService->calculateTotals($order);

        // Clear the cart
        $this->cartService->clearCart($cart);

        return $this->success([
            'order_id'     => $order->id,
            'order_number' => $order->order_number,
            'total'        => (float) $order->total,
            'status'       => $order->status,
        ], 'Order placed successfully.');
    }

    /**
     * GET /api/order/{sessionId}/get
     * Get the most recent order for a session.
     */
    public function get(Request $request, string $sessionId)
    {
        $dealer = $this->dealer();

        $order = Order::where('customer_id', $dealer->id)
            ->latest()
            ->with(['items.productVariant.product.brand', 'items.addOn'])
            ->first();

        if (! $order) {
            return $this->success(null);
        }

        return $this->success($this->formatOrder($order));
    }

    /**
     * GET /api/order/getById/{orderId}
     * Get a specific order — scoped to this dealer.
     */
    public function getById(Request $request, int $orderId)
    {
        $dealer = $this->dealer();

        $order = Order::where('customer_id', $dealer->id)
            ->with(['items.productVariant.product.brand', 'items.addOn'])
            ->findOrFail($orderId);

        return $this->success($this->formatOrder($order));
    }

    /**
     * POST /api/order/all
     * All orders for the authenticated dealer, newest first, paginated.
     */
    public function all(Request $request)
    {
        $dealer = $this->dealer();

        $orders = Order::where('customer_id', $dealer->id)
            ->where('document_type', 'order')
            ->with(['items.productVariant.product.brand'])
            ->latest()
            ->paginate(20);

        return $this->success([
            'data'          => $orders->items(),
            'current_page'  => $orders->currentPage(),
            'total'         => $orders->total(),
            'last_page'     => $orders->lastPage(),
        ]);
    }

    /**
     * POST /api/order/completed
     * Mark an order as completed.
     * Body: { order_id }
     */
    public function completed(Request $request)
    {
        $request->validate(['order_id' => 'required|integer']);
        $dealer = $this->dealer();

        $order = Order::where('customer_id', $dealer->id)->findOrFail($request->order_id);
        $this->orderService->completeOrder($order);

        return $this->success(['order_id' => $order->id, 'status' => $order->fresh()->status]);
    }

    /**
     * PUT /api/order/update/{orderId}
     * Update order notes or shipping address.
     */
    public function update(Request $request, int $orderId)
    {
        $dealer = $this->dealer();
        $order  = Order::where('customer_id', $dealer->id)->findOrFail($orderId);

        $order->update($request->only(['notes', 'shipping_address_id']));

        return $this->success($this->formatOrder($order->fresh()));
    }

    /**
     * GET /api/order/process
     * Post-payment hook — confirm order and update payment status.
     * Called by Angular after successful payment redirect.
     */
    public function process(Request $request)
    {
        $dealer  = $this->dealer();
        $orderId = $request->get('order_id');

        if (! $orderId) {
            // Fall back to most recent pending order
            $order = Order::where('customer_id', $dealer->id)
                ->where('status', 'pending')
                ->latest()
                ->first();
        } else {
            $order = Order::where('customer_id', $dealer->id)->findOrFail($orderId);
        }

        if (! $order) {
            return $this->error('No pending order found.', null, 404);
        }

        // Confirm and allocate inventory
        $this->orderService->confirmOrder($order);

        return $this->success($this->formatOrder($order->fresh()));
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    private function formatOrder(Order $order): array
    {
        return [
            'id'             => $order->id,
            'order_number'   => $order->order_number,
            'status'         => $order->status,
            'payment_status' => $order->payment_status ?? null,
            'sub_total'      => (float) $order->sub_total,
            'discount'       => (float) ($order->discount ?? 0),
            'shipping'       => (float) ($order->shipping ?? 0),
            'tax'            => (float) ($order->tax ?? 0),
            'total'          => (float) $order->total,
            'notes'          => $order->notes,
            'created_at'     => $order->created_at,
            'items'          => $order->items->map(fn($item) => [
                'id'                 => $item->id,
                'product_variant_id' => $item->product_variant_id,
                'add_on_id'          => $item->add_on_id,
                'quantity'           => $item->quantity,
                'unit_price'         => (float) $item->unit_price,
                'total_price'        => (float) $item->total_price,
                'type'               => $item->type ?? 'wheel',
            ]),
        ];
    }
}
