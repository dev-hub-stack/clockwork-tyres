<?php

namespace App\Mail;

use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\Payment;
use App\Modules\Settings\Models\CompanyBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class PaymentReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $record,
        public Payment $payment
    ) {}

    public function envelope(): Envelope
    {
        $company = CompanyBranding::getActive()?->company_name ?? 'TunerStop LLC';
        return new Envelope(
            subject: 'Payment Receipt for Invoice #' . $this->record->order_number . ' | ' . $company,
        );
    }

    public function content(): Content
    {
        $branding = CompanyBranding::getActive();
        $logoPath = $branding?->logo_path;
        $emailLogoUrl = null;
        if ($logoPath) {
            $cdnUrl = rtrim(config('filesystems.disks.s3.url', ''), '/');
            $emailLogoUrl = $cdnUrl
                ? $cdnUrl . '/' . ltrim($logoPath, '/')
                : Storage::disk('public')->url($logoPath);
        }
        return new Content(
            view: 'emails.payment-receipt',
            with: ['emailLogoUrl' => $emailLogoUrl],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
