<?php

namespace App\Http\Controllers;

use App\Modules\Consignments\Models\Consignment;
use App\Modules\Settings\Models\CompanyBranding;
use App\Modules\Settings\Models\CurrencySetting;
use App\Modules\Settings\Models\TaxSetting;
use Barryvdh\DomPDF\Facade\PDF;

class ConsignmentPdfController extends Controller
{
    public function download(Consignment $consignment)
    {
        // Load relationships
        $consignment->load(['customer', 'warehouse', 'representative', 'items']);
        
        // Get settings
        $companyBranding = CompanyBranding::getActive();
        $taxSetting = TaxSetting::getDefault();
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
            'consignment' => $consignment,
            'documentType' => 'consignment',
            'companyName' => $companyBranding->company_name ?? 'TunerStop LLC',
            'companyAddress' => $companyBranding->company_address ?? '',
            'companyPhone' => $companyBranding->company_phone ?? '',
            'companyEmail' => $companyBranding->company_email ?? '',
            'taxNumber' => $companyBranding->tax_registration_number ?? '',
            'logo' => $logoPath,
            'currency' => $currency?->currency_symbol ?? 'AED',
            'vatRate' => $taxSetting ? $taxSetting->rate : 5,
        ];
        
        // Generate PDF
        $pdf = PDF::loadView('templates.consignment-pdf', $data)
            ->setPaper('a4', 'portrait')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true);
        
        // Download with consignment number as filename
        $filename = ($consignment->consignment_number ?? 'consignment') . '.pdf';
        
        return $pdf->download($filename);
    }

    public function preview(Consignment $consignment)
    {
        // Load relationships
        $consignment->load(['customer', 'warehouse', 'representative', 'items']);
        
        // Get settings
        $companyBranding = CompanyBranding::getActive();
        $taxSetting = TaxSetting::getDefault();
        $currency = CurrencySetting::getBase();
        
        // For preview, use relative URL (works in browser)
        $logoUrl = $companyBranding ? $companyBranding->logo_url : null;
        
        // Prepare data for the view
        $data = [
            'consignment' => $consignment,
            'documentType' => 'consignment',
            'companyName' => $companyBranding->company_name ?? 'TunerStop LLC',
            'companyAddress' => $companyBranding->company_address ?? '',
            'companyPhone' => $companyBranding->company_phone ?? '',
            'companyEmail' => $companyBranding->company_email ?? '',
            'taxNumber' => $companyBranding->tax_registration_number ?? '',
            'logo' => $logoUrl,
            'currency' => $currency?->currency_symbol ?? 'AED',
            'vatRate' => $taxSetting ? $taxSetting->rate : 5,
        ];
        
        // Return the HTML view directly for preview
        return view('templates.consignment-preview', $data);
    }
}
