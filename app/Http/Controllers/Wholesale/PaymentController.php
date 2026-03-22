<?php

namespace App\Http\Controllers\Wholesale;

use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Wholesale\Cart\Models\Cart;
use App\Modules\Wholesale\Cart\Services\CartService;
use App\Services\ActivityLogService;
use App\Services\Wholesale\StripePaymentLifecycleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends BaseWholesaleController
{
    public function __construct(
        protected StripePaymentLifecycleService $stripePaymentLifecycleService,
        protected OrderService $orderService,
        protected CartService $cartService,
    ) {}

    public function initiate(Request $request)
    {
        $request->validate([
            'gateway' => 'required|string|in:Stripe,BankTransfer',
            'session_id' => 'nullable|string',
            'order_id' => 'nullable|integer',
        ]);

        $dealer = $this->dealer();
        $gateway = $request->gateway;

        $order = null;
        if ($request->filled('order_id')) {
            $order = Order::where('customer_id', $dealer->id)->find($request->order_id);
        }

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
            'Stripe' => $this->handleStripe($request, $order, $dealer),
            'BankTransfer' => $this->handleBankTransfer($request, $order, $dealer),
            default => $this->error('Unsupported payment gateway.', null, 422),
        };
    }

    public function sendPurchaseEvent(Request $request)
    {
        $request->validate(['order_id' => 'required|integer']);
        $dealer = $this->dealer();

        $order = Order::where('customer_id', $dealer->id)->find($request->order_id);

        if (! $order) {
            return $this->success(['fired' => false], 'Order not found — event skipped.');
        }

        try {
            Log::info('FB Purchase event fired', ['order_id' => $order->id, 'total' => $order->total]);
        } catch (\Throwable $e) {
            Log::warning('FB Purchase event failed', ['error' => $e->getMessage()]);
        }

        return $this->success(['fired' => true, 'order_id' => $order->id]);
    }

    public function logFailedAttempt(Request $request)
    {
        $request->validate([
            'gateway' => 'required|string',
            'message' => 'nullable|string',
            'order_id' => 'nullable|integer',
        ]);

        $dealer = $this->dealer();
        $order = $request->filled('order_id')
            ? Order::where('customer_id', $dealer->id)->find($request->order_id)
            : null;

        $message = trim((string) ($request->message ?? 'Payment attempt failed'));

        ActivityLogService::logForCustomer(
            'dealer_payment_failed',
            'Payment failed via ' . $request->gateway . ': ' . $message,
            $order,
            $dealer->id,
        );

        return $this->success(['logged' => true], 'Payment failure recorded.');
    }

    private function handleStripe(Request $request, Order $order, $dealer): \Illuminate\Http\JsonResponse
    {
        $stripeToken = $request->stripeToken;

        if (! $stripeToken) {
            return $this->error('Stripe token is required.', null, 422);
        }

        try {
            ActivityLogService::logForCustomer(
                'dealer_payment_submitted',
                'Submitted payment via Stripe',
                $order,
                $dealer->id,
            );

            $payment = $this->stripePaymentLifecycleService->authorizeOrderPayment($order, $dealer, $stripeToken);

            $order->refresh();
            $this->orderService->confirmOrder($order->loadMissing('items.productVariant.product', 'items.addon'));
            $order->refresh();

            if ($order->order_status !== OrderStatus::PROCESSING) {
                $order->update(['order_status' => OrderStatus::PROCESSING]);
            }

            if ($order->payment_status !== PaymentStatus::PENDING) {
                $order->update(['payment_status' => PaymentStatus::PENDING]);
            }

            $cart = Cart::where('dealer_id', $dealer->id)->first();
            if ($cart) {
                $this->cartService->clearCart($cart);
            }

            return $this->success([
                'orderId' => $order->id,
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'charge_id' => $payment->reference_number,
                'authorized' => true,
                'captured' => false,
                'paid' => false,
            ], 'Payment authorized successfully.');
        } catch (\Stripe\Exception\CardException $e) {
            ActivityLogService::logForCustomer(
                'dealer_payment_failed',
                'Payment failed via Stripe: ' . $e->getMessage(),
                $order,
                $dealer->id,
            );

            return $this->error('Card was declined: ' . $e->getMessage(), null, 422);
        } catch (\Throwable $e) {
            Log::error('Stripe payment failed', ['error' => $e->getMessage(), 'order_id' => $order->id]);

            ActivityLogService::logForCustomer(
                'dealer_payment_failed',
                'Payment failed via Stripe: ' . $e->getMessage(),
                $order,
                $dealer->id,
            );

            return $this->error('Payment processing failed. Please try again.', null, 500);
        }
    }

    private function handleBankTransfer(Request $request, Order $order, $dealer): \Illuminate\Http\JsonResponse
    {
        ActivityLogService::logForCustomer(
            'dealer_payment_submitted',
            'Submitted payment via Bank Transfer',
            $order,
            $dealer->id,
        );

        $order->update([
            'payment_status' => PaymentStatus::PENDING,
            'payment_method' => 'bank_transfer',
            'payment_gateway' => 'BankTransfer',
            'order_status' => OrderStatus::PENDING,
        ]);

        $cart = Cart::where('dealer_id', $dealer->id)->first();
        if ($cart) {
            $this->cartService->clearCart($cart);
        }

        return $this->success([
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'bank_details' => [
                'bank_name' => config('wholesale.bank_name', 'Emirates NBD'),
                'account_number' => config('wholesale.bank_account', ''),
                'iban' => config('wholesale.bank_iban', ''),
                'swift' => config('wholesale.bank_swift', ''),
            ],
        ], 'Your order is confirmed. Please complete the bank transfer.');
    }
}
