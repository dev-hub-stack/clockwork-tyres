<?php

namespace App\Mail;

use App\Modules\Warranties\Models\WarrantyClaim;
use App\Modules\Settings\Models\CompanyBranding;
use App\Modules\Settings\Models\CurrencySetting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class WarrantyClaimSentMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public WarrantyClaim $record) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Warranty Claim #' . $this->record->claim_number . ' — ' . (CompanyBranding::getActive()?->company_name ?? 'TunerStop LLC'),
        );
    }

    public function content(): Content
    {
        $branding = CompanyBranding::getActive();
        $logoUrl  = $this->buildLogoUrl($branding);

        return new Content(
            view: 'emails.warranty-claim-sent',
            with: ['emailLogoUrl' => $logoUrl],
        );
    }

    public function attachments(): array
    {
        return [];
    }

    private function buildLogoUrl(?CompanyBranding $branding): ?string
    {
        if (!$branding?->logo_path) return null;
        $cdn = rtrim(config('filesystems.disks.s3.url', ''), '/');
        return $cdn
            ? $cdn . '/' . ltrim($branding->logo_path, '/')
            : Storage::disk('public')->url($branding->logo_path);
    }
}
