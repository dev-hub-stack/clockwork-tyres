<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Consignment {{ $record->consignment_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 13px; color: #333; background-color: #f4f4f4; line-height: 1.5; }
        .email-wrapper { max-width: 700px; margin: 30px auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
    </style>
</head>
<body>
    @php
        $companyBranding = \App\Modules\Settings\Models\CompanyBranding::getActive();
        $currency        = \App\Modules\Settings\Models\CurrencySetting::getBase();
        $taxSetting      = \App\Modules\Settings\Models\TaxSetting::getDefault();
        $currSymbol      = $currency?->currency_symbol ?? 'AED';
        $vatRate         = $taxSetting?->rate ?? 5;
        $companyName     = $companyBranding?->company_name ?? 'TunerStop LLC';
        $companyEmail    = $companyBranding?->company_email ?? '';
        $companyPhone    = $companyBranding?->company_phone ?? '';
        $companyAddress  = $companyBranding?->company_address ?? '';
        $taxNumber       = $companyBranding?->tax_registration_number ?? '';
        $logoUrl = $emailLogoUrl ?? null;
        if (!$logoUrl && $companyBranding?->logo_path) {
            $cdnBase = rtrim(config('filesystems.disks.s3.url', ''), '/');
            $logoUrl = $cdnBase
                ? $cdnBase . '/' . ltrim($companyBranding->logo_path, '/')
                : \Illuminate\Support\Facades\Storage::disk('public')->url($companyBranding->logo_path);
        }
    @endphp

    <div class="email-wrapper">
        <!-- Header -->
        <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f8f9fa; border-bottom: 3px solid #1a1a2e; padding: 28px 35px;">
            <tr>
                <td width="50%" style="vertical-align: middle;">
                    <h1 style="color: #1a1a2e; font-size: 26px; font-weight: 700; letter-spacing: 2px; margin: 0;">CONSIGNMENT</h1>
                    <p style="color: #666; font-size: 13px; margin-top: 4px; margin-bottom: 0;">{{ $record->consignment_number }}</p>
                </td>
                <td width="50%" align="right" style="vertical-align: middle; text-align: right;">
                    @if($logoUrl)
                        <img src="{{ $logoUrl }}" alt="{{ $companyName }}" style="display: block; margin-left: auto; max-height: 50px; max-width: 160px; margin-bottom: 6px;">
                    @endif
                    <p style="color: #1a1a2e; font-weight: 700; font-size: 13px; margin: 0;">{{ $companyName }}</p>
                    @if($taxNumber)
                        <p style="color: #666; font-size: 11px; margin-top: 2px; margin-bottom: 0;">Tax No: {{ $taxNumber }}</p>
                    @endif
                </td>
            </tr>
        </table>

        <!-- Greeting -->
        <div style="padding: 22px 35px 15px; border-bottom: 1px solid #f0f0f0;">
            <p style="font-size: 14px; color: #444;">Dear <strong>{{ $record->customer?->business_name ?? $record->customer?->name ?? 'Valued Customer' }}</strong>,</p>
            <p style="font-size: 13px; color: #666; margin-top: 8px;">We have dispatched the following consignment for your reference. Please review the details below.</p>
        </div>

        <!-- Meta -->
        <div style="padding: 20px 35px; background-color: #f9fafb; border-bottom: 1px solid #eee;">
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="width: 25%; padding: 6px 0; vertical-align: top;">
                        <div style="font-size: 10px; color: #888; text-transform: uppercase; letter-spacing: 0.5px;">Consignment #</div>
                        <div style="font-size: 13px; font-weight: 600; color: #222; margin-top: 3px;">{{ $record->consignment_number }}</div>
                    </td>
                    <td style="width: 25%; padding: 6px 0; vertical-align: top;">
                        <div style="font-size: 10px; color: #888; text-transform: uppercase; letter-spacing: 0.5px;">Issue Date</div>
                        <div style="font-size: 13px; font-weight: 600; color: #222; margin-top: 3px;">{{ $record->issue_date ? \Carbon\Carbon::parse($record->issue_date)->format('M d, Y') : date('M d, Y') }}</div>
                    </td>
                    <td style="width: 25%; padding: 6px 0; vertical-align: top;">
                        <div style="font-size: 10px; color: #888; text-transform: uppercase; letter-spacing: 0.5px;">Expected Return</div>
                        <div style="font-size: 13px; font-weight: 600; color: #222; margin-top: 3px;">{{ $record->expected_return_date ? \Carbon\Carbon::parse($record->expected_return_date)->format('M d, Y') : 'N/A' }}</div>
                    </td>
                    <td style="width: 25%; padding: 6px 0; vertical-align: top; text-align: right;">
                        <div style="font-size: 10px; color: #888; text-transform: uppercase; letter-spacing: 0.5px;">Status</div>
                        <div style="margin-top: 3px;">
                            <span style="display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; background-color: #e8f4fd; color: #2b6cb0;">
                                {{ $record->status?->label() ?? strtoupper($record->status ?? 'SENT') }}
                            </span>
                        </div>
                    </td>
                </tr>
            </table>
            @if($record->customer)
            <table style="width: 100%; border-collapse: collapse; margin-top: 15px; padding-top: 15px; border-top: 1px solid #e8e8e8;">
                <tr>
                    <td style="vertical-align: top;">
                        <div style="font-size: 10px; color: #888; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Customer</div>
                        <div style="font-size: 13px; font-weight: 600; color: #222;">{{ $record->customer->business_name ?? $record->customer->name }}</div>
                        @if($record->customer->email)<div style="font-size: 12px; color: #666;">{{ $record->customer->email }}</div>@endif
                        @if($record->customer->phone)<div style="font-size: 12px; color: #666;">{{ $record->customer->phone }}</div>@endif
                    </td>
                    @if($record->tracking_number)
                    <td style="vertical-align: top; text-align: right;">
                        <div style="font-size: 10px; color: #888; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Tracking #</div>
                        <div style="font-size: 13px; font-weight: 600; color: #222;">{{ $record->tracking_number }}</div>
                    </td>
                    @endif
                </tr>
            </table>
            @endif
        </div>

        <!-- Items -->
        <div style="padding: 25px 35px;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th style="background-color: #1a1a2e; color: #fff; font-size: 11px; text-transform: uppercase; padding: 9px 8px; text-align: left; width: 5%;">#</th>
                        <th style="background-color: #1a1a2e; color: #fff; font-size: 11px; text-transform: uppercase; padding: 9px 8px; text-align: left; width: 55%;">Product</th>
                        <th style="background-color: #1a1a2e; color: #fff; font-size: 11px; text-transform: uppercase; padding: 9px 8px; text-align: center; width: 10%;">Qty Sent</th>
                        <th style="background-color: #1a1a2e; color: #fff; font-size: 11px; text-transform: uppercase; padding: 9px 8px; text-align: right; width: 15%;">Unit Value</th>
                        <th style="background-color: #1a1a2e; color: #fff; font-size: 11px; text-transform: uppercase; padding: 9px 8px; text-align: right; width: 15%;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($record->items ?? [] as $index => $item)
                    <tr>
                        <td style="padding: 10px 8px; border-bottom: 1px solid #f0f0f0; font-size: 12px; color: #666; text-align: center;">{{ $index + 1 }}</td>
                        <td style="padding: 10px 8px; border-bottom: 1px solid #f0f0f0;">
                            <div style="font-weight: 600; font-size: 13px; color: #222;">{{ $item->product_name ?? $item->productVariant?->product?->product_full_name ?? 'Product' }}</div>
                            @if($item->sku ?? $item->productVariant?->sku)<div style="font-size: 11px; color: #888; margin-top: 2px;">SKU: {{ $item->sku ?? $item->productVariant?->sku }}</div>@endif
                        </td>
                        <td style="padding: 10px 8px; border-bottom: 1px solid #f0f0f0; text-align: center; font-size: 12px;">{{ $item->quantity_sent ?? $item->quantity ?? 0 }}</td>
                        <td style="padding: 10px 8px; border-bottom: 1px solid #f0f0f0; text-align: right; font-size: 12px;">{{ $currSymbol }} {{ number_format($item->unit_price ?? $item->agreed_price ?? 0, 2) }}</td>
                        <td style="padding: 10px 8px; border-bottom: 1px solid #f0f0f0; text-align: right; font-size: 12px; font-weight: 600;">{{ $currSymbol }} {{ number_format(($item->quantity_sent ?? $item->quantity ?? 0) * ($item->unit_price ?? $item->agreed_price ?? 0), 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Totals -->
        <div style="padding: 5px 35px 25px;">
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="width: 60%;"></td>
                    <td style="width: 40%;">
                        <table style="width: 100%; border-collapse: collapse;">
                            @php
                                $isZeroRated = !empty($record->is_zero_rated);
                                $conTotal    = floatval($record->total ?? $record->total_value ?? 0);
                                $conDiscount = floatval($record->discount ?? 0);
                                $conShipping = floatval($record->shipping_cost ?? 0);
                                $conSubtotal = $isZeroRated
                                    ? round($conTotal + $conDiscount - $conShipping, 2)
                                    : floatval($record->subtotal ?? 0);
                                $conVat = $isZeroRated ? 0 : floatval($record->tax ?? 0);
                            @endphp
                            <tr>
                                <td style="padding: 5px 0; font-size: 13px; color: #666;">Subtotal:</td>
                                <td style="padding: 5px 0; font-size: 13px; text-align: right; font-weight: 500;">{{ $currSymbol }} {{ number_format($conSubtotal, 2) }}</td>
                            </tr>
                            @if($conDiscount > 0)
                            <tr>
                                <td style="padding: 5px 0; font-size: 13px; color: #666;">Discount:</td>
                                <td style="padding: 5px 0; font-size: 13px; text-align: right; color: #e53e3e;">-{{ $currSymbol }} {{ number_format($conDiscount, 2) }}</td>
                            </tr>
                            @endif
                            <tr>
                                <td style="padding: 5px 0; font-size: 13px; color: #666;">{{ $isZeroRated ? 'VAT (0% — Zero Rated):' : 'VAT (' . $vatRate . '%):' }}</td>
                                <td style="padding: 5px 0; font-size: 13px; text-align: right; font-weight: 500;">{{ $currSymbol }} {{ number_format($conVat, 2) }}</td>
                            </tr>
                            <tr><td colspan="2" style="border-top: 2px solid #1a1a2e; padding-top: 6px;"></td></tr>
                            <tr>
                                <td style="padding: 5px 0 0; font-size: 15px; font-weight: 700; color: #1a1a2e;">Total Value:</td>
                                <td style="padding: 5px 0 0; font-size: 15px; font-weight: 700; color: #1a1a2e; text-align: right;">{{ $currSymbol }} {{ number_format($conTotal, 2) }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </div>

        @if($record->notes)
        <div style="padding: 15px 35px 20px; background-color: #f9fafb; border-top: 1px solid #eee;">
            <div style="font-size: 11px; color: #888; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">Notes</div>
            <p style="font-size: 13px; color: #555;">{{ $record->notes }}</p>
        </div>
        @endif

        <!-- Footer -->
        <div style="padding: 20px 35px; text-align: center; border-top: 1px solid #f0f0f0;">
            <p style="font-size: 12px; color: #888; margin-bottom: 4px;"><strong style="color: #555;">{{ $companyName }}</strong></p>
            @if($companyEmail)<p style="font-size: 11px; color: #aaa;">{{ $companyEmail }}@if($companyPhone) | {{ $companyPhone }}@endif</p>@endif
            @if($companyAddress)<p style="font-size: 11px; color: #aaa; margin-top: 3px;">{{ $companyAddress }}</p>@endif
        </div>
    </div>
</body>
</html>
