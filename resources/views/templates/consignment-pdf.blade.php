<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consignment - {{ $consignment->consignment_number }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 7px;
            line-height: 1.2;
            color: #333;
            padding: 10px;
        }
        
        .clearfix::after {
            content: "";
            display: table;
            clear: both;
        }
        
        .header {
            border-bottom: 2px solid #2563eb;
            padding-bottom: 8px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header-left {
            flex: 1;
        }
        
        .header-right {
            text-align: right;
        }
        
        .logo {
            max-width: 100px;
            max-height: 35px;
            margin-bottom: 4px;
        }
        
        .logo-placeholder {
            width: 100px;
            height: 35px;
            background: #2563eb;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 8px;
            font-weight: bold;
            border-radius: 3px;
            margin-bottom: 4px;
        }
        
        .header h1 {
            font-size: 16px;
            color: #2563eb;
            margin-bottom: 3px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .header .subtitle {
            font-size: 10px;
            color: #666;
            font-weight: normal;
        }
        
        .company-info {
            text-align: right;
        }
        
        .company-info h2 {
            font-size: 9px;
            color: #1e40af;
            margin-bottom: 3px;
        }
        
        .company-info p {
            font-size: 6px;
            color: #666;
            margin: 2px 0;
            line-height: 1.3;
        }
        
        .info-section {
            margin-bottom: 8px;
        }
        
        .info-left {
            float: left;
            width: 48%;
        }
        
        .info-right {
            float: right;
            width: 48%;
        }
        
        .info-box {
            border: 1px solid #ddd;
            padding: 6px;
            background-color: #f9fafb;
            border-radius: 3px;
            min-height: 80px;
        }
        
        .info-box h3 {
            font-size: 8px;
            color: #1e40af;
            border-bottom: 1px solid #2563eb;
            padding-bottom: 3px;
            margin-bottom: 4px;
            font-weight: bold;
        }
        
        .info-row {
            margin-bottom: 3px;
            display: flex;
            justify-content: space-between;
        }
        
        .info-label {
            font-weight: bold;
            color: #555;
            flex: 0 0 40%;
        }
        
        .info-value {
            color: #333;
            flex: 1;
            text-align: right;
        }
        
        .status-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 2px;
            font-size: 6px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-draft {
            background-color: #f3f4f6;
            color: #6b7280;
        }
        
        .status-sent {
            background-color: #dbeafe;
            color: #1e40af;
        }
        
        .status-partially-returned {
            background-color: #fef3c7;
            color: #92400e;
        }
        
        .status-completed {
            background-color: #d1fae5;
            color: #065f46;
        }
        
        .status-cancelled {
            background-color: #fee2e2;
            color: #991b1b;
        }
        
        .summary-section {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding: 6px;
            background-color: #f9fafb;
            border: 1px solid #ddd;
            border-radius: 3px;
        }
        
        .summary-box {
            text-align: center;
            flex: 1;
            padding: 3px;
        }
        
        .summary-value {
            font-size: 12px;
            font-weight: bold;
            color: #2563eb;
            margin-bottom: 2px;
        }
        
        .summary-label {
            font-size: 6px;
            color: #666;
            text-transform: uppercase;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
            font-size: 6px;
        }
        
        .items-table thead {
            background-color: #2563eb;
            color: white;
        }
        
        .items-table th {
            padding: 4px 3px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #1e40af;
        }
        
        .items-table td {
            padding: 3px 3px;
            border: 1px solid #ddd;
        }
        
        .items-table tbody tr:nth-child(even) {
            background-color: #f9fafb;
        }
        
        .item-name {
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 2px;
        }
        
        .item-description {
            color: #6b7280;
            font-size: 5px;
        }
        
        .sku-badge {
            display: inline-block;
            background-color: #e5e7eb;
            padding: 1px 3px;
            border-radius: 2px;
            font-size: 5px;
            color: #374151;
            margin-top: 1px;
        }
        
        .quantity-badge {
            display: inline-block;
            background-color: #dbeafe;
            color: #1e40af;
            padding: 1px 4px;
            border-radius: 2px;
            font-weight: bold;
        }
        
        .text-center {
            text-align: center;
        }
        
        .text-right {
            text-align: right;
        }
        
        .totals-section {
            float: right;
            width: 300px;
            margin-bottom: 10px;
        }
        
        .totals-table {
            width: 100%;
            font-size: 7px;
        }
        
        .totals-table td {
            padding: 3px 6px;
        }
        
        .totals-table .label {
            text-align: right;
            font-weight: bold;
            color: #555;
        }
        
        .totals-table .value {
            text-align: right;
            width: 120px;
            font-weight: bold;
        }
        
        .totals-table .grand-total {
            border-top: 2px solid #2563eb;
            background-color: #eff6ff;
        }
        
        .totals-table .grand-total .label,
        .totals-table .grand-total .value {
            font-size: 8px;
            color: #1e40af;
            padding: 4px 6px;
        }
        
        .notes-section {
            clear: both;
            margin-top: 10px;
        }
        
        .notes-box {
            border: 1px solid #ddd;
            padding: 6px;
            background-color: #f9fafb;
            border-radius: 3px;
            margin-bottom: 6px;
        }
        
        .notes-box h3 {
            font-size: 7px;
            color: #1e40af;
            border-bottom: 1px solid #2563eb;
            padding-bottom: 2px;
            margin-bottom: 3px;
        }
        
        .notes-box p {
            font-size: 6px;
            color: #333;
            line-height: 1.3;
        }
        
        .notes-box ul {
            margin: 0;
            padding-left: 10px;
            font-size: 5px;
            line-height: 1.2;
        }
        
        .notes-box ul li {
            margin-bottom: 2px;
        }
        
        .footer {
            margin-top: 15px;
            padding-top: 6px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 5px;
            color: #666;
        }
        
        .footer p {
            margin: 2px 0;
        }
        
        @media print {
            body {
                padding: 0;
            }
            .header {
                page-break-after: avoid;
            }
            .items-table {
                page-break-inside: avoid;
            }
            .summary-section {
                page-break-inside: avoid;
            }
            .notes-section {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-left">
            <h1>CONSIGNMENT</h1>
            <div class="subtitle">{{ $consignment->consignment_number }}</div>
        </div>
        
        <div class="header-right">
            @if($logo)
                <img src="{{ $logo }}" alt="Company Logo" class="logo">
            @else
                <div class="logo-placeholder">
                    {{ $companyName }}
                </div>
            @endif
            <div class="company-info">
                <h2>{{ $companyName }}</h2>
                @if($companyAddress)
                    <p>{{ $companyAddress }}</p>
                @endif
                @if($companyPhone)
                    <p>Phone: {{ $companyPhone }}</p>
                @endif
                @if($companyEmail)
                    <p>Email: {{ $companyEmail }}</p>
                @endif
                <p>Generated: {{ now()->format('d/m/Y H:i') }}</p>
            </div>
        </div>
    </div>

    <div class="info-section clearfix">
        <div class="info-left">
            <div class="info-box">
                <h3>Customer Information</h3>
                <div class="info-row">
                    <span class="info-label">Name:</span>
                    <span class="info-value">{{ $consignment->customer?->name ?? 'N/A' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email:</span>
                    <span class="info-value">{{ $consignment->customer?->email ?? 'N/A' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Phone:</span>
                    <span class="info-value">{{ $consignment->customer?->phone ?? 'N/A' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Representative:</span>
                    <span class="info-value">{{ $consignment->representative?->name ?? 'N/A' }}</span>
                </div>
            </div>
        </div>
        
        <div class="info-right">
            <div class="info-box">
                <h3>Consignment Details</h3>
                <div class="info-row">
                    <span class="info-label">Status:</span>
                    <span class="info-value">
                        <span class="status-badge status-{{ str_replace('_', '-', $consignment->status->value) }}">
                            {{ $consignment->status->getLabel() }}
                        </span>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Channel:</span>
                    <span class="info-value">{{ ucfirst($consignment->channel) }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Warehouse:</span>
                    <span class="info-value">{{ $consignment->warehouse?->warehouse_name ?? 'N/A' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Created:</span>
                    <span class="info-value">{{ $consignment->created_at?->format('d/m/Y H:i') ?? 'N/A' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Expected Return:</span>
                    <span class="info-value">{{ $consignment->expected_return_date?->format('d/m/Y') ?? 'Not Set' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Delivery Date:</span>
                    <span class="info-value">{{ $consignment->delivery_date?->format('d/m/Y') ?? 'Not Delivered' }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Statistics -->
    <div class="summary-section clearfix">
        <div class="summary-box">
            <div class="summary-value">{{ $consignment->items->sum('quantity') }}</div>
            <div class="summary-label">Items Sent</div>
        </div>
        <div class="summary-box">
            <div class="summary-value">{{ $consignment->items->sum('quantity_sold') }}</div>
            <div class="summary-label">Items Sold</div>
        </div>
        <div class="summary-box">
            <div class="summary-value">{{ $consignment->items->sum('quantity_returned') }}</div>
            <div class="summary-label">Items Returned</div>
        </div>
        <div class="summary-box">
            <div class="summary-value">{{ $currency }} {{ number_format($consignment->total, 2) }}</div>
            <div class="summary-label">Total Value</div>
        </div>
    </div>

    <!-- Consignment Items -->
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 35%;">Item Description</th>
                <th style="width: 10%;" class="text-center">Qty</th>
                <th style="width: 12%;" class="text-right">Unit Price</th>
                <th style="width: 12%;" class="text-right">Total</th>
                <th style="width: 10%;" class="text-center">Sold</th>
                <th style="width: 10%;" class="text-center">Returned</th>
                <th style="width: 11%;" class="text-center">Available</th>
            </tr>
        </thead>
        <tbody>
            @forelse($consignment->items as $item)
                <tr>
                    <td>
                        <div class="item-name">{{ $item->product_name }}</div>
                        @if($item->product?->brand?->name)
                            <div class="item-description">{{ $item->product->brand->name }}</div>
                        @endif
                        @if($item->product?->sku)
                            <div class="sku-badge">SKU: {{ $item->product->sku }}</div>
                        @endif
                    </td>
                    <td class="text-center">{{ $item->quantity }}</td>
                    <td class="text-right">{{ $currency }} {{ number_format($item->unit_price, 2) }}</td>
                    <td class="text-right">{{ $currency }} {{ number_format($item->total, 2) }}</td>
                    <td class="text-center">
                        <span class="quantity-badge">{{ $item->quantity_sold }}</span>
                    </td>
                    <td class="text-center">
                        <span class="quantity-badge">{{ $item->quantity_returned }}</span>
                    </td>
                    <td class="text-center">
                        <span class="quantity-badge">{{ $item->quantity - $item->quantity_sold + $item->quantity_returned }}</span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center" style="padding: 20px; color: #666;">
                        No items found for this consignment.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Totals -->
    <div class="totals-section">
        <table class="totals-table">
            <tr>
                <td class="label">Subtotal:</td>
                <td class="value">{{ $currency }} {{ number_format($consignment->subtotal, 2) }}</td>
            </tr>
            @if($consignment->tax > 0)
                <tr>
                    <td class="label">Tax ({{ $vatRate }}%):</td>
                    <td class="value">{{ $currency }} {{ number_format($consignment->tax, 2) }}</td>
                </tr>
            @endif
            <tr class="grand-total">
                <td class="label">Grand Total:</td>
                <td class="value">{{ $currency }} {{ number_format($consignment->total, 2) }}</td>
            </tr>
        </table>
    </div>

    @if($consignment->notes)
        <div class="notes-section">
            <div class="notes-box">
                <h3>Notes</h3>
                <p>{{ $consignment->notes }}</p>
            </div>
        </div>
    @endif

    <!-- Terms and Conditions -->
    <div class="notes-section">
        <div class="notes-box">
            <h3>Terms and Conditions</h3>
            <ul>
                <li>All consigned items remain the property of the consignor until sold.</li>
                <li>{{ $companyName }} will use reasonable care in handling consigned items but is not liable for damage, theft, or loss.</li>
                <li>Consignor authorizes {{ $companyName }} to sell items at agreed-upon prices and terms.</li>
                <li>Settlement will be made according to the agreed commission structure.</li>
                <li>Items not sold within the consignment period may be returned to the consignor.</li>
                <li>Any modifications to this agreement must be made in writing and signed by both parties.</li>
            </ul>
        </div>
    </div>

    <div class="footer">
        <p>This document was generated electronically and is valid without signature.</p>
        @if($companyEmail)
            <p>For questions regarding this consignment, please contact us at {{ $companyEmail }}</p>
        @endif
        <p>&copy; {{ date('Y') }} {{ $companyName }}. All rights reserved.</p>
    </div>
</body>
</html>
