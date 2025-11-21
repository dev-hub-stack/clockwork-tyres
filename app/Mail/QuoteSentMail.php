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
            'logo' => $companyBranding ? $companyBranding->logo_url : null,
            'currency' => $currency ? $currency->currency_symbol : 'AED',
            'vatRate' => $taxSetting ? $taxSetting->rate : 5,
        ];

        $pdf = Pdf::loadView('templates.invoice-preview', $data);

        return [
            Attachment::fromData(fn () => $pdf->output(), 'Quote_' . $this->record->quote_number . '.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
