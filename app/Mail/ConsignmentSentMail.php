<?php

namespace App\Mail;

use App\Modules\Consignments\Models\Consignment;
use App\Modules\Settings\Models\CompanyBranding;
use App\Modules\Settings\Models\CurrencySetting;
use App\Modules\Settings\Models\TaxSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ConsignmentSentMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Consignment $record) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Consignment #' . $this->record->consignment_number . ' from ' . (CompanyBranding::getActive()?->company_name ?? 'TunerStop LLC'),
        );
    }

    public function content(): Content
    {
        $branding = CompanyBranding::getActive();
        $logoUrl  = $this->buildLogoUrl($branding);

        return new Content(
            view: 'emails.consignment-sent',
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
