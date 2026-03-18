<?php

namespace App\Services\Wholesale;

use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Models\Order;

class StripePaymentGateway
{
    public function createCharge(Order $order, Customer $dealer, string $stripeToken): object
    {
        \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

        return \Stripe\Charge::create([
            'amount' => (int) ($order->total * 100),
            'currency' => 'aed',
            'source' => $stripeToken,
            'capture' => false,
            'description' => 'Wholesale Order #' . $order->order_number,
            'metadata' => [
                'order_id' => $order->id,
                'dealer_id' => $dealer->id,
            ],
        ]);
    }

    public function captureCharge(string $chargeId): object
    {
        \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

        $charge = \Stripe\Charge::retrieve($chargeId);

        return $charge->capture();
    }
}