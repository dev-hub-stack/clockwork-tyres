<?php

namespace App\Http\Controllers;

use App\Modules\Consignments\Models\Consignment;
use App\Modules\Settings\Models\CompanyBranding;
use App\Modules\Settings\Models\CurrencySetting;
use App\Modules\Settings\Models\TaxSetting;
use Barryvdh\DomPDF\Facade\Pdf;

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
        
        // Prepare data for the view
        $data = [
            'consignment' => $consignment,
            'documentType' => 'consignment',
            'companyName' => $companyBranding->company_name ?? 'TunerStop LLC',
            'companyAddress' => $companyBranding->company_address ?? '',
            'companyPhone' => $companyBranding->company_phone ?? '',
            'companyEmail' => $companyBranding->company_email ?? '',
            'taxNumber' => $companyBranding->tax_registration_number ?? '',
            'logo' => $companyBranding ? $companyBranding->logo_url : null,
            'currency' => $currency?->currency_symbol ?? 'AED',
            'vatRate' => $taxSetting ? $taxSetting->rate : 5,
        ];
        
        // Generate PDF
        $pdf = Pdf::loadView('templates.consignment-pdf', $data)
            ->setPaper('a4', 'portrait')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true);
        
        // Download with consignment number as filename
        $filename = ($consignment->consignment_number ?? 'consignment') . '.pdf';
        
        return $pdf->download($filename);
    }
}
