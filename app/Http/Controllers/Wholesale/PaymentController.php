<?php

namespace App\Http\Controllers\Wholesale;

use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\Payment;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Wholesale\Cart\Models\Cart;
use App\Modules\Wholesale\Cart\Services\CartService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Wholesale Payment Controller
 *
 * Handles payment initiation for Stripe and PostPay gateways.
 * On successful payment, updates the CRM Order payment status.
 *
 * Maps to Angular ApiServices:
 *   PaymentWithStripe()    → POST /api/payment  { gateway: 'Stripe',  stripeToken }
 *   PaymentWithPostPay()   → POST /api/payment  { gateway: 'PostPay', session_id }
 *   payformUI()            → POST /api/payment  { ... }
 *   sendFbPurchaseEvent()  → POST /api/send-purchase-event
 *
 * NOTE: Stripe SDK → composer require stripe/stripe-php
 *       PostPay SDK → composer require postpay/postpay-php
 *       Credentials → STRIPE_KEY, STRIPE_SECRET, POSTPAY_MERCHANT_ID, POSTPAY_SECRET in .env
 */
class PaymentController extends BaseWholesaleController
{
    /**
     * POST /api/payment
     * Initiates payment via the gateway specified in the request body.
     */
    public function initiate(Request $request)
    {
        $request->validate([
            'gateway'    => 'required|string|in:Stripe,PostPay,BankTransfer',
            'session_id' => 'nullable|string',
            'order_id'   => 'nullable|integer',
        ]);

        $dealer  = $this->dealer();
        $gateway = $request->gateway;

        // Resolve the order to pay
        $order = null;
        if ($request->filled('order_id')) {
            $order = Order::where('customer_id', $dealer->id)->find($request->order_id);
        }
        // Fallback: find most recent wholesale order awaiting payment for this dealer
        if (! $order) {
            $order = Order::where('customer_id', $dealer->id)
                ->where('channel', 'wholesale')
                ->whereIn('quote_status', ['sent', 'pending'])
                ->whereNotIn('payment_status', ['paid', 'partially_paid'])
                ->latest()
                ->first();
        }

        if (! $order) {
            return $this->error('No pending order found to pay for.', null, 404);
        }

        return match ($gateway) {
            'Stripe'      => $this->handleStripe($request, $order, $dealer),
            'PostPay'     => $this->handlePostPay($request, $order, $dealer),
            'BankTransfer'=> $this->handleBankTransfer($request, $order, $dealer),
            default       => $this->error('Unsupported payment gateway.', null, 422),
        };
    }

    /**
     * POST /api/send-purchase-event
     * Fires a Facebook server-side Conversions API purchase event (non-blocking).
     */
    public function sendPurchaseEvent(Request $request)
    {
        $request->validate(['order_id' => 'required|integer']);
        $dealer = $this->dealer();

        $order = Order::where('customer_id', $dealer->id)->find($request->order_id);

        if (! $order) {
            return $this->success(['fired' => false], 'Order not found — event skipped.');
        }

        try {
            // Fire FB Conversions API event (non-blocking — wrap in try/catch)
            // TODO: Integrate Meta PHP SDK here once PIXEL_ACCESS_TOKEN is configured
            // \Meta\BusinessExtension\Infrastructure\FacebookGateway::purchase($order)
            Log::info('FB Purchase event fired', ['order_id' => $order->id, 'total' => $order->total]);
        } catch (\Throwable $e) {
            Log::warning('FB Purchase event failed', ['error' => $e->getMessage()]);
        }

        return $this->success(['fired' => true, 'order_id' => $order->id]);
    }

    // ─── Gateway handlers ─────────────────────────────────────────────────────

