<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Quote {{ $record->quote_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 13px;
            color: #333;
            background-color: #f4f4f4;
            line-height: 1.5;
        }
        .email-wrapper {
            max-width: 700px;
            margin: 30px auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        /* Header */
        .header {
            background-color: #1a1a2e;
            padding: 28px 35px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header-left h1 {
            color: #ffffff;
            font-size: 26px;
            font-weight: 700;
            letter-spacing: 2px;
        }
        .header-left p {
            color: #a0aec0;
            font-size: 13px;
            margin-top: 4px;
        }
        .header-right {
            text-align: right;
        }
        .header-right img {
            max-height: 60px;
            max-width: 150px;
        }
        .header-right .logo-placeholder {
            color: #a0aec0;
            font-size: 20px;
            font-weight: bold;
        }
        /* Greeting */
        .greeting {
            padding: 25px 35px 10px;
            border-bottom: 1px solid #f0f0f0;
        }
        .greeting p {
            font-size: 14px;
            color: #555;
        }
        /* Info Grid */
        .info-section {
            padding: 20px 35px;
            background-color: #f9fafb;
            border-bottom: 1px solid #eee;
        }
        .info-grid {
            width: 100%;
            border-collapse: collapse;
        }
        .info-grid td {
            padding: 6px 10px 6px 0;
            width: 50%;
            vertical-align: top;
        }
        .info-grid .label {
            font-size: 11px;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .info-grid .value {
            font-size: 13px;
            font-weight: 600;
            color: #222;
        }
        /* Line Items */
        .items-section {
            padding: 25px 35px;
        }
        .items-section h3 {
            font-size: 13px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 12px;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
        }
        .items-table th {
            background-color: #1a1a2e;
            color: #ffffff;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 10px 8px;
            text-align: left;
        }
        .items-table th.text-right { text-align: right; }
        .items-table td {
            padding: 10px 8px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 12px;
            color: #444;
            vertical-align: top;
        }
        .items-table td.text-right { text-align: right; }
        .items-table .product-name { font-weight: 600; color: #222; }
        .items-table .product-sku { font-size: 11px; color: #888; }
        /* Totals */
        .totals-section {
            padding: 15px 35px 25px;
            border-top: 2px solid #f0f0f0;
        }
        .totals-table {
            width: 100%;
            border-collapse: collapse;
        }
        .totals-table td {
            padding: 5px 0;
            font-size: 13px;
        }
        .totals-table .totals-inner {
            width: 50%;
            margin-left: auto;
        }
        .totals-table .label { color: #666; }
        .totals-table .value { text-align: right; font-weight: 500; }
        .totals-table .total-row td {
            border-top: 2px solid #1a1a2e;
            font-weight: 700;
            font-size: 15px;
            color: #1a1a2e;
            padding-top: 10px;
        }
        /* CTA */
        .cta-section {
            padding: 20px 35px;
            background-color: #f9fafb;
            border-top: 1px solid #eee;
            border-bottom: 1px solid #eee;
            text-align: center;
        }
        .cta-section p { color: #555; font-size: 13px; margin-bottom: 5px; }
        /* Footer */
        .footer {
            padding: 20px 35px;
            text-align: center;
        }
        .footer p { font-size: 11px; color: #aaa; }
        .footer strong { color: #666; }
        /* Status badge */
        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            background-color: #e8f4fd;
            color: #2b6cb0;
        }
    </style>
</head>
<body>
    @php
        $companyBranding = \App\Modules\Settings\Models\CompanyBranding::getActive();
        $taxSetting = \App\Modules\Settings\Models\TaxSetting::getDefault();
        $currency = \App\Modules\Settings\Models\CurrencySetting::getBase();
        $currSymbol = $currency ? $currency->currency_symbol : 'AED';
        $vatRate = $taxSetting ? $taxSetting->rate : 5;
        $companyName = $companyBranding?->company_name ?? 'TunerStop LLC';
        $companyEmail = $companyBranding?->company_email ?? '';
        $companyPhone = $companyBranding?->company_phone ?? '';
        $companyAddress = $companyBranding?->company_address ?? '';
        $taxNumber = $companyBranding?->tax_registration_number ?? '';
        // Use the passed emailLogoUrl if available (set by QuoteSentMail::content()),
        // otherwise build the CloudFront URL directly from the logo_path.
        $logoUrl = $emailLogoUrl ?? null;
        if (!$logoUrl && ($companyBranding?->logo_path)) {
            $cdnBase = rtrim(config('filesystems.disks.s3.url', ''), '/');
            if ($cdnBase) {
                $logoUrl = $cdnBase . '/' . ltrim($companyBranding->logo_path, '/');
            } else {
                $logoUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($companyBranding->logo_path);
            }
        }
    @endphp

    <div class="email-wrapper">
        <!-- Header -->
        <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f8f9fa; border-bottom: 3px solid #1a1a2e; padding: 28px 35px;">
            <tr>
                <td width="50%" style="vertical-align: middle;">
                    <h1 style="color: #1a1a2e; font-size: 26px; font-weight: 700; letter-spacing: 2px; margin: 0;">QUOTE</h1>
                    <p style="color: #666; font-size: 13px; margin-top: 4px; margin-bottom: 0;">{{ $record->quote_number }}</p>
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
            <p style="font-size: 14px; color: #444;">
                Dear <strong>{{ $record->customer?->business_name ?? $record->customer?->name ?? 'Valued Customer' }}</strong>,
            </p>
            <p style="font-size: 13px; color: #666; margin-top: 8px;">
                Please find your quote details below. A downloadable PDF copy is also attached to this email for your records.
            </p>
        </div>

        <!-- Quote Meta Info -->
        <div style="padding: 20px 35px; background-color: #f9fafb; border-bottom: 1px solid #eee;">
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="width: 25%; padding: 6px 0; vertical-align: top;">
                        <div style="font-size: 10px; color: #888; text-transform: uppercase; letter-spacing: 0.5px;">Quote #</div>
                        <div style="font-size: 13px; font-weight: 600; color: #222; margin-top: 3px;">{{ $record->quote_number }}</div>
                    </td>
                    <td style="width: 25%; padding: 6px 0; vertical-align: top;">
                        <div style="font-size: 10px; color: #888; text-transform: uppercase; letter-spacing: 0.5px;">Issue Date</div>
                        <div style="font-size: 13px; font-weight: 600; color: #222; margin-top: 3px;">{{ $record->issue_date ? \Carbon\Carbon::parse($record->issue_date)->format('M d, Y') : date('M d, Y') }}</div>
                    </td>
                    <td style="width: 25%; padding: 6px 0; vertical-align: top;">
                        <div style="font-size: 10px; color: #888; text-transform: uppercase; letter-spacing: 0.5px;">Valid Until</div>
                        <div style="font-size: 13px; font-weight: 600; color: #222; margin-top: 3px;">{{ $record->valid_until ? \Carbon\Carbon::parse($record->valid_until)->format('M d, Y') : 'N/A' }}</div>
                    </td>
                    <td style="width: 25%; padding: 6px 0; vertical-align: top; text-align: right;">
                        <div style="font-size: 10px; color: #888; text-transform: uppercase; letter-spacing: 0.5px;">Status</div>
                        <div style="margin-top: 3px;">
                            <span style="display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; background-color: #e8f4fd; color: #2b6cb0;">
                                {{ $record->quote_status?->label() ?? 'SENT' }}
                            </span>
                        </div>
                    </td>
                </tr>
            </table>

            <!-- Customer Info -->
            @if($record->customer)
            <table style="width: 100%; border-collapse: collapse; margin-top: 15px; padding-top: 15px; border-top: 1px solid #e8e8e8;">
                <tr>
                    <td style="vertical-align: top; padding-right: 20px;">
                        <div style="font-size: 10px; color: #888; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Bill To</div>
                        <div style="font-size: 13px; font-weight: 600; color: #222;">{{ $record->customer->business_name ?? $record->customer->name }}</div>
                        @if($record->customer->email)
                            <div style="font-size: 12px; color: #666;">{{ $record->customer->email }}</div>
                        @endif
                        @if($record->customer->phone)
                            <div style="font-size: 12px; color: #666;">{{ $record->customer->phone }}</div>
                        @endif
                    </td>
                    @if($record->vehicle_year || $record->vehicle_make || $record->vehicle_model || $record->vehicle_sub_model)
                    <td style="vertical-align: top;">
                        <div style="font-size: 10px; color: #888; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Vehicle</div>
                        <div style="font-size: 13px; font-weight: 600; color: #222;">
                            {{ implode(' ', array_filter([$record->vehicle_year, $record->vehicle_make, $record->vehicle_model, $record->vehicle_sub_model])) }}
                        </div>
                    </td>
                    @endif
                </tr>
            </table>
            @endif
        </div>

        <!-- Line Items -->
        <div style="padding: 25px 35px;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th style="background-color: #1a1a2e; color: #fff; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; padding: 9px 8px; text-align: left; width: 4%;">#</th>
                        <th style="background-color: #1a1a2e; color: #fff; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; padding: 9px 8px; text-align: left; width: 8%;">Image</th>
                        <th style="background-color: #1a1a2e; color: #fff; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; padding: 9px 8px; text-align: left; width: 44%;">Product / Description</th>
                        <th style="background-color: #1a1a2e; color: #fff; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; padding: 9px 8px; text-align: center; width: 8%;">Qty</th>
                        <th style="background-color: #1a1a2e; color: #fff; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; padding: 9px 8px; text-align: right; width: 18%;">Unit Price</th>
                        <th style="background-color: #1a1a2e; color: #fff; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; padding: 9px 8px; text-align: right; width: 18%;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($record->items as $index => $item)
                    @php
                        $imgUrl = null;
                        if (!empty($item->product_image)) {
                            $imgUrl = \App\Utility\Helper::getImagePath($item->product_image);
                        } else {
                            // Try variant_snapshot then addon_snapshot for image
                            $vSnap = is_string($item->variant_snapshot) ? json_decode($item->variant_snapshot, true) : (array)($item->variant_snapshot ?? []);
                            $rawImg = $vSnap['image'] ?? null;
                            if (!$rawImg) {
                                $aSnap = is_string($item->addon_snapshot) ? json_decode($item->addon_snapshot, true) : (array)($item->addon_snapshot ?? []);
                                $rawImg = $aSnap['image_1'] ?? null;
                            }
                            if ($rawImg) {
                                $cdnBase = rtrim(env('AWS_CLOUDFRONT_URL') ?: env('S3IMAGES_URL') ?: 'https://d2iosncs8hpu1u.cloudfront.net', '/');
                                $imgUrl = str_starts_with($rawImg, 'http') ? $rawImg : ($cdnBase . '/' . ltrim($rawImg, '/'));
                            }
                        }
                        if (empty($vSnap)) {
                            $vSnap = is_string($item->variant_snapshot) ? json_decode($item->variant_snapshot, true) : (array)($item->variant_snapshot ?? []);
                        }
                        $itemFinish = $vSnap['finish_name'] ?? $vSnap['finish'] ?? $item->finish ?? null;
                        if (!$itemFinish && !empty($item->product_variant_id)) {
                            $fv = \App\Modules\Products\Models\ProductVariant::with('finishRelation')->find($item->product_variant_id);
                            $itemFinish = $fv?->finishRelation?->finish;
                        }
                        $itemSize   = $vSnap['size'] ?? $item->size ?? null;
                        $itemBolt   = $vSnap['bolt_pattern'] ?? $item->bolt_pattern ?? null;
                        $itemOffset = $vSnap['offset'] ?? $item->offset ?? null;
                    @endphp
                    <tr>
                        <td style="padding: 10px 8px; border-bottom: 1px solid #f0f0f0; font-size: 12px; color: #666; text-align: center;">{{ $index + 1 }}</td>
                        <td style="padding: 6px 8px; border-bottom: 1px solid #f0f0f0; text-align: center;">
                            @if($imgUrl)
                                <img src="{{ $imgUrl }}" alt="Product" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px; border: 1px solid #eee;">
                            @else
                                <div style="width: 50px; height: 50px; background: #f3f4f6; border-radius: 4px; display: inline-block;"></div>
                            @endif
                        </td>
                        <td style="padding: 10px 8px; border-bottom: 1px solid #f0f0f0;">
                            <div style="font-weight: 600; font-size: 13px; color: #222;">{{ $item->product_name ?? 'Unknown Product' }}</div>
                            @if($item->sku)
                                <div style="font-size: 11px; color: #888; margin-top: 2px;">SKU: {{ $item->sku }}</div>
                            @endif
                            @if($item->brand_name)
                                <div style="font-size: 11px; color: #888;">Brand: {{ $item->brand_name }}</div>
                            @endif
                            @if($itemFinish)
                                <div style="font-size: 11px; color: #888;">Finish: {{ $itemFinish }}</div>
                            @endif
                            @if($itemSize)
                                <div style="font-size: 11px; color: #888;">Size: {{ $itemSize }}</div>
                            @endif
                            @if($itemBolt)
                                <div style="font-size: 11px; color: #888;">Bolt: {{ $itemBolt }}</div>
                            @endif
                            @if($itemOffset)
                                <div style="font-size: 11px; color: #888;">Offset: ET{{ $itemOffset }}</div>
                            @endif
                        </td>
                        <td style="padding: 10px 8px; border-bottom: 1px solid #f0f0f0; text-align: center; font-size: 12px;">{{ $item->quantity }}</td>
                        <td style="padding: 10px 8px; border-bottom: 1px solid #f0f0f0; text-align: right; font-size: 12px;">{{ $currSymbol }} {{ number_format($item->unit_price, 2) }}</td>
                        <td style="padding: 10px 8px; border-bottom: 1px solid #f0f0f0; text-align: right; font-size: 12px; font-weight: 600;">{{ $currSymbol }} {{ number_format($item->line_total, 2) }}</td>
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
                                $isZeroRatedEmail = !empty($record->is_zero_rated);
                                $displaySubTotalEmail = $isZeroRatedEmail
                                    ? round(floatval($record->total) + floatval($record->discount ?? 0) - floatval($record->shipping ?? 0), 2)
                                    : floatval($record->sub_total);
                            @endphp
                            <tr>
                                <td style="padding: 5px 0; font-size: 13px; color: #666;">Subtotal:</td>
                                <td style="padding: 5px 0; font-size: 13px; text-align: right; font-weight: 500;">{{ $currSymbol }} {{ number_format($displaySubTotalEmail, 2) }}</td>
                            </tr>
                            @if($record->discount > 0)
                            <tr>
                                <td style="padding: 5px 0; font-size: 13px; color: #666;">Discount:</td>
                                <td style="padding: 5px 0; font-size: 13px; text-align: right; color: #e53e3e;">-{{ $currSymbol }} {{ number_format($record->discount, 2) }}</td>
                            </tr>
                            @endif
                            @if($record->shipping > 0)
                            <tr>
                                <td style="padding: 5px 0; font-size: 13px; color: #666;">Shipping:</td>
                                <td style="padding: 5px 0; font-size: 13px; text-align: right;">{{ $currSymbol }} {{ number_format($record->shipping, 2) }}</td>
                            </tr>
                            @endif
                            @php
                                if ($isZeroRatedEmail) {
                                    $vatAmount = 0.0;
                                } else {
                                    $vatAmount = floatval($record->tax ?? $record->vat ?? 0);
                                    // Fallback: if DB stores 0, derive from total - subtotal
                                    if ($vatAmount == 0 && floatval($record->total ?? 0) > 0 && $displaySubTotalEmail > 0) {
                                        $vatAmount = round(floatval($record->total) - $displaySubTotalEmail - floatval($record->shipping ?? 0), 2);
                                    }
                                    // Last resort: calculate from subtotal * rate
                                    if ($vatAmount == 0 && $displaySubTotalEmail > 0) {
                                        $vatAmount = round($displaySubTotalEmail * (floatval($vatRate) / 100), 2);
                                    }
                                }
                            @endphp
                            <tr>
                                <td style="padding: 5px 0; font-size: 13px; color: #666;">
                                    @if($record->is_zero_rated)
                                        VAT (0% — Zero Rated):
                                    @else
                                        VAT ({{ $vatRate }}%):
                                    @endif
                                </td>
                                <td style="padding: 5px 0; font-size: 13px; text-align: right; font-weight: 500;">{{ $currSymbol }} {{ number_format($vatAmount, 2) }}</td>
                            </tr>
                            <tr>
                                <td colspan="2" style="border-top: 2px solid #1a1a2e; padding-top: 6px;"></td>
                            </tr>
                            <tr>
                                <td style="padding: 5px 0 0; font-size: 15px; font-weight: 700; color: #1a1a2e;">Total:</td>
                                <td style="padding: 5px 0 0; font-size: 15px; font-weight: 700; color: #1a1a2e; text-align: right;">{{ $currSymbol }} {{ number_format($record->total, 2) }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </div>

        @if($record->order_notes)
        <!-- Notes -->
        <div style="padding: 15px 35px 20px; background-color: #f9fafb; border-top: 1px solid #eee;">
            <div style="font-size: 11px; color: #888; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">Notes</div>
            <p style="font-size: 13px; color: #555;">{{ $record->order_notes }}</p>
        </div>
        @endif

        <!-- CTA -->
        <div style="padding: 20px 35px; background-color: #eef6ff; border-top: 1px solid #dbeafe; text-align: center;">
            <p style="font-size: 14px; color: #2b6cb0; font-weight: 600; margin-bottom: 5px;">Ready to proceed?</p>
            <p style="font-size: 13px; color: #555;">Reply to this email or contact us and we'll get started right away.</p>
        </div>

        <!-- Footer -->
        <div style="padding: 20px 35px; text-align: center; border-top: 1px solid #f0f0f0;">
            <p style="font-size: 12px; color: #888; margin-bottom: 4px;"><strong style="color: #555;">{{ $companyName }}</strong></p>
            @if($companyEmail)
                <p style="font-size: 11px; color: #aaa;">{{ $companyEmail }}@if($companyPhone) &nbsp;|&nbsp; {{ $companyPhone }}@endif</p>
            @endif
            @if($companyAddress)
                <p style="font-size: 11px; color: #aaa; margin-top: 3px;">{{ $companyAddress }}</p>
            @endif
        </div>
    </div>
</body>
</html>
