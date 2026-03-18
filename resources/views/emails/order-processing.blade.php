<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Order {{ $record->order_number }} - Processing</title>
</head>
<body style="margin:0;padding:0;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#333;background-color:#f4f4f4;line-height:1.5;">
    @php
        $companyBranding = \App\Modules\Settings\Models\CompanyBranding::getActive();
        $taxSetting      = \App\Modules\Settings\Models\TaxSetting::getDefault();
        $currency        = \App\Modules\Settings\Models\CurrencySetting::getBase();
        $currSymbol      = $currency ? $currency->currency_symbol : 'AED';
        $vatRate         = $taxSetting ? $taxSetting->rate : 5;
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

    <div style="max-width:700px;margin:30px auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,0.08);">

        <!-- Header -->
        <table width="100%" cellpadding="0" cellspacing="0" style="background:#f8f9fa;border-bottom:3px solid #1a1a2e;padding:28px 35px;">
            <tr>
                <td width="50%" style="vertical-align:middle;">
                    <h1 style="color:#1a1a2e;font-size:24px;font-weight:700;letter-spacing:2px;margin:0;">ORDER UPDATE</h1>
                    <p style="color:#666;font-size:13px;margin-top:4px;margin-bottom:0;">{{ $record->order_number }}</p>
                </td>
                <td width="50%" align="right" style="vertical-align:middle;text-align:right;">
                    @if($logoUrl)
                        <img src="{{ $logoUrl }}" alt="{{ $companyName }}" style="display:block;margin-left:auto;max-height:50px;max-width:160px;margin-bottom:6px;">
                    @endif
                    <p style="color:#1a1a2e;font-weight:700;font-size:13px;margin:0;">{{ $companyName }}</p>
                    @if($taxNumber)<p style="color:#666;font-size:11px;margin-top:2px;margin-bottom:0;">Tax No: {{ $taxNumber }}</p>@endif
                </td>
            </tr>
        </table>

        <!-- Status Banner -->
        <div style="background:#fef9c3;border-bottom:1px solid #fde047;padding:14px 35px;">
            <p style="margin:0;font-size:14px;color:#854d0e;font-weight:600;">
                &#9881;&nbsp; Your order is now being processed by our team.
            </p>
        </div>

        <!-- Greeting -->
        <div style="padding:22px 35px 15px;border-bottom:1px solid #f0f0f0;">
            <p style="font-size:14px;color:#444;">Dear <strong>{{ $record->customer?->business_name ?? $record->customer?->name ?? 'Valued Customer' }}</strong>,</p>
            <p style="font-size:13px;color:#666;margin-top:8px;">
                We've received your order <strong>#{{ $record->order_number }}</strong> and our team is now processing it.
                You'll receive another update once your order has been shipped.
            </p>
        </div>

        <!-- Order Summary -->
        <div style="padding:20px 35px;background:#f9fafb;border-bottom:1px solid #eee;">
            <table style="width:100%;border-collapse:collapse;">
                <tr>
                    <td style="width:25%;padding:6px 0;vertical-align:top;">
                        <div style="font-size:10px;color:#888;text-transform:uppercase;letter-spacing:0.5px;">Order #</div>
                        <div style="font-size:13px;font-weight:600;color:#222;margin-top:3px;">{{ $record->order_number }}</div>
                    </td>
                    <td style="width:25%;padding:6px 0;vertical-align:top;">
                        <div style="font-size:10px;color:#888;text-transform:uppercase;letter-spacing:0.5px;">Order Date</div>
                        <div style="font-size:13px;font-weight:600;color:#222;margin-top:3px;">{{ $record->issue_date ? \Carbon\Carbon::parse($record->issue_date)->format('M d, Y') : date('M d, Y') }}</div>
                    </td>
                    <td style="width:25%;padding:6px 0;vertical-align:top;">
                        <div style="font-size:10px;color:#888;text-transform:uppercase;letter-spacing:0.5px;">Items</div>
                        <div style="font-size:13px;font-weight:600;color:#222;margin-top:3px;">{{ $record->items->count() }} item(s)</div>
                    </td>
                    <td style="width:25%;padding:6px 0;vertical-align:top;text-align:right;">
                        <div style="font-size:10px;color:#888;text-transform:uppercase;letter-spacing:0.5px;">Status</div>
                        <div style="margin-top:3px;"><span style="display:inline-block;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:600;background:#fef9c3;color:#854d0e;">PROCESSING</span></div>
                    </td>
                </tr>
            </table>
            @if($record->customer)
            <table style="width:100%;border-collapse:collapse;margin-top:15px;padding-top:15px;border-top:1px solid #e8e8e8;">
                <tr>
                    <td style="vertical-align:top;padding-right:20px;">
                        <div style="font-size:10px;color:#888;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">Customer</div>
                        <div style="font-size:13px;font-weight:600;color:#222;">{{ $record->customer->business_name ?? $record->customer->name }}</div>
                        @if($record->customer->email)<div style="font-size:12px;color:#666;">{{ $record->customer->email }}</div>@endif
                    </td>
                    @if($record->vehicle_year || $record->vehicle_make || $record->vehicle_model || $record->vehicle_sub_model)
                    <td style="vertical-align:top;">
                        <div style="font-size:10px;color:#888;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">Vehicle</div>
                        <div style="font-size:13px;font-weight:600;color:#222;">{{ implode(' ', array_filter([$record->vehicle_year, $record->vehicle_make, $record->vehicle_model, $record->vehicle_sub_model])) }}</div>
                    </td>
                    @endif
                </tr>
            </table>
            @endif
        </div>

        <!-- Order Total -->
        <div style="padding:20px 35px 25px;">
            <table style="width:100%;border-collapse:collapse;">
                <tr>
                    <td style="width:55%;"></td>
                    <td style="width:45%;">
                        <table style="width:100%;border-collapse:collapse;">
                            @php
                                $isZeroRated = !empty($record->is_zero_rated);
                                $subTotal = $isZeroRated
                                    ? round(floatval($record->total) + floatval($record->discount ?? 0) - floatval($record->shipping ?? 0), 2)
                                    : floatval($record->sub_total);
                                $vatAmount = 0.0;
                                if (!$isZeroRated) {
                                    $vatAmount = floatval($record->tax ?? $record->vat ?? 0);
                                    if ($vatAmount == 0 && floatval($record->total ?? 0) > 0 && $subTotal > 0)
                                        $vatAmount = round(floatval($record->total) - $subTotal - floatval($record->shipping ?? 0), 2);
                                    if ($vatAmount == 0 && $subTotal > 0)
                                        $vatAmount = round($subTotal * (floatval($vatRate) / 100), 2);
                                }
                            @endphp
                            <tr><td style="padding:5px 0;font-size:13px;color:#666;">Subtotal:</td><td style="padding:5px 0;font-size:13px;text-align:right;">{{ $currSymbol }} {{ number_format($subTotal, 2) }}</td></tr>
                            @if($record->discount > 0)<tr><td style="padding:5px 0;font-size:13px;color:#666;">Discount:</td><td style="padding:5px 0;font-size:13px;text-align:right;color:#e53e3e;">-{{ $currSymbol }} {{ number_format($record->discount, 2) }}</td></tr>@endif
                            @if($record->shipping > 0)<tr><td style="padding:5px 0;font-size:13px;color:#666;">Shipping:</td><td style="padding:5px 0;font-size:13px;text-align:right;">{{ $currSymbol }} {{ number_format($record->shipping, 2) }}</td></tr>@endif
                            <tr><td style="padding:5px 0;font-size:13px;color:#666;">@if($isZeroRated)VAT (0%):@else VAT ({{ $vatRate }}%):@endif</td><td style="padding:5px 0;font-size:13px;text-align:right;">{{ $currSymbol }} {{ number_format($vatAmount, 2) }}</td></tr>
                            <tr><td colspan="2" style="border-top:2px solid #1a1a2e;padding-top:6px;"></td></tr>
                            <tr><td style="padding:5px 0 0;font-size:15px;font-weight:700;color:#1a1a2e;">Total:</td><td style="padding:5px 0 0;font-size:15px;font-weight:700;color:#1a1a2e;text-align:right;">{{ $currSymbol }} {{ number_format($record->total, 2) }}</td></tr>
                        </table>
                    </td>
                </tr>
            </table>
        </div>

        <!-- CTA -->
        <div style="padding:20px 35px;background:#eef6ff;border-top:1px solid #dbeafe;text-align:center;">
            <p style="font-size:13px;color:#555;">Have questions? Reply to this email or contact us directly.</p>
        </div>

        <!-- Footer -->
        <div style="padding:20px 35px;text-align:center;border-top:1px solid #f0f0f0;">
            <p style="font-size:12px;color:#888;margin-bottom:4px;"><strong style="color:#555;">{{ $companyName }}</strong></p>
            @if($companyEmail)<p style="font-size:11px;color:#aaa;">{{ $companyEmail }}@if($companyPhone) &nbsp;|&nbsp; {{ $companyPhone }}@endif</p>@endif
            @if($companyAddress)<p style="font-size:11px;color:#aaa;margin-top:3px;">{{ $companyAddress }}</p>@endif
        </div>
    </div>
</body>
</html>
