<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Payment Receipt - {{ $record->order_number }}</title>
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
        $methodLabels = [
            'cash'          => 'Cash',
            'card'          => 'Credit/Debit Card',
            'bank_transfer' => 'Bank Transfer',
            'cheque'        => 'Cheque',
            'online'        => 'Online Payment',
        ];
        $methodLabel = $methodLabels[$payment->payment_method] ?? ucfirst($payment->payment_method ?? 'N/A');
    @endphp

    <div style="max-width:700px;margin:30px auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,0.08);">

        <!-- Header -->
        <table width="100%" cellpadding="0" cellspacing="0" style="background:#f8f9fa;border-bottom:3px solid #1a1a2e;padding:28px 35px;">
            <tr>
                <td width="50%" style="vertical-align:middle;">
                    <h1 style="color:#1a1a2e;font-size:24px;font-weight:700;letter-spacing:2px;margin:0;">PAYMENT RECEIPT</h1>
                    <p style="color:#666;font-size:13px;margin-top:4px;margin-bottom:0;">Invoice #{{ $record->order_number }}</p>
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
                &#9989;&nbsp; Payment received — thank you!
            </p>
        </div>

        <!-- Greeting -->
        <div style="padding:22px 35px 15px;border-bottom:1px solid #f0f0f0;">
            <p style="font-size:14px;color:#444;">Dear <strong>{{ $record->customer?->business_name ?? $record->customer?->name ?? 'Valued Customer' }}</strong>,</p>
            <p style="font-size:13px;color:#666;margin-top:8px;">
                We have received your payment for invoice <strong>#{{ $record->order_number }}</strong>. Please find your receipt details below.
            </p>
        </div>

        <!-- Payment Details Box -->
        <div style="padding:20px 35px;">
            <div style="background:#f0fdf4;border:2px solid #86efac;border-radius:8px;padding:20px 25px;">
                <div style="font-size:11px;color:#166534;text-transform:uppercase;letter-spacing:1px;font-weight:600;margin-bottom:16px;">Payment Details</div>
                <table style="width:100%;border-collapse:collapse;">
                    <tr>
                        <td style="padding:6px 0;vertical-align:top;width:50%;border-bottom:1px solid #d1fae5;">
                            <div style="font-size:10px;color:#888;text-transform:uppercase;letter-spacing:0.5px;">Amount Paid</div>
                            <div style="font-size:18px;font-weight:700;color:#166534;margin-top:3px;">{{ $currSymbol }} {{ number_format($payment->amount, 2) }}</div>
                        </td>
                        <td style="padding:6px 0;vertical-align:top;width:50%;border-bottom:1px solid #d1fae5;text-align:right;">
                            <div style="font-size:10px;color:#888;text-transform:uppercase;letter-spacing:0.5px;">Payment Date</div>
                            <div style="font-size:14px;font-weight:600;color:#222;margin-top:3px;">{{ \Carbon\Carbon::parse($payment->payment_date)->format('d/m/Y') }}</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:10px 0 6px;vertical-align:top;width:50%;">
                            <div style="font-size:10px;color:#888;text-transform:uppercase;letter-spacing:0.5px;">Payment Method</div>
                            <div style="font-size:13px;font-weight:600;color:#222;margin-top:3px;">{{ $methodLabel }}</div>
                        </td>
                        <td style="padding:10px 0 6px;vertical-align:top;width:50%;text-align:right;">
                            @if($payment->reference_number)
                            <div style="font-size:10px;color:#888;text-transform:uppercase;letter-spacing:0.5px;">Reference</div>
                            <div style="font-size:13px;font-weight:600;color:#222;margin-top:3px;">{{ $payment->reference_number }}</div>
                            @endif
                        </td>
                    </tr>
                    @if($payment->bank_name)
                    <tr>
                        <td colspan="2" style="padding:6px 0;vertical-align:top;">
                            <div style="font-size:10px;color:#888;text-transform:uppercase;letter-spacing:0.5px;">Bank</div>
                            <div style="font-size:13px;font-weight:600;color:#222;margin-top:3px;">{{ $payment->bank_name }}</div>
                        </td>
                    </tr>
                    @endif
                </table>
            </div>
        </div>

        <!-- Invoice Balance -->
        <div style="padding:15px 35px;background:#f9fafb;border-top:1px solid #eee;border-bottom:1px solid #eee;">
            <table style="width:100%;border-collapse:collapse;">
                <tr>
                    <td style="width:33%;padding:6px 0;vertical-align:top;">
                        <div style="font-size:10px;color:#888;text-transform:uppercase;letter-spacing:0.5px;">Invoice Total</div>
                        <div style="font-size:13px;font-weight:600;color:#222;margin-top:3px;">{{ $currSymbol }} {{ number_format($record->total, 2) }}</div>
                    </td>
                    <td style="width:33%;padding:6px 0;vertical-align:top;">
                        <div style="font-size:10px;color:#888;text-transform:uppercase;letter-spacing:0.5px;">Total Paid</div>
                        <div style="font-size:13px;font-weight:600;color:#166534;margin-top:3px;">{{ $currSymbol }} {{ number_format($record->paid_amount, 2) }}</div>
                    </td>
                    <td style="width:33%;padding:6px 0;vertical-align:top;text-align:right;">
                        <div style="font-size:10px;color:#888;text-transform:uppercase;letter-spacing:0.5px;">Balance Due</div>
                        @php $outstanding = $record->outstanding_amount; @endphp
                        <div style="font-size:13px;font-weight:700;color:{{ $outstanding > 0 ? '#991b1b' : '#166534' }};margin-top:3px;">
                            {{ $currSymbol }} {{ number_format($outstanding, 2) }}
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        @if($outstanding <= 0)
        <!-- Fully Paid Banner -->
        <div style="padding:14px 35px;background:#dcfce7;border-bottom:1px solid #86efac;text-align:center;">
            <p style="margin:0;font-size:13px;color:#166534;font-weight:600;">&#127881; Invoice fully paid — no outstanding balance.</p>
        </div>
        @endif

        <!-- Footer -->
        <div style="padding:20px 35px;background:#f8f9fa;text-align:center;">
            <p style="margin:0 0 6px;font-size:12px;color:#888;">{{ $companyName }}</p>
            @if($companyEmail)<p style="margin:2px 0;font-size:11px;color:#aaa;">{{ $companyEmail }}</p>@endif
            @if($companyPhone)<p style="margin:2px 0;font-size:11px;color:#aaa;">{{ $companyPhone }}</p>@endif
        </div>
    </div>
</body>
</html>
