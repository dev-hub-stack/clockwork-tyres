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
            'delivery_options'   => 'nullable|string',
            'notes'              => 'nullable|string',
            'billing'            => 'nullable|array',
            'shipping'           => 'nullable|array',
            'is_same_shipping'   => 'nullable|boolean',
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

        // --- Save billing/shipping address from checkout form into address_books ---
        $billingData  = $request->billing ?? [];
        $shippingData = $request->boolean('is_same_shipping') ? $billingData : ($request->shipping ?? $billingData);

        $addressBook = \App\Modules\Customers\Models\AddressBook::updateOrCreate(
            [
                'customer_id'  => $dealer->id,
                'address_type' => 2, // 2 = shipping
            ],
            [
                'first_name' => $billingData['first_name'] ?? '',
                'last_name'  => $billingData['last_name']  ?? '',
                'phone_no'   => $billingData['phone']      ?? '',
                'email'      => $billingData['email']      ?? '',
                'country'    => $shippingData['country']   ?? '',
                'city'       => $shippingData['city']      ?? '',
                'address'    => $shippingData['address']   ?? '',
            ]
        );
        // --- Create the CRM quote ---
        $order = $this->orderService->createOrder([
            'document_type'      => DocumentType::QUOTE->value,
            'quote_status'       => 'sent',
            'channel'            => 'wholesale',
            'currency'           => 'AED',
            'customer_id'        => $dealer->id,
            'shipping_address_id'=> $addressBook->id,
            'order_notes'        => $request->notes ?? $request->order_notes,
            'payment_method'     => $request->payment_method ?? 'pending',
            'delivery_options'   => $request->delivery_options,
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
                'tax_inclusive'      => true, // prices from cart are final, never re-apply pricing
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
                'tax_inclusive'      => true, // prices from cart are final
                'type'               => 'addon',
            ]);
        }

        // Do NOT recalculate — totals were already correctly computed by CartService.
        // addItem() internally calls calculateTotals() which overwrites with wrong values,
        // so we force-restore the correct cart totals here as the final write.
        $order->update([
            'quote_status'       => 'sent',
            'payment_method'     => $request->payment_method ?? 'pending',
            'delivery_options'   => $request->delivery_options,
            'shipping_address_id'=> $addressBook->id,
            'sub_total'          => $cart->sub_total,
            'tax'                => $cart->vat,
            'total'              => $cart->total,
            'discount'           => $cart->discount,
            'shipping'           => $cart->shipping,
        ]);

        // DO NOT clear the cart here — cart is only cleared after successful payment.
        // This allows the user to return and retry payment if they navigate away.

        return $this->success([
            'order_id'     => $order->id,
            'order_number' => $order->quote_number ?? $order->order_number,
            'total'        => (float) $order->total,
            'status'       => $order->quote_status,
            'document_type'=> $order->document_type,
        ], 'Your order has been placed and is pending review.');
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
            ->with(['items.productVariant.product.brand', 'items.addon'])
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
            ->with(['items.productVariant.product.brand', 'items.addon'])
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
            ->where('document_type', DocumentType::QUOTE->value)
            ->where('channel', 'wholesale')
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
            // Fall back to most recent wholesale quote
            $order = Order::where('customer_id', $dealer->id)
                ->where('document_type', DocumentType::QUOTE->value)
                ->where('channel', 'wholesale')
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

        // Clear the dealer's cart now that payment is confirmed — standard e-commerce flow:
        // cart is only emptied after successful payment, not at order placement.
        $cart = Cart::where('dealer_id', $dealer->id)->first();
        if ($cart) {
            $this->cartService->clearCart($cart);
        }

        return $this->success($this->formatOrder($order->fresh()));
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    private function formatOrder(Order $order): array
    {
        // Load address for billing/shipping display
        $address = $order->shipping_address_id
            ? \App\Modules\Customers\Models\AddressBook::find($order->shipping_address_id)
            : null;

        $addressData = [
            'first_name' => $address?->first_name ?? '',
            'last_name'  => $address?->last_name  ?? '',
            'phone'      => $address?->phone_no   ?? '',
            'phone_no'   => $address?->phone_no   ?? '',
            'email'      => $address?->email      ?? '',
            'country'    => $address?->country    ?? '',
            'city'       => $address?->city       ?? '',
            'address'    => $address?->address    ?? '',
        ];

        // Billing = same address (wholesale uses one address for both)
        $billingData = $addressData;

        return [
            'id'               => $order->id,
            'order_number'     => $order->quote_number ?? $order->order_number,
            'document_type'    => $order->document_type,
            'status'           => $order->quote_status ?? $order->order_status,
            'payment_status'   => $order->payment_status ?? null,
            'channel'          => $order->channel,
            'sub_total'        => (float) $order->sub_total,
            'discount'         => (float) ($order->discount ?? 0),
            'shipping_amount'  => (float) ($order->shipping ?? 0),
            'vat'              => (float) ($order->tax ?? 0),
            'total'            => (float) $order->total, // trust stored total — CartService computed it correctly
            'notes'            => $order->order_notes,
            'payment_method'   => $order->payment_method,
            'delivery_options' => $order->delivery_options,
            'payment_type'     => 'full',
            'billing'          => $billingData,
            'shipping'         => $addressData,
            'shipping_address' => $addressData,
            'created_at'       => $order->created_at,
            'cart_items'       => $order->items
                ->whereNull('add_on_id')
                ->filter(fn($item) => $item->productVariant !== null)
                ->map(fn($item) => [
                    'id'                    => $item->id,
                    'product_id'            => $item->productVariant->product_id ?? null,
                    'product_variant_id'    => $item->product_variant_id,
                    'quantity'              => $item->quantity,
                    'price'                 => (float) $item->line_total,
                    'discounted'            => 0,
                    'unit_price'            => (float) $item->unit_price,
                    'product_variant'       => [
                        'sku'           => $item->productVariant->sku  ?? '',
                        'bolt_pattern'  => $item->productVariant->bolt_pattern ?? '',
                        'size'          => $item->productVariant->size ?? '',
                        'offset'        => $item->productVariant->offset ?? '',
                        'images'        => $item->productVariant->product?->images ?? [],
                        'brand'         => [
                            'name' => $item->productVariant->product?->brand?->name ?? '',
                        ],
                    ],
                ])->values(),
            'addons' => $order->items
                ->whereNotNull('add_on_id')
                ->map(fn($item) => [
                    'id'          => $item->id,
                    'title'       => $item->addon?->title ?? $item->product_name ?? '',
                    'image'       => $item->addon?->image ?? '',
                    'quantity'    => $item->quantity,
                    'unit_price'  => (float) $item->unit_price,
                    'total_price' => (float) $item->line_total,
                ])->values(),
        ];
    }
}
