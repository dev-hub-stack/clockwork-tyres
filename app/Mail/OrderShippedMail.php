<?php

namespace App\Mail;

use App\Modules\Orders\Models\Order;
use App\Modules\Settings\Models\CompanyBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class OrderShippedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $record
    ) {}

    public function envelope(): Envelope
    {
        $company = CompanyBranding::getActive()?->company_name ?? 'TunerStop LLC';
        return new Envelope(
            subject: 'Your Order #' . $this->record->order_number . ' Has Been Shipped | ' . $company,
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
            view: 'emails.order-shipped',
            with: ['emailLogoUrl' => $emailLogoUrl],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
