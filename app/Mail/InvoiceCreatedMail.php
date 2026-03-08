<?php

namespace App\Mail;

use App\Modules\Orders\Models\Order;
use App\Modules\Settings\Models\CompanyBranding;
use App\Modules\Settings\Models\CurrencySetting;
use App\Modules\Settings\Models\TaxSetting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class InvoiceCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $record) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Invoice #' . ($this->record->order_number ?? $this->record->invoice_number) . ' from ' . (CompanyBranding::getActive()?->company_name ?? 'TunerStop LLC'),
        );
    }

    public function content(): Content
    {
        $branding  = CompanyBranding::getActive();
        $logoUrl   = $this->buildLogoUrl($branding);

        return new Content(
            view: 'emails.invoice-created',
            with: ['emailLogoUrl' => $logoUrl],
        );
    }

    public function attachments(): array
    {
        $branding   = CompanyBranding::getActive();
        $taxSetting = TaxSetting::getDefault();
        $currency   = CurrencySetting::getBase();

        $data = [
            'record'         => $this->record,
            'documentType'   => 'invoice',
            'companyName'    => $branding?->company_name ?? 'TunerStop LLC',
            'companyAddress' => $branding?->company_address ?? '',
            'companyPhone'   => $branding?->company_phone ?? '',
            'companyEmail'   => $branding?->company_email ?? '',
            'taxNumber'      => $branding?->tax_registration_number ?? '',
            'logo'           => $this->buildPdfLogoData($branding),
            'currency'       => $currency?->currency_symbol ?? 'AED',
            'vatRate'        => $taxSetting?->rate ?? 5,
            'isPdf'          => true,
        ];

        $pdf = Pdf::loadView('templates.invoice-preview', $data)
            ->setOption('isRemoteEnabled', true);

        $filename = 'Invoice_' . ($this->record->order_number ?? $this->record->id) . '.pdf';

        return [
            Attachment::fromData(fn () => $pdf->output(), $filename)
                ->withMime('application/pdf'),
        ];
    }

    private function buildLogoUrl(?CompanyBranding $branding): ?string
    {
        if (!$branding?->logo_path) return null;
        $cdn = rtrim(config('filesystems.disks.s3.url', ''), '/');
        return $cdn
            ? $cdn . '/' . ltrim($branding->logo_path, '/')
            : Storage::disk('public')->url($branding->logo_path);
    }

    private function buildPdfLogoData(?CompanyBranding $branding): ?string
    {
        if (!$branding?->logo_path) return null;
        $path = $branding->logo_path;
        $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = ['png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','gif'=>'image/gif','svg'=>'image/svg+xml','webp'=>'image/webp'][$ext] ?? 'image/png';
        if (Storage::disk('s3')->exists($path)) {
            $c = Storage::disk('s3')->get($path);
            if ($c) return 'data:' . $mime . ';base64,' . base64_encode($c);
        }
        if (Storage::disk('public')->exists($path)) {
            $c = Storage::disk('public')->get($path);
            if ($c) return 'data:' . $mime . ';base64,' . base64_encode($c);
        }
        return null;
    }
}
