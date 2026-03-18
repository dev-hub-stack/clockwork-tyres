<?php

namespace App\Observers;

use App\Modules\Orders\Models\Payment;
use App\Services\ActivityLogService;

class PaymentObserver
{
    public function created(Payment $payment): void
    {
        $userId = $payment->recorded_by ?? auth()->id();

        if (! $userId) {
            return;
        }

        $documentNumber = $payment->order?->order_number ?? ('#' . $payment->order_id);
        $amount = number_format((float) $payment->amount, 2);

        if ((float) $payment->amount < 0 || $payment->status === 'refunded') {
            ActivityLogService::log(
                'payment_refunded',
                "Recorded refund of {$amount} for invoice {$documentNumber}",
                $payment,
                $userId,
            );

            return;
        }

        ActivityLogService::log(
            'payment_recorded',
            "Recorded payment of {$amount} for invoice {$documentNumber}",
            $payment,
            $userId,
        );
    }
}