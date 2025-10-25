<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $documentType === 'quote' ? 'Quote' : 'Invoice' }} | {{ $documentType === 'quote' ? ($record->quote_number ?? 'N/A') : ($record->order_number ?? 'N/A') }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 12px; color: #333; line-height: 1.6; }
        .container { max-width: 800px; margin: 0 auto; padding: 20px; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 20px; }
        .logo { max-width: 200px; max-height: 80px; }
        .logo-placeholder { width: 200px; height: 80px; background: #dc3545; color: white; display: flex; align-items: center; justify-content: center; font-size: 24px; font-weight: bold; }
        h1 { font-size: 32px; font-weight: bold; margin: 0; }
        .info-section { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-bottom: 30px; }
        .info-box h4 { font-weight: bold; margin-bottom: 5px; font-size: 12px; }
        .info-box p { margin: 0; font-size: 11px; line-height: 1.4; }
        .vehicle-section { background-color: #f8f9fa; padding: 15px; margin-bottom: 20px; border-left: 4px solid #007bff; }
        .vehicle-section h4 { font-weight: bold; margin-bottom: 10px; font-size: 14px; }
        .vehicle-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; }
        .vehicle-item strong { display: block; font-size: 11px; color: #666; margin-bottom: 3px; }
        .vehicle-item span { font-size: 12px; font-weight: 600; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        thead { background-color: #f8f9fa; }
        th { padding: 12px; border: 1px solid #ddd; text-align: left; font-weight: bold; font-size: 11px; }
        td { padding: 10px; border: 1px solid #ddd; font-size: 11px; vertical-align: top; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .totals-section { display: flex; justify-content: space-between; margin-top: 30px; }
        .notes { width: 55%; }
        .totals { width: 40%; }
        .totals table { margin: 0; }
        .totals td { border: none; border-bottom: 1px solid #eee; padding: 8px; }
        .totals .total-row { background-color: #f8f9fa; font-weight: bold; font-size: 14px; border-top: 2px solid #333; }
        .footer { margin-top: 50px; text-align: center; font-size: 10px; color: #999; border-top: 1px solid #eee; padding-top: 20px; }
        .small-text { font-size: 10px; color: #666; }
        .brand-name { color: #007bff; font-weight: 600; }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div>
                <h1>{{ $documentType === 'quote' ? 'QUOTE' : 'INVOICE' }}</h1>
                <p style="margin-top: 5px; font-size: 14px;">
                    {{ $documentType === 'quote' ? ($record->quote_number ?? 'N/A') : ($record->order_number ?? 'N/A') }}
                </p>
            </div>
            <div>
                @if($logo)
                    <img src="{{ $logo }}" alt="Company Logo" class="logo">
                @else
                    <div class="logo-placeholder">
                        {{ $companyName ?? 'TUNERSTOP' }}
                    </div>
                @endif
            </div>
        </div>

        <!-- Company and Customer Info -->
        <div class="info-section">
            <!-- From -->
            <div class="info-box">
                <h4>From:</h4>
                <p><strong>{{ $companyName ?? 'TunerStop LLC' }}</strong></p>
                <p>{{ $companyAddress ?? '479 Jahanzeb Block street 14 Allama Iqbal Town' }}</p>
                <p>Phone: {{ $companyPhone ?? '0334934247' }}</p>
                <p>Email: {{ $companyEmail ?? 'admin@tunerStop.com' }}</p>
                <p>Tax No: {{ $taxNumber ?? '66666684444' }}</p>
            </div>

            <!-- To -->
            <div class="info-box">
                <h4>To:</h4>
                <p><strong>{{ $record->customer->business_name ?? $record->customer->name ?? 'Unknown Customer' }}</strong></p>
                @if($record->customer)
                    <p>Phone: {{ $record->customer->phone ?? 'N/A' }}</p>
                    <p>Email: {{ $record->customer->email ?? 'N/A' }}</p>
                @endif
            </div>

            <!-- Document Details -->
            <div class="info-box">
                <div style="margin-bottom: 15px;">
                    <h4>{{ $documentType === 'quote' ? 'Quote' : 'Invoice' }} Date:</h4>
                    <p>{{ $record->issue_date ? $record->issue_date->format('m/d/Y') : date('m/d/Y') }}</p>
                </div>
                <div style="margin-bottom: 15px;">
                    <h4>{{ $documentType === 'quote' ? 'Valid Until:' : 'Due Date:' }}</h4>
                    <p>{{ $record->valid_until ? $record->valid_until->format('m/d/Y') : 'N/A' }}</p>
                </div>
                @if($documentType === 'invoice')
                    <div>
                        <h4>Status:</h4>
                        <p><strong>{{ strtoupper($record->payment_status ?? 'PENDING') }}</strong></p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Vehicle Information -->
        @if($record->vehicle_year || $record->vehicle_make || $record->vehicle_model || $record->vehicle_sub_model)
        <div class="vehicle-section">
            <h4>Vehicle Information</h4>
            <div class="vehicle-grid">
                <div class="vehicle-item">
                    <strong>Year:</strong>
                    <span>{{ $record->vehicle_year ?? 'N/A' }}</span>
                </div>
                <div class="vehicle-item">
                    <strong>Make:</strong>
                    <span>{{ $record->vehicle_make ?? 'N/A' }}</span>
                </div>
                <div class="vehicle-item">
                    <strong>Model:</strong>
                    <span>{{ $record->vehicle_model ?? 'N/A' }}</span>
                </div>
                <div class="vehicle-item">
                    <strong>Sub Model:</strong>
                    <span>{{ $record->vehicle_sub_model ?? 'N/A' }}</span>
                </div>
            </div>
        </div>
        @endif

        <!-- Line Items Table -->
        <table>
            <thead>
                <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: 50%;">Description</th>
                    <th style="width: 10%;" class="text-center">SKU</th>
                    <th style="width: 10%;" class="text-center">Qty</th>
                    <th style="width: 12%;" class="text-right">Unit Price</th>
                    <th style="width: 13%;" class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse($record->items as $index => $item)
                    <tr>
                        <td class="text-center">{{ $index + 1 }}</td>
                        <td>
                            <strong>{{ $item->product_name ?? 'Unknown Product' }}</strong>
                            @if($item->brand_name)
                                <br><span class="small-text">Brand: <span class="brand-name">{{ $item->brand_name }}</span></span>
                            @endif
                            @if($item->product_description)
                                <br><span class="small-text">{{ Str::limit($item->product_description, 100) }}</span>
                            @endif
                            {{-- Warehouse Information --}}
                            @if($item->warehouse)
                                <br><small style="color: #666; font-size: 10px; display: inline-block; margin-top: 4px;">
                                    📦 Warehouse: {{ $item->warehouse->warehouse_name ?? $item->warehouse->name }}
                                </small>
                            @elseif($item->warehouse_id === null)
                                <br><small style="color: #666; font-size: 10px; display: inline-block; margin-top: 4px;">
                                    ⚡ Non-Stock (Special Order)
                                </small>
                            @endif
                        </td>
                        <td class="text-center">
                            <span class="small-text">{{ $item->sku ?? 'N/A' }}</span>
                        </td>
                        <td class="text-center">{{ $item->quantity ?? 1 }}</td>
                        <td class="text-right">{{ $currency }} {{ number_format($item->unit_price ?? 0, 2) }}</td>
                        <td class="text-right">{{ $currency }} {{ number_format($item->line_total ?? 0, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center" style="padding: 30px; color: #999; font-style: italic;">
                            No items in this {{ $documentType }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <!-- Totals and Notes -->
        <div class="totals-section">
            <div class="notes">
                @if($record->order_notes)
                    <h4 style="margin-bottom: 10px;">Customer Notes</h4>
                    <p style="line-height: 1.5; color: #666; font-size: 11px;">{{ $record->order_notes }}</p>
                @endif
                @if($record->internal_notes)
                    <h4 style="margin-bottom: 10px; margin-top: 20px;">Internal Notes</h4>
                    <p style="line-height: 1.5; color: #666; font-size: 11px;">{{ $record->internal_notes }}</p>
                @endif
            </div>

            <div class="totals">
                <table>
                    <tr>
                        <td style="font-weight: bold;">Subtotal:</td>
                        <td class="text-right">{{ $currency }} {{ number_format($record->sub_total ?? 0, 2) }}</td>
                    </tr>
                    @if(($record->shipping ?? 0) > 0)
                    <tr>
                        <td style="font-weight: bold;">Shipping:</td>
                        <td class="text-right">{{ $currency }} {{ number_format($record->shipping ?? 0, 2) }}</td>
                    </tr>
                    @endif
                    @if(($record->discount ?? 0) > 0)
                    <tr>
                        <td style="font-weight: bold;">Discount:</td>
                        <td class="text-right">-{{ $currency }} {{ number_format($record->discount ?? 0, 2) }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td style="font-weight: bold;">VAT ({{ $vatRate ?? 5 }}%):</td>
                        <td class="text-right">{{ $currency }} {{ number_format($record->vat ?? 0, 2) }}</td>
                    </tr>
                    <tr class="total-row">
                        <td>Total:</td>
                        <td class="text-right">{{ $currency }} {{ number_format($record->total ?? 0, 2) }}</td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>Thank you for your business!</p>
            @if($companyEmail || $companyPhone)
                <p>
                    @if($companyEmail)Contact: {{ $companyEmail }}@endif
                    @if($companyEmail && $companyPhone) | @endif
                    @if($companyPhone)Phone: {{ $companyPhone }}@endif
                </p>
            @endif
        </div>
    </div>
</body>
</html>
