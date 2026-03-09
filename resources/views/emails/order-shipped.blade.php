<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Your Order {{ $record->order_number }} Has Shipped</title>
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
                    <h1 style="color:#1a1a2e;font-size:24px;font-weight:700;letter-spacing:2px;margin:0;">ORDER SHIPPED</h1>
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
        <div style="background:#dcfce7;border-bottom:1px solid #86efac;padding:14px 35px;">
            <p style="margin:0;font-size:14px;color:#166534;font-weight:600;">
                &#128666;&nbsp; Your order is on its way!
            </p>
        </div>

        <!-- Greeting -->
        <div style="padding:22px 35px 15px;border-bottom:1px solid #f0f0f0;">
            <p style="font-size:14px;color:#444;">Dear <strong>{{ $record->customer?->business_name ?? $record->customer?->name ?? 'Valued Customer' }}</strong>,</p>
            <p style="font-size:13px;color:#666;margin-top:8px;">
                Your order <strong>#{{ $record->order_number }}</strong> has been shipped and is on its way to you.
                @if($record->tracking_number) Use the tracking information below to follow your shipment.@endif
            </p>
        </div>

        @if($record->tracking_number)
        <!-- Tracking Box -->
        <div style="padding:20px 35px;">
            <div style="background:#f0fdf4;border:2px solid #86efac;border-radius:8px;padding:20px 25px;">
                <div style="font-size:11px;color:#166534;text-transform:uppercase;letter-spacing:1px;font-weight:600;margin-bottom:12px;">Tracking Information</div>
                <table style="width:100%;border-collapse:collapse;">
                    <tr>
                        <td style="padding:5px 0;vertical-align:top;width:40%;">
                            <div style="font-size:11px;color:#666;text-transform:uppercase;letter-spacing:0.5px;">Carrier</div>
                            <div style="font-size:14px;font-weight:700;color:#1a1a2e;margin-top:3px;">{{ $record->shipping_carrier ?? 'N/A' }}</div>
                        </td>
                        <td style="padding:5px 0;vertical-align:top;width:60%;">
                            <div style="font-size:11px;color:#666;text-transform:uppercase;letter-spacing:0.5px;">Tracking Number</div>
                            <div style="font-size:14px;font-weight:700;color:#1a1a2e;margin-top:3px;">{{ $record->tracking_number }}</div>
                        </td>
                    </tr>
                    @if($record->shipped_at)
                    <tr>
                        <td colspan="2" style="padding-top:10px;">
                            <div style="font-size:11px;color:#666;text-transform:uppercase;letter-spacing:0.5px;">Shipped On</div>
                            <div style="font-size:13px;font-weight:600;color:#222;margin-top:3px;">{{ \Carbon\Carbon::parse($record->shipped_at)->format('M d, Y \a\t g:i A') }}</div>
                        </td>
                    </tr>
                    @endif
                    @if($record->tracking_url)
                    <tr>
                        <td colspan="2" style="padding-top:14px;text-align:center;">
                            <a href="{{ $record->tracking_url }}" style="display:inline-block;padding:10px 28px;background:#1a1a2e;color:#fff;font-size:13px;font-weight:600;border-radius:5px;text-decoration:none;letter-spacing:0.5px;">Track My Order</a>
                        </td>
                    </tr>
                    @endif
                </table>
            </div>
        </div>
        @endif

        <!-- Order Summary -->
        <div style="padding:15px 35px;background:#f9fafb;border-top:1px solid #eee;border-bottom:1px solid #eee;">
            <table style="width:100%;border-collapse:collapse;">
                <tr>
                    <td style="width:25%;padding:6px 0;vertical-align:top;">
                        <div style="font-size:10px;color:#888;text-transform:uppercase;letter-spacing:0.5px;">Order #</div>
                        <div style="font-size:13px;font-weight:600;color:#222;margin-top:3px;">{{ $record->order_number }}</div>
                    </td>
                    <td style="width:25%;padding:6px 0;vertical-align:top;">
                        <div style="font-size:10px;color:#888;text-transform:uppercase;letter-spacing:0.5px;">Items</div>
                        <div style="font-size:13px;font-weight:600;color:#222;margin-top:3px;">{{ $record->items->count() }}</div>
                    </td>
                    <td style="width:25%;padding:6px 0;vertical-align:top;">
                        <div style="font-size:10px;color:#888;text-transform:uppercase;letter-spacing:0.5px;">Order Total</div>
                        <div style="font-size:13px;font-weight:700;color:#1a1a2e;margin-top:3px;">{{ $currSymbol }} {{ number_format($record->total, 2) }}</div>
                    </td>
                    <td style="width:25%;padding:6px 0;vertical-align:top;text-align:right;">
                        <div style="font-size:10px;color:#888;text-transform:uppercase;letter-spacing:0.5px;">Status</div>
                        <div style="margin-top:3px;"><span style="display:inline-block;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:600;background:#dcfce7;color:#166534;">SHIPPED</span></div>
                    </td>
                </tr>
            </table>
        </div>

        <!-- CTA -->
        <div style="padding:20px 35px;background:#eef6ff;border-top:1px solid #dbeafe;text-align:center;">
            <p style="font-size:13px;color:#555;">Questions about your delivery? Reply to this email and we'll assist you right away.</p>
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
