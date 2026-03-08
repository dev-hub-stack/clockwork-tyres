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
        return new Content(
            view: 'emails.quote-sent',
        );
    }

    /**
     * Fetch company logo and return a base64 data URI for DomPDF embedding.
     */
    private function getPdfLogoData(?CompanyBranding $branding): ?string
    {
        if (!$branding) {
            return null;
        }

        $url = $branding->logo_url ?? null;
        if ($url) {
            try {
                $contents = @file_get_contents($url);
                if ($contents !== false && strlen($contents) > 0) {
                    $ext     = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
                    $mimeMap = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
                                'gif' => 'image/gif', 'svg' => 'image/svg+xml', 'webp' => 'image/webp'];
                    $mime    = $mimeMap[$ext] ?? 'image/png';
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
        ];

        $pdf = Pdf::loadView('templates.invoice-preview', $data)
            ->setOption('isRemoteEnabled', true);

        return [
            Attachment::fromData(fn () => $pdf->output(), 'Quote_' . $this->record->quote_number . '.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
