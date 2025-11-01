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
        // Load relationships with nested data
        $warrantyClaim->load([
            'invoice', 
            'customer', 
            'warehouse', 
            'items.productVariant.product.brand',
            'items.productVariant.product.model',
            'histories.user'
        ]);
        
        // Get settings
        $companyBranding = CompanyBranding::getActive();
        $currency = CurrencySetting::getBase();
        
        // Prepare data for the view
        $data = [
            'claim' => $warrantyClaim,
            'documentType' => 'warranty_claim',
            'companyName' => $companyBranding->company_name ?? 'TunerStop LLC',
            'companyAddress' => $companyBranding->company_address ?? '',
            'companyPhone' => $companyBranding->company_phone ?? '',
            'companyEmail' => $companyBranding->company_email ?? '',
            'taxNumber' => $companyBranding->tax_registration_number ?? '',
            'logo' => $companyBranding ? $companyBranding->logo_url : null,
            'currency' => $currency?->currency_symbol ?? 'AED',
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
        // Load relationships with nested data
        $warrantyClaim->load([
            'invoice', 
            'customer', 
            'warehouse', 
            'items.productVariant.product.brand',
            'items.productVariant.product.model',
            'histories.user'
        ]);
        
        // Get settings
        $companyBranding = CompanyBranding::getActive();
        $currency = CurrencySetting::getBase();
        
        // Prepare data for the view
        $data = [
            'claim' => $warrantyClaim,
            'documentType' => 'warranty_claim',
            'companyName' => $companyBranding->company_name ?? 'TunerStop LLC',
            'companyAddress' => $companyBranding->company_address ?? '',
            'companyPhone' => $companyBranding->company_phone ?? '',
            'companyEmail' => $companyBranding->company_email ?? '',
            'taxNumber' => $companyBranding->tax_registration_number ?? '',
            'logo' => $companyBranding ? $companyBranding->logo_url : null,
            'currency' => $currency?->currency_symbol ?? 'AED',
        ];
        
        // Return the HTML view directly for preview
        return view('templates.warranty-claim-preview', $data);
    }
}
