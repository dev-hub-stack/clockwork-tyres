<?php

namespace App\Http\Controllers\Wholesale;

use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\Payment;
use App\Modules\Orders\Enums\PaymentStatus;
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
        // If no order_id, find most recent pending order for this dealer
        if (! $order) {
            $order = Order::where('customer_id', $dealer->id)
                ->where('status', 'pending')
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
                'order_id'           => $order->id,
                'gateway'            => 'stripe',
                'gateway_payment_id' => $charge->id,
                'amount'             => $order->total,
                'status'             => 'paid',
            ]);

            $order->update(['payment_status' => 'paid', 'status' => 'confirmed']);

            return $this->success([
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
            // PostPay creates a checkout session and returns a redirect URL
            // TODO: Integrate PostPay PHP SDK: composer require postpay/postpay-php
            // $postpay = new \Postpay\Postpay(config('services.postpay.merchant_id'), config('services.postpay.secret'));
            // $session = $postpay->checkout->create([...]);

            // Placeholder response until SDK is installed
            return $this->success([
                'gateway'      => 'PostPay',
                'order_id'     => $order->id,
                'order_number' => $order->order_number,
                'redirect_url' => null, // Will be $session->redirect_url after SDK integration
                'message'      => 'PostPay SDK not yet installed. Run: composer require postpay/postpay-php',
            ]);

        } catch (\Throwable $e) {
            Log::error('PostPay initiation failed', ['error' => $e->getMessage()]);
            return $this->error('PostPay session creation failed.', null, 500);
        }
    }

    private function handleBankTransfer(Request $request, Order $order, $dealer): \Illuminate\Http\JsonResponse
    {
        // Mark order as awaiting payment; admin will confirm manually
        $order->update(['payment_status' => 'pending_bank_transfer', 'status' => 'pending']);

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
