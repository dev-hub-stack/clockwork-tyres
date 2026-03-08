<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Warranty Claim {{ $record->claim_number ?? '' }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 13px; color: #333; background-color: #f4f4f4; line-height: 1.5; }
        .email-wrapper { max-width: 700px; margin: 30px auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
    </style>
</head>
<body>
    @php
        $companyBranding = \App\Modules\Settings\Models\CompanyBranding::getActive();
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

        $claimDate  = $record->claim_date  ?? $record->issue_date ?? null;
        $issueDate  = $record->issue_date  ?? null;
        $statusLabel = is_object($record->status)
            ? ($record->status->label() ?? ucfirst($record->status->value ?? ''))
            : ucfirst(strtolower($record->status ?? 'submitted'));
    @endphp

    <div class="email-wrapper">
        <!-- Header -->
        <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #1a1a2e; padding: 28px 35px;">
            <tr>
                <td width="50%" style="vertical-align: middle;">
                    <h1 style="color: #ffffff; font-size: 26px; font-weight: 700; letter-spacing: 2px; margin: 0;">WARRANTY CLAIM</h1>
                    <p style="color: #a0aec0; font-size: 13px; margin-top: 4px; margin-bottom: 0;">{{ $record->claim_number ?? '' }}</p>
                </td>
                <td width="50%" align="right" style="vertical-align: middle; text-align: right;">
                    @if($logoUrl)
                        <img src="{{ $logoUrl }}" alt="{{ $companyName }}" style="display: block; margin-left: auto; max-height: 50px; max-width: 140px; margin-bottom: 4px;">
                    @endif
                    <p style="color: #ffffff; font-weight: 600; font-size: 13px; margin: 0;">{{ $companyName }}</p>
                    @if($taxNumber)
                        <p style="color: #a0aec0; font-size: 11px; margin-top: 2px; margin-bottom: 0;">Tax No: {{ $taxNumber }}</p>
                    @endif
                </td>
            </tr>
        </table>

        <!-- Greeting -->
        <div style="padding: 22px 35px 15px; border-bottom: 1px solid #f0f0f0;">
            <p style="font-size: 14px; color: #444;">Dear <strong>{{ $record->customer?->business_name ?? $record->customer?->name ?? 'Valued Customer' }}</strong>,</p>
            <p style="font-size: 13px; color: #666; margin-top: 8px;">We have received your warranty claim. Please review the details below. Our team will be in touch shortly.</p>
        </div>

        <!-- Meta -->
        <div style="padding: 20px 35px; background-color: #f9fafb; border-bottom: 1px solid #eee;">
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="width: 25%; padding: 6px 0; vertical-align: top;">
                        <div style="font-size: 10px; color: #888; text-transform: uppercase; letter-spacing: 0.5px;">Claim #</div>
                        <div style="font-size: 13px; font-weight: 600; color: #222; margin-top: 3px;">{{ $record->claim_number ?? 'N/A' }}</div>
                    </td>
                    <td style="width: 25%; padding: 6px 0; vertical-align: top;">
                        <div style="font-size: 10px; color: #888; text-transform: uppercase; letter-spacing: 0.5px;">Claim Date</div>
                        <div style="font-size: 13px; font-weight: 600; color: #222; margin-top: 3px;">{{ $claimDate ? \Carbon\Carbon::parse($claimDate)->format('M d, Y') : date('M d, Y') }}</div>
                    </td>
                    @if($issueDate && $issueDate != $claimDate)
                    <td style="width: 25%; padding: 6px 0; vertical-align: top;">
                        <div style="font-size: 10px; color: #888; text-transform: uppercase; letter-spacing: 0.5px;">Issue Date</div>
                        <div style="font-size: 13px; font-weight: 600; color: #222; margin-top: 3px;">{{ \Carbon\Carbon::parse($issueDate)->format('M d, Y') }}</div>
                    </td>
                    <td style="width: 25%; padding: 6px 0; vertical-align: top; text-align: right;">
                    @else
                    <td style="width: 25%; padding: 6px 0; vertical-align: top;">
                    </td>
                    <td style="width: 25%; padding: 6px 0; vertical-align: top; text-align: right;">
                    @endif
                        <div style="font-size: 10px; color: #888; text-transform: uppercase; letter-spacing: 0.5px;">Status</div>
                        <div style="margin-top: 3px;">
                            <span style="display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; background-color: #fff3cd; color: #856404;">
                                {{ $statusLabel }}
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
                </tr>
            </table>
            @endif
        </div>

        <!-- Items -->
        <div style="padding: 25px 35px;">
            <h3 style="font-size: 13px; font-weight: 700; color: #1a1a2e; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 12px;">Claimed Items</h3>
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th style="background-color: #1a1a2e; color: #fff; font-size: 11px; text-transform: uppercase; padding: 9px 8px; text-align: left; width: 5%;">#</th>
                        <th style="background-color: #1a1a2e; color: #fff; font-size: 11px; text-transform: uppercase; padding: 9px 8px; text-align: left; width: 50%;">Product</th>
                        <th style="background-color: #1a1a2e; color: #fff; font-size: 11px; text-transform: uppercase; padding: 9px 8px; text-align: center; width: 10%;">Qty</th>
                        <th style="background-color: #1a1a2e; color: #fff; font-size: 11px; text-transform: uppercase; padding: 9px 8px; text-align: left; width: 35%;">Reason / Issue</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($record->items ?? [] as $index => $item)
                    <tr>
                        <td style="padding: 10px 8px; border-bottom: 1px solid #f0f0f0; font-size: 12px; color: #666; text-align: center;">{{ $index + 1 }}</td>
                        <td style="padding: 10px 8px; border-bottom: 1px solid #f0f0f0;">
                            <div style="font-weight: 600; font-size: 13px; color: #222;">{{ $item->product_name ?? $item->productVariant?->product?->product_full_name ?? $item->product?->product_full_name ?? 'Product' }}</div>
                            @php $sku = $item->sku ?? $item->productVariant?->sku ?? $item->product?->sku ?? null; @endphp
                            @if($sku)<div style="font-size: 11px; color: #888; margin-top: 2px;">SKU: {{ $sku }}</div>@endif
                        </td>
                        <td style="padding: 10px 8px; border-bottom: 1px solid #f0f0f0; text-align: center; font-size: 12px;">{{ $item->quantity ?? 1 }}</td>
                        <td style="padding: 10px 8px; border-bottom: 1px solid #f0f0f0; font-size: 12px; color: #666;">{{ $item->reason ?? $item->issue_description ?? $item->description ?? '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($record->notes || ($record->description ?? null))
        <div style="padding: 0 35px 25px;">
            <div style="background-color: #f9fafb; border-left: 3px solid #1a1a2e; padding: 15px 18px; border-radius: 0 4px 4px 0;">
                <div style="font-size: 11px; color: #888; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">Additional Notes</div>
                <p style="font-size: 13px; color: #555; line-height: 1.6;">{{ $record->notes ?? $record->description }}</p>
            </div>
        </div>
        @endif

        <!-- Footer -->
        <div style="padding: 20px 35px; text-align: center; border-top: 1px solid #f0f0f0;">
            <p style="font-size: 12px; color: #555; margin-bottom: 4px;">If you have any questions about your warranty claim, please contact us.</p>
            <p style="font-size: 12px; color: #888; margin-bottom: 4px;"><strong style="color: #555;">{{ $companyName }}</strong></p>
            @if($companyEmail)<p style="font-size: 11px; color: #aaa;">{{ $companyEmail }}@if($companyPhone) | {{ $companyPhone }}@endif</p>@endif
            @if($companyAddress)<p style="font-size: 11px; color: #aaa; margin-top: 3px;">{{ $companyAddress }}</p>@endif
        </div>
    </div>
</body>
</html>
