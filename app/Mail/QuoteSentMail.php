<?php

namespace App\Mail;

use App\Modules\Orders\Models\Order;
use App\Modules\Settings\Models\CompanyBranding;
use App\Modules\Settings\Models\CurrencySetting;
use App\Modules\Settings\Models\TaxSetting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class QuoteSentMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $record
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Quote #' . $this->record->quote_number . ' from ' . (CompanyBranding::getActive()?->company_name ?? 'TunerStop LLC'),
        );
    }

    public function content(): Content
    {
        $branding = CompanyBranding::getActive();
        // Build the logo URL here (in PHP context, config fully loaded) and pass
        // explicitly to the view so the blade doesn't need to resolve it itself.
        $logoPath = $branding?->logo_path;
        $emailLogoUrl = null;
        if ($logoPath) {
            $cdnUrl = rtrim(config('filesystems.disks.s3.url', ''), '/');
            $emailLogoUrl = $cdnUrl
                ? $cdnUrl . '/' . ltrim($logoPath, '/')
                : Storage::disk('public')->url($logoPath);
        }

        return new Content(
            view: 'emails.quote-sent',
            with: ['emailLogoUrl' => $emailLogoUrl],
        );
    }

    /**
     * Fetch company logo and return a base64 data URI for DomPDF embedding.
     * Checks S3 disk first (new uploads), then public disk (legacy), then URL.
     */
    private function getPdfLogoData(?CompanyBranding $branding): ?string
    {
        if (!$branding || !$branding->logo_path) {
            return null;
        }

        $ext     = strtolower(pathinfo($branding->logo_path, PATHINFO_EXTENSION));
        $mimeMap = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
                    'gif' => 'image/gif', 'svg' => 'image/svg+xml', 'webp' => 'image/webp'];
        $mime    = $mimeMap[$ext] ?? 'image/png';

        // 1. S3 disk (new uploads)
        if (Storage::disk('s3')->exists($branding->logo_path)) {
            $contents = Storage::disk('s3')->get($branding->logo_path);
            if ($contents) {
                return 'data:' . $mime . ';base64,' . base64_encode($contents);
            }
        }

        // 2. Public disk (logos uploaded before S3 migration)
        if (Storage::disk('public')->exists($branding->logo_path)) {
            $contents = Storage::disk('public')->get($branding->logo_path);
            if ($contents) {
                return 'data:' . $mime . ';base64,' . base64_encode($contents);
            }
        }

        // 3. Fallback: fetch from CDN URL
        $url = $branding->logo_url ?? null;
        if ($url) {
            try {
                $contents = @file_get_contents($url);
                if ($contents !== false && strlen($contents) > 0) {
                    return 'data:' . $mime . ';base64,' . base64_encode($contents);
                }
            } catch (\Exception $e) {
                // Fall through — PDF will render without logo
            }
        }

        return null;
    }

    public function attachments(): array
    {
        // Prepare data for PDF (same as preview)
        $companyBranding = CompanyBranding::getActive();
        $taxSetting = TaxSetting::getDefault();
        $currency = CurrencySetting::getBase();

        $data = [
            'record' => $this->record,
            'documentType' => 'quote',
            'companyName' => $companyBranding->company_name ?? 'TunerStop LLC',
            'companyAddress' => $companyBranding->company_address ?? '',
            'companyPhone' => $companyBranding->company_phone ?? '',
            'companyEmail' => $companyBranding->company_email ?? '',
            'taxNumber' => $companyBranding->tax_registration_number ?? '',
            'logo' => $this->getPdfLogoData($companyBranding),
            'currency' => $currency ? $currency->currency_symbol : 'AED',
            'vatRate' => $taxSetting ? $taxSetting->rate : 5,
            'isPdf' => true,
        ];

        $pdf = Pdf::loadView('templates.invoice-preview', $data)
            ->setOption('isRemoteEnabled', true);

        return [
            Attachment::fromData(fn () => $pdf->output(), 'Quote_' . $this->record->quote_number . '.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
