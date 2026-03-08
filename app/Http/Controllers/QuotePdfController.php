<?php

namespace App\Http\Controllers;

use App\Modules\Orders\Models\Order;
use App\Modules\Settings\Models\CompanyBranding;
use App\Modules\Settings\Models\TaxSetting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class QuotePdfController extends Controller
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

    public function download(Order $quote)
    {
        Log::info('Quote PDF download requested', ['quote_id' => $quote->id, 'quote_number' => $quote->quote_number]);

        try {
            // Get settings
            $companyBranding = CompanyBranding::getActive();
            $taxSetting = TaxSetting::getDefault();
            
            Log::info('Settings retrieved', [
                'branding_found' => (bool)$companyBranding, 
                'tax_found' => (bool)$taxSetting
            ]);

            // Get logo path compatible with DomPDF
            $logoPath = $this->getPdfLogoPath($companyBranding);

            // Prepare data for the view
            $data = [
                'record' => $quote,
                'documentType' => 'quote',
                'companyName' => $companyBranding->company_name ?? 'TunerStop LLC',
                'companyAddress' => $companyBranding->company_address ?? '',
                'companyPhone' => $companyBranding->company_phone ?? '',
                'companyEmail' => $companyBranding->company_email ?? '',
                'taxNumber' => $companyBranding->tax_registration_number ?? '',
                'logo' => $logoPath,
                'currency' => 'AED',
                'vatRate' => $taxSetting ? $taxSetting->rate : 5,
                'isPdf' => true, // Add this flag to hide buttons
            ];
            
            Log::info('Generating PDF view');

            // Generate PDF
            $pdf = Pdf::loadView('templates.invoice-preview', $data)
                ->setPaper('a4', 'portrait')
                ->setOption('isHtml5ParserEnabled', true)
                ->setOption('isRemoteEnabled', true);
            
            // Download with quote number as filename
            $filename = ($quote->quote_number ?? 'quote') . '.pdf';
            
            Log::info('PDF generated, initiating download', ['filename' => $filename]);

            return $pdf->download($filename);
        } catch (\Exception $e) {
            Log::error('Error generating Quote PDF', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}
