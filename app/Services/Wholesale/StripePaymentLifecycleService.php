<?php

namespace App\Services\Wholesale;

use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\Payment;

class StripePaymentLifecycleService
{
    public function __construct(
        protected StripePaymentGateway $stripePaymentGateway,
    ) {}

    public function authorizeOrderPayment(Order $order, Customer $dealer, string $stripeToken): Payment
    {
        $charge = $this->stripePaymentGateway->createCharge($order, $dealer, $stripeToken);

        $payment = Payment::create([
            'order_id' => $order->id,
            'customer_id' => $dealer->id,
            'payment_method' => 'stripe',
            'payment_type' => 'authorization',
            'reference_number' => $charge->id,
            'amount' => $order->total,
            'status' => 'authorized',
            'payment_date' => now(),
            'currency' => 'AED',
            'metadata' => [
                'stripe_charge_id' => $charge->id,
                'capture_status' => 'authorized',
                'authorized_at' => now()->toIso8601String(),
            ],
        ]);

        $order->update([
            'payment_method' => 'stripe',
            'payment_gateway' => 'Stripe',
            'payment_status' => PaymentStatus::PENDING,
        ]);

        return $payment;
    }

    public function captureAuthorizedPayment(Order $order): array
    {
        $payment = $order->payments()
            ->where('payment_method', 'stripe')
            ->latest('id')
            ->first();

        if (! $payment) {
            return ['status' => 'not_applicable', 'payment' => null];
        }

        if ($payment->status === 'completed') {
            return ['status' => 'already_captured', 'payment' => $payment];
        }

        if ($payment->status !== 'authorized') {
            return ['status' => 'not_applicable', 'payment' => $payment];
        }

        $capturedCharge = $this->stripePaymentGateway->captureCharge($payment->reference_number);

        $payment->update([
            'status' => 'completed',
            'payment_date' => now(),
            'metadata' => array_merge($payment->metadata ?? [], [
                'capture_status' => 'captured',
                'captured_at' => now()->toIso8601String(),
                'captured_charge_id' => $capturedCharge->id,
            ]),
        ]);

        $order->refresh();

        return ['status' => 'captured', 'payment' => $payment->fresh()];
    }
}