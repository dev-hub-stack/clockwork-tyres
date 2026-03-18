<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Modules\Orders\Models\Payment;
use Illuminate\Http\Request;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Stripe;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $webhookSecret = config('services.stripe.webhook_secret');

        if (empty($webhookSecret)) {
            return response()->json(['error' => 'Webhook secret not configured'], 500);
        }

        try {
            Stripe::setApiKey(config('services.stripe.secret'));
            $event = Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
        } catch (SignatureVerificationException $e) {
            return response()->json(['error' => 'Invalid signature'], 400);
        } catch (\UnexpectedValueException $e) {
            return response()->json(['error' => 'Invalid payload'], 400);
        }

        match ($event->type) {
            'charge.captured' => $this->handleChargeCaptured($event->data->object),
            default => null,
        };

        return response()->json(['received' => true]);
    }

    private function handleChargeCaptured(object $charge): void
    {
        $payment = Payment::where('reference_number', $charge->id)
            ->where('status', 'authorized')
            ->first();

        if (! $payment) {
            return;
        }

        $payment->update([
            'status' => 'completed',
            'payment_date' => now(),
            'metadata' => array_merge($payment->metadata ?? [], [
                'capture_status' => 'captured',
                'captured_at' => now()->toIso8601String(),
                'captured_via' => 'stripe_webhook',
                'captured_charge_id' => $charge->id,
            ]),
        ]);
    }
}
