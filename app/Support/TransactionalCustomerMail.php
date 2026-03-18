<?php

namespace App\Support;

use App\Modules\Settings\Models\SystemSetting;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TransactionalCustomerMail
{
    public const SUPPRESSION_KEY = 'mail.suppress_customer_emails';

    public function shouldSuppress(): bool
    {
        return (bool) SystemSetting::get(self::SUPPRESSION_KEY, false);
    }

    public function send(string|array $to, Mailable $mailable, array $context = []): string
    {
        $recipients = collect(is_array($to) ? $to : [$to])
            ->filter()
            ->values()
            ->all();

        if ($recipients === []) {
            Log::warning('Skipped outbound customer email with no recipients', [
                'mail_class' => $mailable::class,
            ] + $context);

            return 'skipped';
        }

        if ($this->shouldSuppress()) {
            Log::info('Suppressed outbound customer email', [
                'setting_key' => self::SUPPRESSION_KEY,
                'mail_class' => $mailable::class,
                'recipients' => $recipients,
            ] + $context);

            return 'suppressed';
        }

        Mail::to($recipients)->send($mailable);

        return 'sent';
    }
}