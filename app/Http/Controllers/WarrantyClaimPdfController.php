<?php

namespace App\Http\Controllers;

use App\Modules\Warranties\Models\WarrantyClaim;
use App\Modules\Settings\Models\CompanyBranding;
use App\Modules\Settings\Models\CurrencySetting;
use Barryvdh\DomPDF\Facade\Pdf;

class WarrantyClaimPdfController extends Controller
{
    public function download(WarrantyClaim $warrantyClaim)
    {
        // Check if activity history should be included (default: false for customer-facing PDFs)
        $includeHistory = request()->boolean('include_history', false);
        
        // Load relationships with nested data
        $warrantyClaim->load([
            'invoice', 
            'customer', 
            'warehouse', 
            'items.productVariant.product.brand',
            'items.productVariant.product.model',
        ]);
        
        // Only load history if requested
        if ($includeHistory) {
            $warrantyClaim->load('histories.user');
        }
        
        // Get settings
        $companyBranding = CompanyBranding::getActive();
        $currency = CurrencySetting::getBase();
        
        // Get logo full path for PDF (DomPDF needs absolute path)
        $logoPath = null;
        if ($companyBranding && $companyBranding->logo_path) {
            $logoPath = public_path('storage/' . $companyBranding->logo_path);
            // Check if file exists, otherwise use null
            if (!file_exists($logoPath)) {
                $logoPath = null;
            }
        }
        
        // Prepare data for the view
        $data = [
            'claim' => $warrantyClaim,
            'documentType' => 'warranty_claim',
            'companyName' => $companyBranding->company_name ?? 'TunerStop LLC',
            'companyAddress' => $companyBranding->company_address ?? '',
            'companyPhone' => $companyBranding->company_phone ?? '',
            'companyEmail' => $companyBranding->company_email ?? '',
            'taxNumber' => $companyBranding->tax_registration_number ?? '',
            'logo' => $logoPath,
            'currency' => $currency?->currency_symbol ?? 'AED',
            'includeHistory' => $includeHistory,
        ];
        
        // Generate PDF
        $pdf = Pdf::loadView('templates.warranty-claim-pdf', $data)
            ->setPaper('a4', 'portrait')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true);
        
        // Download with claim number as filename
        $filename = 'Warranty_Claim_' . ($warrantyClaim->claim_number ?? $warrantyClaim->id) . '.pdf';
        
        return $pdf->download($filename);
    }

    public function preview(WarrantyClaim $warrantyClaim)
    {
        // Preview always includes history for internal review
        $includeHistory = request()->boolean('include_history', true);
        
        // Load relationships with nested data
        $warrantyClaim->load([
            'invoice', 
            'customer', 
            'warehouse', 
            'items.productVariant.product.brand',
            'items.productVariant.product.model',
        ]);
        
        // Only load history if requested
        if ($includeHistory) {
            $warrantyClaim->load('histories.user');
        }
        
        // Get settings
        $companyBranding = CompanyBranding::getActive();
        $currency = CurrencySetting::getBase();
        
        // For preview, use relative URL (works in browser)
        $logoUrl = $companyBranding ? $companyBranding->logo_url : null;
        
        // Prepare data for the view
        $data = [
            'claim' => $warrantyClaim,
            'documentType' => 'warranty_claim',
            'companyName' => $companyBranding->company_name ?? 'TunerStop LLC',
            'companyAddress' => $companyBranding->company_address ?? '',
            'companyPhone' => $companyBranding->company_phone ?? '',
            'companyEmail' => $companyBranding->company_email ?? '',
            'taxNumber' => $companyBranding->tax_registration_number ?? '',
            'logo' => $logoUrl,
            'currency' => $currency?->currency_symbol ?? 'AED',
            'includeHistory' => $includeHistory,
        ];
        
        // Return the HTML view directly for preview
        return view('templates.warranty-claim-preview', $data);
    }
}
