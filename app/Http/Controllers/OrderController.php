<?php

namespace App\Http\Controllers;

use App\Modules\Orders\Models\Order;
use App\Modules\Settings\Models\CurrencySetting;
use App\Modules\Settings\Models\TaxSetting;
use App\Modules\Settings\Models\CompanyBranding;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class OrderController extends Controller
{
    /**
     * Helper to get logo path for PDF
     */
    private function getPdfLogoPath($companyBranding)
    {
        if (!$companyBranding || !$companyBranding->logo_path) {
            return null;
        }

        // If using local storage, get the absolute system path
        if (Storage::disk('public')->exists($companyBranding->logo_path)) {
            return Storage::disk('public')->path($companyBranding->logo_path);
        }

        // Fallback to URL (e.g. S3) - DomPDF needs allow_url_fopen enabled
        return $companyBranding->logo_url;
    }

    /**
     * Download Delivery Note PDF
     */
    public function deliveryNote(Order $order)
    {
        // Get settings
        $currency = CurrencySetting::getBase()?->currency_symbol ?? 'AED';
        $vatRate = TaxSetting::getDefault()?->rate ?? 5;
        $companyBranding = \App\Modules\Settings\Models\CompanyBranding::getActive();
        
        // Get logo path compatible with DomPDF
        $logoPath = $this->getPdfLogoPath($companyBranding);
        
        // Use existing invoice-preview template but customize title
        $pdf = Pdf::loadView('templates/invoice-preview', [
            'record' => $order,
            'documentType' => 'delivery_note', // Custom type
            'currency' => $currency,
            'vatRate' => $vatRate,
            'logo' => $logoPath,
            'companyName' => $companyBranding->company_name ?? 'TunerStop LLC',
            'companyAddress' => $companyBranding->company_address ?? '479 Jahanzeb Block street 14',
            'companyPhone' => $companyBranding->company_phone ?? '0334934247',
            'companyEmail' => $companyBranding->company_email ?? 'admin@tunerstop.com',
            'taxNumber' => $companyBranding->tax_registration_number ?? '66666684444',
            'isPdf' => true,
        ])->setOption(['isRemoteEnabled' => true]);
        
        $filename = 'delivery-note-' . $order->order_number . '.pdf';
        
        return $pdf->stream($filename);
    }
    
    /**
     * Download Invoice PDF
     */
    public function invoice(Order $order)
    {
        // Get settings
        $currency = CurrencySetting::getBase()?->currency_symbol ?? 'AED';
        $vatRate = TaxSetting::getDefault()?->rate ?? 5;
        $companyBranding = \App\Modules\Settings\Models\CompanyBranding::getActive();
        
        // Get logo path compatible with DomPDF
        $logoPath = $this->getPdfLogoPath($companyBranding);
        
        // Determine document type based on order
        $documentType = $order->document_type === 'quote' ? 'quote' : 'invoice';
        
        // Use existing invoice-preview template
        $pdf = Pdf::loadView('templates/invoice-preview', [
            'record' => $order,
            'documentType' => $documentType,
            'currency' => $currency,
            'vatRate' => $vatRate,
            'logo' => $logoPath,
            'companyName' => $companyBranding->company_name ?? 'TunerStop LLC',
            'companyAddress' => $companyBranding->company_address ?? '479 Jahanzeb Block street 14',
            'companyPhone' => $companyBranding->company_phone ?? '0334934247',
            'companyEmail' => $companyBranding->company_email ?? 'admin@tunerstop.com',
            'taxNumber' => $companyBranding->tax_registration_number ?? '66666684444',
            'isPdf' => true,
        ])->setOption(['isRemoteEnabled' => true]);
        
        $filename = $documentType . '-' . ($order->order_number ?? $order->quote_number) . '.pdf';
        
        return $pdf->stream($filename);
    }
}
