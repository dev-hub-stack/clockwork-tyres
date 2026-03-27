<?php

namespace App\Mail;

use App\Modules\Settings\Models\CompanyBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class RestockConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public array $item,
    ) {}

    public function envelope(): Envelope
    {
        $company = CompanyBranding::getActive()?->company_name ?? 'TunerStop LLC';

        return new Envelope(
            subject: 'Restock alert requested for ' . ($this->item['sku'] ?? $this->item['name'] ?? 'item') . ' | ' . $company,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.restock-confirmation',
            with: [
                'item' => $this->item,
                'emailLogoUrl' => $this->resolveLogoUrl(),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }

    private function resolveLogoUrl(): ?string
    {
        $branding = CompanyBranding::getActive();
        $logoPath = $branding?->logo_path;

        if (! $logoPath) {
            return null;
        }

        $cdnUrl = rtrim(config('filesystems.disks.s3.url', ''), '/');

        return $cdnUrl
            ? $cdnUrl . '/' . ltrim($logoPath, '/')
            : Storage::disk('public')->url($logoPath);
    }
}