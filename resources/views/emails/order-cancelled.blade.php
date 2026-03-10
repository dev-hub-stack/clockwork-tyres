<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Order {{ $record->order_number }} Cancelled</title>
</head>
<body style="margin:0;padding:0;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#333;background-color:#f4f4f4;line-height:1.5;">
    @php
        $companyBranding = \App\Modules\Settings\Models\CompanyBranding::getActive();
        $currency        = \App\Modules\Settings\Models\CurrencySetting::getBase();
        $currSymbol      = $currency ? $currency->currency_symbol : 'AED';
        $companyName     = $companyBranding?->company_name ?? 'TunerStop LLC';
        $companyEmail    = $companyBranding?->company_email ?? '';
        $companyPhone    = $companyBranding?->company_phone ?? '';
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
                    <h1 style="color:#1a1a2e;font-size:24px;font-weight:700;letter-spacing:2px;margin:0;">ORDER CANCELLED</h1>
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
        <div style="background:#fee2e2;border-bottom:1px solid #fca5a5;padding:14px 35px;">
            <p style="margin:0;font-size:14px;color:#991b1b;font-weight:600;">
                &#10060;&nbsp; Your order has been cancelled.
            </p>
        </div>

        <!-- Greeting -->
        <div style="padding:22px 35px 15px;border-bottom:1px solid #f0f0f0;">
            <p style="font-size:14px;color:#444;">Dear <strong>{{ $record->customer?->business_name ?? $record->customer?->name ?? 'Valued Customer' }}</strong>,</p>
            <p style="font-size:13px;color:#666;margin-top:8px;">
                We are writing to confirm that your order <strong>#{{ $record->order_number }}</strong> has been cancelled.
                If you have made a payment and are eligible for a refund, our team will be in touch with you shortly.
            </p>
        </div>

        <!-- Order Summary -->
        <div style="padding:20px 35px;background:#f9fafb;border-bottom:1px solid #eee;">
            <div style="font-size:11px;color:#888;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:14px;font-weight:600;">Order Summary</div>
            <table style="width:100%;border-collapse:collapse;">
                <tr>
                    <td style="width:25%;padding:6px 0;vertical-align:top;">
                        <div style="font-size:10px;color:#888;text-transform:uppercase;letter-spacing:0.5px;">Order #</div>
                        <div style="font-size:13px;font-weight:600;color:#222;margin-top:3px;">{{ $record->order_number }}</div>
                    </td>
                    <td style="width:25%;padding:6px 0;vertical-align:top;">
                        <div style="font-size:10px;color:#888;text-transform:uppercase;letter-spacing:0.5px;">Order Date</div>
                        <div style="font-size:13px;font-weight:600;color:#222;margin-top:3px;">{{ $record->created_at->format('d/m/Y') }}</div>
                    </td>
                    <td style="width:25%;padding:6px 0;vertical-align:top;">
                        <div style="font-size:10px;color:#888;text-transform:uppercase;letter-spacing:0.5px;">Order Total</div>
                        <div style="font-size:13px;font-weight:700;color:#1a1a2e;margin-top:3px;">{{ $currSymbol }} {{ number_format($record->total, 2) }}</div>
                    </td>
                    <td style="width:25%;padding:6px 0;vertical-align:top;text-align:right;">
                        <div style="font-size:10px;color:#888;text-transform:uppercase;letter-spacing:0.5px;">Status</div>
                        <div style="margin-top:3px;"><span style="display:inline-block;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:600;background:#fee2e2;color:#991b1b;">CANCELLED</span></div>
                    </td>
                </tr>
            </table>
        </div>

        @if($record->cancellation_reason)
        <!-- Cancellation Reason -->
        <div style="padding:18px 35px;border-bottom:1px solid #f0f0f0;">
            <div style="font-size:11px;color:#888;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px;font-weight:600;">Cancellation Reason</div>
            <p style="font-size:13px;color:#555;margin:0;">{{ $record->cancellation_reason }}</p>
            @if($record->cancellation_notes)
                <p style="font-size:12px;color:#777;margin-top:6px;">{{ $record->cancellation_notes }}</p>
            @endif
        </div>
        @endif

        <!-- Contact -->
        <div style="padding:18px 35px;border-bottom:1px solid #f0f0f0;">
            <p style="font-size:13px;color:#555;margin:0;">
                If you have any questions about this cancellation, please don't hesitate to contact us.
            </p>
        </div>

        <!-- Footer -->
        <div style="padding:20px 35px;background:#f8f9fa;text-align:center;">
            <p style="margin:0 0 6px;font-size:12px;color:#888;">{{ $companyName }}</p>
            @if($companyEmail)<p style="margin:2px 0;font-size:11px;color:#aaa;">{{ $companyEmail }}</p>@endif
            @if($companyPhone)<p style="margin:2px 0;font-size:11px;color:#aaa;">{{ $companyPhone }}</p>@endif
        </div>
    </div>
</body>
</html>
