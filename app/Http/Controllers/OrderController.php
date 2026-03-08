<?php

namespace App\Http\Controllers;

use App\Modules\Orders\Models\Order;
use App\Modules\Settings\Models\CurrencySetting;
use App\Modules\Settings\Models\TaxSetting;
use App\Modules\Settings\Models\CompanyBranding;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class OrderController extends Controller
{
    /**
     * Helper to get logo path/data for PDF (DomPDF-compatible).
     * Returns an absolute local path when the logo is on the public disk,
     * or a base64 data URI for remote URLs.
     */
    private function getPdfLogoPath($companyBranding)
    {
        if (!$companyBranding || !$companyBranding->logo_path) {
            return null;
        }

        // Logo is uploaded to Storage::disk('public') — return absolute path for DomPDF
        if (Storage::disk('public')->exists($companyBranding->logo_path)) {
            return Storage::disk('public')->path($companyBranding->logo_path);
        }

        // Remote URL (S3 / CloudFront): fetch and embed as base64 data URI
        $url = $companyBranding->logo_url ?? null;
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
                Log::warning('Could not fetch logo for PDF embed: ' . $e->getMessage());
            }
        }

        return null;
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
