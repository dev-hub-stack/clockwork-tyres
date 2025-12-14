<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $documentType === 'quote' ? 'Quote' : 'Invoice' }} | {{ $documentType === 'quote' ? ($record->quote_number ?? 'N/A') : ($record->order_number ?? 'N/A') }}</title>
    <style>
        @page { margin: 0px; }
        body { 
            margin: 0; 
            padding: 30px; 
            font-family: 'Helvetica', 'Arial', sans-serif; 
            font-size: 10px; 
            color: #333; 
            line-height: 1.4; 
            background-color: white; 
        }
        .container { 
            width: 100%; 
            margin: 0 auto; 
        }
        
        /* Table-based layout for PDF compatibility */
        table.layout-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; border: none; }
        table.layout-table td { border: none; vertical-align: top; padding: 0; }
        
        .header-table { margin-bottom: 15px; }
        .header-table td { vertical-align: top; }
        
        .logo { max-width: 200px; max-height: 70px; }
        
        h1 { font-size: 24px; font-weight: bold; margin: 0; color: #333; }
        
        .company-section { border-bottom: 2px solid #333; padding-bottom: 15px; margin-bottom: 20px; }
        
        .info-table { margin-bottom: 20px; }
        .info-table td { padding-right: 10px; width: 50%; }
        .info-box h4 { font-weight: bold; margin-bottom: 5px; font-size: 11px; color: #333; }
        .info-box p { margin: 0; font-size: 10px; line-height: 1.3; color: #555; }
        
        .vehicle-section { background-color: #f8f9fa; padding: 10px; margin-bottom: 20px; border-left: 4px solid #007bff; }
        .vehicle-section h4 { font-weight: bold; margin-bottom: 8px; font-size: 12px; }
        .vehicle-table td { padding: 5px 10px 5px 0; width: 25%; }
        .vehicle-item strong { display: block; font-size: 9px; color: #666; margin-bottom: 2px; }
        .vehicle-item span { font-size: 10px; font-weight: 600; }
        
        /* Data Tables */
        table.data-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; table-layout: fixed; }
        table.data-table thead { background-color: #f8f9fa; }
        table.data-table th { padding: 8px 5px; border: 1px solid #ddd; text-align: left; font-weight: bold; font-size: 10px; overflow: hidden; }
        table.data-table td { padding: 8px 5px; border: 1px solid #ddd; font-size: 10px; vertical-align: top; overflow: hidden; word-wrap: break-word; }
        
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        
        /* Totals Section */
        .totals-table { margin-top: 20px; }
        .totals-table td.notes-cell { width: 60%; padding-right: 20px; }
        .totals-table td.totals-cell { width: 40%; }
        
        .totals-calc-table { width: 100%; }
        .totals-calc-table td { border-bottom: 1px solid #eee; padding: 6px 0; font-size: 10px; }
        .totals-calc-table .total-row td { border-top: 2px solid #333; font-weight: bold; font-size: 12px; color: #000; padding: 8px 0; }
        
        .footer { margin-top: 40px; text-align: center; font-size: 9px; color: #999; border-top: 1px solid #eee; padding-top: 15px; }
        .small-text { font-size: 9px; color: #666; }
        .brand-name { color: #007bff; font-weight: 600; }
        
        /* Helper for images in tables */
        .product-img-container { width: 40px; height: 40px; border: 1px solid #ddd; border-radius: 3px; overflow: hidden; }
        .product-img { width: 100%; height: 100%; object-fit: cover; }
        .no-image { width: 100%; height: 100%; background: #f0f0f0; display: flex; align-items: center; justify-content: center; font-size: 8px; color: #999; text-align: center; }
        
        /* Print/Download buttons - hide on print */
        .action-buttons { position: fixed; top: 10px; right: 10px; z-index: 1000; }
        .action-buttons button { padding: 8px 16px; margin-left: 5px; cursor: pointer; border: 1px solid #ddd; border-radius: 4px; background: #fff; font-size: 12px; }
        .action-buttons button:hover { background: #f0f0f0; }
        @media print {
            .action-buttons { display: none !important; }
        }
    </style>
</head>
<body>
    <!-- Action Buttons -->
    @if((!isset($isPdf) || !$isPdf) && !request()->has('pdf'))
    <div class="action-buttons">
        <button onclick="window.print()">🖨️ Print</button>
        @if($documentType === 'quote')
            <button onclick="window.open('{{ route('quote.pdf', $record->id) }}', '_blank'); event.stopPropagation();" style="cursor: pointer;">📥 Download PDF</button>
        @elseif($documentType === 'invoice')
            <button onclick="window.open('{{ route('orders.invoice', $record->id) }}?pdf=1', '_blank'); event.stopPropagation();" style="cursor: pointer;">📥 Download PDF</button>
        @else
             <button onclick="downloadPdf()">📥 Download PDF</button>
        @endif
    </div>
    @endif
    
    <div class="container">
        <!-- Header with Logo on Right -->
        <table class="layout-table header-table">
            <tr>
                <td style="width: 60%;">
                    <h1>{{ $documentType === 'quote' ? 'QUOTE' : ($documentType === 'delivery_note' ? 'DELIVERY NOTE' : 'INVOICE') }}</h1>
                    <p style="margin-top: 5px; font-size: 14px; font-weight: 600; color: #333;">{{ $documentType === 'quote' ? ($record->quote_number ?? 'N/A') : ($record->order_number ?? 'N/A') }}</p>
                </td>
                <td style="width: 40%; text-align: right;">
                    @if(!empty($logo))
                        <img src="{{ $logo }}" alt="Company Logo" class="logo" style="float: right;">
                    @else
                        <div class="logo-placeholder" style="float: right;">LOGO</div>
                    @endif
                    <div style="clear: both;"></div>
                    
                    <!-- Company Details (below logo) -->
                    <div style="margin-top: 10px; text-align: right;">
                        <p style="margin: 0; font-size: 11px;"><strong>{{ $companyName }}</strong></p>
                        <p style="margin: 2px 0; font-size: 9px; color: #555;">{!! nl2br(e($companyAddress)) !!}</p>
                        <p style="margin: 2px 0; font-size: 9px; color: #555;">Phone: {{ $companyPhone }} | Email: {{ $companyEmail }}</p>
                        <p style="margin: 2px 0; font-size: 9px; color: #555;">Tax No: {{ $taxNumber }}</p>
                    </div>
                </td>
            </tr>
        </table>
        
        <!-- Customer Info and Dates -->
        <table class="layout-table info-table">
            <tr>
                <!-- Customer -->
                <td>
                    <div class="info-box">
                        <h4>Customer:</h4>
                        <p><strong>{{ $record->customer->name ?? 'N/A' }}</strong></p>
                        <p>Phone: {{ $record->customer->phone ?? 'N/A' }}</p>
                        <p>Email: {{ $record->customer->email ?? 'N/A' }}</p>
                    </div>
                </td>
                
                <!-- Dates & Status -->
                <td>
                    <div class="info-box">
                        @if($documentType === 'quote')
                            <h4>Quote Date:</h4>
                            <p>{{ $record->created_at->format('m/d/Y') }}</p>
                            
                            <h4 style="margin-top: 10px;">Valid Until:</h4>
                            <p>{{ $record->valid_until ? \Carbon\Carbon::parse($record->valid_until)->format('m/d/Y') : 'N/A' }}</p>
                        @else
                            <h4>Invoice Date:</h4>
                            <p>{{ $record->created_at->format('m/d/Y') }}</p>
                            
                            <h4 style="margin-top: 10px;">Due Date:</h4>
                            <p>{{ $record->due_date ? \Carbon\Carbon::parse($record->due_date)->format('m/d/Y') : \Carbon\Carbon::parse($record->created_at)->addDays(30)->format('m/d/Y') }}</p>
                        @endif
                        
                        <h4 style="margin-top: 10px;">Status:</h4>
                        <p style="text-transform: uppercase;">{{ $record->status ?? 'N/A' }}</p>
                    </div>
                </td>
            </tr>
        </table>

        <!-- Vehicle Information -->
        @if($record->vehicle_year || $record->vehicle_make || $record->vehicle_model || $record->vehicle_sub_model)
        <div class="vehicle-section">
            <h4>Vehicle Information</h4>
            <table class="vehicle-table" style="width: 100%;">
                <tr>
                    <td>
                        <div class="vehicle-item">
                            <strong>Year:</strong>
                            <span>{{ $record->vehicle_year ?? '-' }}</span>
                        </div>
                    </td>
                    <td>
                        <div class="vehicle-item">
                            <strong>Make:</strong>
                            <span>{{ $record->vehicle_make ?? '-' }}</span>
                        </div>
                    </td>
                    <td>
                        <div class="vehicle-item">
                            <strong>Model:</strong>
                            <span>{{ $record->vehicle_model ?? '-' }}</span>
                        </div>
                    </td>
                    <td>
                        <div class="vehicle-item">
                            <strong>Sub Model:</strong>
                            <span>{{ $record->vehicle_sub_model ?? '-' }}</span>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
        @endif

        <!-- Line Items Table -->
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: {{ $documentType === 'delivery_note' ? '85%' : '55%' }};">Description</th>
                    <th style="width: {{ $documentType === 'delivery_note' ? '10%' : '8%' }};" class="text-center">Qty</th>
                    @if($documentType !== 'delivery_note')
                        <th style="width: 16%;" class="text-right">Unit Price</th>
                        <th style="width: 16%;" class="text-right">Total</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach($record->items as $index => $item)
                    <tr>
                        <td class="text-center">{{ $index + 1 }}</td>
                        <td>
                            <table style="width: 100%; border: none;">
                                <tr>
                                    <td style="width: 50px; border: none; padding: 0;">
                                        {{-- Product Image --}}
                                        @php
                                            $variantData = is_string($item->variant_snapshot) ? json_decode($item->variant_snapshot, true) : $item->variant_snapshot;
                                            $productImage = $variantData['image'] ?? null;
                                            if ($productImage && !str_starts_with($productImage, 'http')) {
                                                $productImage = 'https://d3oet5ce3rdmse.cloudfront.net/' . ltrim($productImage, '/');
                                            }
                                        @endphp
                                        <div class="product-img-container">
                                            @if($productImage)
                                                <img src="{{ $productImage }}" alt="Product" class="product-img">
                                            @else
                                                <div class="no-image">No<br>Img</div>
                                            @endif
                                        </div>
                                    </td>
                                    <td style="border: none; padding: 0 0 0 10px; vertical-align: top;">
                                        <strong>{{ $item->product_name ?? 'Unknown Product' }}</strong>
                                        @if($item->sku)
                                            <br><span class="small-text">SKU: {{ $item->sku }}</span>
                                        @endif
                                        @if($item->brand_name)
                                            <br><span class="small-text">Brand: <span class="brand-name">{{ $item->brand_name }}</span></span>
                                        @endif
                                        
                                        @php
                                            $hasWarehouse = !empty($item->warehouse) || !empty($item->warehouse_id);
                                            $isNonStock = empty($item->warehouse_id) && empty($item->consignment_item_id);
                                        @endphp
                                        @if($hasWarehouse && $item->warehouse)
                                            <br><small style="color: #666; font-size: 9px; display: inline-block; margin-top: 2px;">
                                                📦 {{ $item->warehouse->warehouse_name ?? $item->warehouse->name }}
                                            </small>
                                        @elseif(!empty($item->consignment_item_id))
                                            <br><small style="color: #666; font-size: 9px; display: inline-block; margin-top: 2px;">
                                                🤝 Consignment
                                            </small>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </td>
                        <td class="text-center">{{ $item->quantity }}</td>
                        @if($documentType !== 'delivery_note')
                            <td class="text-right">
                                {{ $currency->symbol ?? 'AED' }} {{ number_format($item->unit_price, 2) }}
                            </td>
                            <td class="text-right">
                                {{ $currency->symbol ?? 'AED' }} {{ number_format($item->line_total, 2) }}
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>

        <!-- Totals & Notes -->
        <table class="layout-table totals-table">
            <tr>
                <!-- Notes -->
                <td class="notes-cell" style="vertical-align: top;">
                    @if($documentType === 'delivery_note')
                        @if($record->order_notes)
                            <div style="margin-bottom: 20px;">
                                <h4 style="font-size: 11px; margin-bottom: 5px;">Order Notes</h4>
                                <p style="font-size: 10px; color: #555; background: #f9f9f9; padding: 10px; border: 1px solid #eee; border-radius: 4px;">
                                    {{ $record->order_notes }}
                                </p>
                            </div>
                        @endif
                    @endif

                    @if($record->notes)
                        <div style="margin-bottom: 15px;">
                            <h4 style="font-size: 11px; margin-bottom: 5px;">Customer Notes</h4>
                            <p style="font-size: 10px; color: #555;">{{ $record->notes }}</p>
                        </div>
                    @endif
                    
                    @if($record->internal_notes)
                        <div>
                            <h4 style="font-size: 11px; margin-bottom: 5px;">Internal Notes</h4>
                            <p style="font-size: 10px; color: #555;">{{ $record->internal_notes }}</p>
                        </div>
                    @endif
                </td>
                
                <!-- Totals -->
                @if($documentType !== 'delivery_note')
                <td class="totals-cell" style="vertical-align: top;">
                    <table class="totals-calc-table">
                        <tr>
                            <td>Subtotal:</td>
                            <td class="text-right">{{ $currency->symbol ?? 'AED' }} {{ number_format($record->sub_total, 2) }}</td>
                        </tr>
                        @if($record->discount > 0)
                        <tr>
                            <td>Discount:</td>
                            <td class="text-right">-{{ $currency->symbol ?? 'AED' }} {{ number_format($record->discount, 2) }}</td>
                        </tr>
                        @endif
                        @if($record->shipping > 0)
                        <tr>
                            <td>Shipping:</td>
                            <td class="text-right">{{ $currency->symbol ?? 'AED' }} {{ number_format($record->shipping, 2) }}</td>
                        </tr>
                        @endif
                        <tr>
                            <td>VAT ({{ $vatRate }}%):</td>
                            <td class="text-right">{{ $currency->symbol ?? 'AED' }} {{ number_format($record->vat, 2) }}</td>
                        </tr>
                        <tr class="total-row">
                            <td>Total:</td>
                            <td class="text-right">{{ $currency->symbol ?? 'AED' }} {{ number_format($record->total, 2) }}</td>
                        </tr>
                    </table>
                </td>
                @endif
            </tr>
        </table>

        <!-- Footer -->
        <div class="footer">
            <p>Thank you for your business!</p>
            <p>Contact: {{ $companyEmail }} | Phone: {{ $companyPhone }}</p>
        </div>
    </div>
    
    @if(!isset($isPdf) || !$isPdf)
    <script>
        // Define function in global scope
        window.downloadPdf = function() {
            // Get current URL and add pdf=1 parameter
            var url = new URL(window.location.href);
            url.searchParams.set('pdf', '1');
            
            // Open in new tab to prevent closing the modal
            window.open(url.toString(), '_blank');
        };
    </script>
    @endif
</body>
</html>
