<?php

namespace App\Http\Controllers;

use App\Modules\Orders\Models\Order;
use App\Modules\Settings\Models\CompanyBranding;
use App\Modules\Settings\Models\TaxSetting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class QuotePdfController extends Controller
{
    public function download(Order $quote)
    {
        // Get settings
        $companyBranding = CompanyBranding::getActive();
        $taxSetting = TaxSetting::getDefault();
        
        // Prepare data for the view
        $data = [
            'record' => $quote,
            'documentType' => 'quote',
            'companyName' => $companyBranding->company_name ?? 'TunerStop LLC',
            'companyAddress' => $companyBranding->company_address ?? '',
            'companyPhone' => $companyBranding->company_phone ?? '',
            'companyEmail' => $companyBranding->company_email ?? '',
            'taxNumber' => $companyBranding->tax_registration_number ?? '',
            'logo' => $companyBranding ? $companyBranding->logo_url : null,
            'currency' => 'AED',
            'vatRate' => $taxSetting ? $taxSetting->rate : 5,
        ];
        
        // Generate PDF
        $pdf = Pdf::loadView('templates.invoice-preview', $data)
            ->setPaper('a4', 'portrait')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true);
        
        // Download with quote number as filename
        $filename = ($quote->quote_number ?? 'quote') . '.pdf';
        
        return $pdf->download($filename);
    }
}
