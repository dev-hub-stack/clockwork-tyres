<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Warranty Claim - {{ $claim->claim_number ?? 'WC-' . ($claim->id ?? 'N/A') }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 9px;
            line-height: 1.4;
            color: #333;
            padding: 15px;
        }
        
        .clearfix::after {
            content: "";
            display: table;
            clear: both;
        }
        
        .header {
            border-bottom: 3px solid #dc2626;
            padding-bottom: 15px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            font-size: 28px;
            color: #dc2626;
            margin-bottom: 5px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .header .subtitle {
            font-size: 14px;
            color: #666;
            font-weight: normal;
        }
        
        .header-left {
            flex: 1;
        }
        
        .header-right {
            text-align: right;
        }
        
        .logo {
            max-width: 180px;
            max-height: 70px;
            margin-bottom: 10px;
        }
        
        .logo-placeholder {
            width: 180px;
            height: 70px;
            background: #dc2626;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .company-info {
            text-align: right;
        }
        
        .company-info h2 {
            font-size: 16px;
            color: #991b1b;
            margin-bottom: 3px;
        }
        
        .company-info p {
            font-size: 8px;
            color: #666;
            margin: 2px 0;
        }
        
        .info-section {
            margin-bottom: 20px;
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
            padding: 10px;
            background-color: #f9fafb;
            border-radius: 4px;
            min-height: 120px;
        }
        
        .info-box h3 {
            font-size: 11px;
            color: #991b1b;
            border-bottom: 2px solid #dc2626;
            padding-bottom: 5px;
            margin-bottom: 8px;
            font-weight: bold;
        }
        
        .info-row {
            margin-bottom: 5px;
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
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-draft {
            background-color: #f3f4f6;
            color: #6b7280;
        }
        
        .status-pending {
            background-color: #fef3c7;
            color: #92400e;
        }
        
        .status-replaced {
            background-color: #dbeafe;
            color: #1e40af;
        }
        
        .status-claimed {
            background-color: #d1fae5;
            color: #065f46;
        }
        
        .status-void {
            background-color: #fee2e2;
            color: #991b1b;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            font-size: 8px;
        }
        
        .items-table thead {
            background-color: #dc2626;
            color: white;
        }
        
        .items-table th {
            padding: 8px 5px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #991b1b;
        }
        
        .items-table td {
            padding: 6px 5px;
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
            font-size: 7px;
        }
        
        .sku-badge {
            display: inline-block;
            background-color: #e5e7eb;
            padding: 2px 5px;
            border-radius: 2px;
            font-size: 7px;
            color: #374151;
            margin-top: 2px;
        }
        
        .text-center {
            text-align: center;
        }
        
        .text-right {
            text-align: right;
        }
        
        .notes-section {
            clear: both;
            margin-top: 20px;
        }
        
        .notes-box {
            border: 1px solid #ddd;
            padding: 10px;
            background-color: #f9fafb;
            border-radius: 4px;
            margin-bottom: 10px;
        }
        
        .notes-box h3 {
            font-size: 10px;
            color: #991b1b;
            border-bottom: 1px solid #dc2626;
            padding-bottom: 4px;
            margin-bottom: 6px;
        }
        
        .notes-box p {
            font-size: 8px;
            color: #333;
            line-height: 1.5;
            white-space: pre-wrap;
        }
        
        .timeline-section {
            margin-top: 20px;
            border: 1px solid #ddd;
            padding: 10px;
            background-color: #f9fafb;
            border-radius: 4px;
        }
        
        .timeline-section h3 {
            font-size: 10px;
            color: #991b1b;
            border-bottom: 1px solid #dc2626;
            padding-bottom: 4px;
            margin-bottom: 10px;
        }
        
        .timeline-item {
            margin-bottom: 10px;
            padding-left: 15px;
            border-left: 2px solid #dc2626;
        }
        
        .timeline-item-header {
            font-weight: bold;
            color: #991b1b;
            font-size: 9px;
        }
        
        .timeline-item-meta {
            font-size: 7px;
            color: #666;
            margin: 2px 0;
        }
        
        .timeline-item-notes {
            font-size: 8px;
            color: #333;
            margin-top: 3px;
            line-height: 1.4;
        }
        
        .footer {
            margin-top: 30px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 7px;
            color: #666;
        }
        
        .footer p {
            margin: 3px 0;
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
            .notes-section {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-left">
            <h1>WARRANTY CLAIM</h1>
            <div class="subtitle">{{ $claim->claim_number ?? 'WC-' . ($claim->id ?? 'N/A') }}</div>
        </div>
        
        <div class="header-right">
            @if($logo ?? false)
                <img src="{{ $logo }}" alt="Company Logo" class="logo">
            @else
                <div class="logo-placeholder">
                    {{ $companyName ?? 'Company' }}
                </div>
            @endif
            <div class="company-info">
                @if($companyAddress ?? false)
                    <p>{{ $companyAddress }}</p>
                @endif
                @if($companyPhone ?? false)
                    <p>Phone: {{ $companyPhone }}</p>
                @endif
                @if($companyEmail ?? false)
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
                    <span class="info-value">
                        @if($claim ?? false)
                            {{ $claim->customer?->business_name 
                                ?? ($claim->customer?->first_name . ' ' . $claim->customer?->last_name)
                                ?? $claim->customer?->email 
                                ?? 'N/A' }}
                        @else
                            N/A
                        @endif
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email:</span>
                    <span class="info-value">{{ $claim->customer?->email ?? 'N/A' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Phone:</span>
                    <span class="info-value">{{ $claim->customer?->phone ?? 'N/A' }}</span>
                </div>
                @if($claim->invoice ?? false)
                    <div class="info-row">
                        <span class="info-label">Invoice #:</span>
                        <span class="info-value">{{ $claim->invoice->invoice_number ?? 'N/A' }}</span>
                    </div>
                @endif
            </div>
        </div>
        
        <div class="info-right">
            <div class="info-box">
                <h3>Claim Details</h3>
                <div class="info-row">
                    <span class="info-label">Status:</span>
                    <span class="info-value">
                        @if($claim->status ?? false)
                            <span class="status-badge status-{{ str_replace('_', '-', $claim->status->value) }}">
                                {{ $claim->status->getLabel() }}
                            </span>
                        @else
                            <span class="status-badge status-draft">Draft</span>
                        @endif
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Warehouse:</span>
                    <span class="info-value">{{ $claim->warehouse?->warehouse_name ?? 'N/A' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Created:</span>
                    <span class="info-value">{{ $claim->created_at?->format('d/m/Y H:i') ?? 'N/A' }}</span>
                </div>
                @if($claim->resolution_date ?? false)
                    <div class="info-row">
                        <span class="info-label">Resolved:</span>
                        <span class="info-value">{{ $claim->resolution_date->format('d/m/Y') }}</span>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Claimed Items -->
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 50%;">Item Description</th>
                <th style="width: 15%;" class="text-center">SKU</th>
                <th style="width: 10%;" class="text-center">Qty</th>
                <th style="width: 25%;">Issue Description</th>
            </tr>
        </thead>
        <tbody>
            @forelse($claim->items ?? [] as $item)
                <tr>
                    <td>
                        <div class="item-name">{{ $item->product_name }}</div>
                        @if($item->brand_name)
                            <div class="item-description">{{ $item->brand_name }}</div>
                        @endif
                    </td>
                    <td class="text-center">
                        @if($item->sku)
                            <span class="sku-badge">{{ $item->sku }}</span>
                        @else
                            N/A
                        @endif
                    </td>
                    <td class="text-center">{{ $item->quantity }}</td>
                    <td>
                        <span style="font-size: 7px; color: #666;">{{ $item->issue_description ?? 'No description provided' }}</span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="text-center" style="padding: 20px; color: #666;">
                        No items found for this warranty claim.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Customer Issue Description -->
    @if($claim->issue_description ?? false)
        <div class="notes-section">
            <div class="notes-box">
                <h3>Issue Description</h3>
                <p>{{ $claim->issue_description }}</p>
            </div>
        </div>
    @endif

    <!-- Customer Notes -->
    @if($claim->customer_notes ?? false)
        <div class="notes-section">
            <div class="notes-box">
                <h3>Customer Notes</h3>
                <p>{{ $claim->customer_notes }}</p>
            </div>
        </div>
    @endif

    <!-- Internal Notes -->
    @if($claim->internal_notes ?? false)
        <div class="notes-section">
            <div class="notes-box">
                <h3>Internal Notes</h3>
                <p>{{ $claim->internal_notes }}</p>
            </div>
        </div>
    @endif

    <!-- Photo/Video Links -->
    @if($claim->photo_video_links ?? false)
        <div class="notes-section">
            <div class="notes-box">
                <h3>Photo/Video Links</h3>
                <p>{{ $claim->photo_video_links }}</p>
            </div>
        </div>
    @endif

    <!-- Activity History -->
    @if(($includeHistory ?? true) && ($claim->histories ?? false) && $claim->histories->count() > 0)
        <div class="timeline-section">
            <h3>Activity History</h3>
            @foreach($claim->histories->sortByDesc('created_at') as $history)
                <div class="timeline-item">
                    <div class="timeline-item-header">
                        {{ $history->action_type->getLabel() }}
                        <span style="font-size:10px;color:#666;">({{ $history->action_type->value }})</span>
                    </div>
                    <div class="timeline-item-meta">
                        {{ $history->description }}
                    </div>
                    <div class="timeline-item-meta">
                        By: {{ $history->user?->name ?? 'System' }} |
                        {{ $history->created_at->format('d/m/Y H:i') }}
                    </div>
                    @if(!empty($history->metadata['url']))
                        <div class="timeline-item-notes">Link: {{ $history->metadata['url'] }}</div>
                    @endif
                    @if(!empty($history->metadata['notes']))
                        <div class="timeline-item-notes">{{ $history->metadata['notes'] }}</div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    <div class="footer">
        <p>This document was generated electronically and is valid without signature.</p>
        @if($companyEmail)
            <p>For questions regarding this warranty claim, please contact us at {{ $companyEmail }}</p>
        @endif
        <p>&copy; {{ date('Y') }} {{ $companyName ?? 'Company' }}. All rights reserved.</p>
    </div>
</body>
</html>
