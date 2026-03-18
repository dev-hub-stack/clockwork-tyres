<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Order {{ $record->order_number }} Delivered</title>
</head>
<body style="margin:0;padding:0;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#333;background-color:#f4f4f4;line-height:1.5;">
    @php
        $companyBranding = \App\Modules\Settings\Models\CompanyBranding::getActive();
        $currency        = \App\Modules\Settings\Models\CurrencySetting::getBase();
        $currSymbol      = $currency ? $currency->currency_symbol : 'AED';
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
                    <h1 style="color:#1a1a2e;font-size:24px;font-weight:700;letter-spacing:2px;margin:0;">ORDER DELIVERED</h1>
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
        <div style="background:#d1fae5;border-bottom:1px solid #6ee7b7;padding:14px 35px;">
            <p style="margin:0;font-size:14px;color:#065f46;font-weight:600;">
                &#10003;&nbsp; Your order has been delivered. Thank you for choosing {{ $companyName }}!
            </p>
        </div>

        <!-- Greeting -->
        <div style="padding:22px 35px 15px;border-bottom:1px solid #f0f0f0;">
            <p style="font-size:14px;color:#444;">Dear <strong>{{ $record->customer?->business_name ?? $record->customer?->name ?? 'Valued Customer' }}</strong>,</p>
            <p style="font-size:13px;color:#666;margin-top:8px;">
                We're happy to confirm that your order <strong>#{{ $record->order_number }}</strong> has been delivered.
                We hope you're completely satisfied with your purchase.
            </p>
        </div>

        <!-- Order Summary Box -->
        <div style="padding:25px 35px;">
            <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:20px 25px;">
                <div style="font-size:11px;color:#888;text-transform:uppercase;letter-spacing:1px;font-weight:600;margin-bottom:14px;">Order Summary</div>
                <table style="width:100%;border-collapse:collapse;">
                    <tr>
                        <td style="padding:6px 0;font-size:13px;color:#666;width:50%;">
                            <strong style="color:#222;">Order #:</strong> {{ $record->order_number }}
                        </td>
                        <td style="padding:6px 0;font-size:13px;color:#666;width:50%;text-align:right;">
                            <strong style="color:#222;">Date:</strong> {{ $record->issue_date ? \Carbon\Carbon::parse($record->issue_date)->format('M d, Y') : date('M d, Y') }}
                        </td>
                    </tr>
                    @if($record->vehicle_year || $record->vehicle_make || $record->vehicle_model || $record->vehicle_sub_model)
                    <tr>
                        <td colspan="2" style="padding:6px 0;font-size:13px;color:#666;">
                            <strong style="color:#222;">Vehicle:</strong> {{ implode(' ', array_filter([$record->vehicle_year, $record->vehicle_make, $record->vehicle_model, $record->vehicle_sub_model])) }}
                        </td>
                    </tr>
                    @endif
                    <tr>
                        <td style="padding:10px 0 5px;font-size:11px;color:#888;text-transform:uppercase;letter-spacing:0.5px;" colspan="2">
                            Items: {{ $record->items->count() }}
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2" style="border-top:1px solid #e5e7eb;"></td>
                    </tr>
                    <tr>
                        <td style="padding:10px 0 0;font-size:16px;font-weight:700;color:#1a1a2e;">Order Total</td>
                        <td style="padding:10px 0 0;font-size:16px;font-weight:700;color:#1a1a2e;text-align:right;">{{ $currSymbol }} {{ number_format($record->total, 2) }}</td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Thank You CTA -->
        <div style="padding:20px 35px 25px;text-align:center;">
            <p style="font-size:15px;color:#1a1a2e;font-weight:700;margin-bottom:8px;">Thank you for your order! &#127881;</p>
            <p style="font-size:13px;color:#666;">We appreciate your business and look forward to serving you again.</p>
        </div>

        <!-- Footer -->
        <div style="padding:20px 35px;text-align:center;border-top:1px solid #f0f0f0;background:#f9fafb;">
            <p style="font-size:12px;color:#888;margin-bottom:4px;"><strong style="color:#555;">{{ $companyName }}</strong></p>
            @if($companyEmail)<p style="font-size:11px;color:#aaa;">{{ $companyEmail }}@if($companyPhone) &nbsp;|&nbsp; {{ $companyPhone }}@endif</p>@endif
            @if($companyAddress)<p style="font-size:11px;color:#aaa;margin-top:3px;">{{ $companyAddress }}</p>@endif
        </div>
    </div>
</body>
</html>