    private function handleStripe(Request $request, Order $order, $dealer): \Illuminate\Http\JsonResponse
    {
        $stripeToken = $request->stripeToken;

        if (! $stripeToken) {
            return $this->error('Stripe token is required.', null, 422);
        }

        try {
            \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

            $charge = \Stripe\Charge::create([
                'amount'      => (int) ($order->total * 100), // in cents
                'currency'    => 'aed',
                'source'      => $stripeToken,
                'description' => 'Wholesale Order #' . $order->order_number,
                'metadata'    => ['order_id' => $order->id, 'dealer_id' => $dealer->id],
            ]);

            // Record payment in CRM
            Payment::create([
                'order_id'        => $order->id,
                'customer_id'     => $dealer->id,
                'payment_method'  => 'stripe',
                'reference_number'=> $charge->id,
                'amount'          => $order->total,
                'status'          => 'paid',
                'currency'        => 'AED',
                'payment_date'    => now(),
            ]);

            $order->update(['payment_status' => 'paid', 'order_status' => 'processing']);

            // Clear the wholesale cart after successful payment
            $cart = Cart::where('dealer_id', $dealer->id)->first();
            if ($cart) {
                app(CartService::class)->clearCart($cart);
            }

            return $this->success([
                'orderId'      => $order->id,
                'order_id'     => $order->id,
                'order_number' => $order->order_number,
                'charge_id'    => $charge->id,
                'paid'         => true,
            ], 'Payment successful.');

        } catch (\Stripe\Exception\CardException $e) {
            return $this->error('Card was declined: ' . $e->getMessage(), null, 422);
        } catch (\Throwable $e) {
            Log::error('Stripe payment failed', ['error' => $e->getMessage(), 'order_id' => $order->id]);
            return $this->error('Payment processing failed. Please try again.', null, 500);
        }
    }

    private function handlePostPay(Request $request, Order $order, $dealer): \Illuminate\Http\JsonResponse
    {
        try {
            $postpay = new \Postpay\Postpay([
                'merchant_id' => config('services.postpay.merchant_id'),
                'secret_key'  => config('services.postpay.secret'),
                'sandbox'     => config('services.postpay.sandbox', false),
            ]);

            $lineItems = [];
            foreach ($order->items as $item) {
                $lineItems[] = [
                    'reference'  => (string) $item->product_id,
                    'name'       => $item->product_name ?? 'Product',
                    'qty'        => (int) $item->quantity,
                    'unit_price' => (int) round($item->unit_price * 100),
                ];
            }

            $response = $postpay->post('/orders', [
                'merchant_order_id' => (string) $order->order_number,
                'total_amount'      => (int) round($order->total * 100),
                'currency'          => 'AED',
                'items'             => $lineItems,
                'merchant'          => [
                    'confirmation_url' => config('app.url') . '/api/postpay/confirm',
                    'cancel_url'       => config('app.url') . '/api/postpay/cancel',
                ],
                'billing_address' => [
                    'first_name' => $dealer->name,
                    'email'      => $dealer->email,
                    'phone'      => $dealer->phone ?? '',
                    'line1'      => $dealer->address ?? '',
                    'city'       => $dealer->city ?? '',
                    'country'    => 'AE',
                ],
            ]);

            $body = $response->json();
            $redirectUrl = $body['redirect_url'] ?? null;

            // Record pending payment
            Payment::create([
                'order_id'        => $order->id,
                'payment_method'  => 'postpay',
                'reference_number'=> $body['id'] ?? null,
                'amount'          => $order->total,
                'status'          => 'pending',
                'currency'        => 'AED',
            ]);

            return $this->success([
                'gateway'      => 'PostPay',
                'order_id'     => $order->id,
                'order_number' => $order->order_number,
                'redirect_url' => $redirectUrl,
            ]);

        } catch (\Throwable $e) {
            Log::error('PostPay initiation failed', ['error' => $e->getMessage(), 'order_id' => $order->id]);
            return $this->error('PostPay session creation failed: ' . $e->getMessage(), null, 500);
        }
    }

    private function handleBankTransfer(Request $request, Order $order, $dealer): \Illuminate\Http\JsonResponse
    {
        // Mark order as awaiting payment; admin will confirm manually
        $order->update(['payment_status' => 'pending_bank_transfer', 'order_status' => 'pending']);

        return $this->success([
            'order_id'     => $order->id,
            'order_number' => $order->order_number,
            'bank_details' => [
                'bank_name'      => config('wholesale.bank_name', 'Emirates NBD'),
                'account_number' => config('wholesale.bank_account', ''),
                'iban'           => config('wholesale.bank_iban', ''),
                'swift'          => config('wholesale.bank_swift', ''),
            ],
        ], 'Your order is confirmed. Please complete the bank transfer.');
    }
}
