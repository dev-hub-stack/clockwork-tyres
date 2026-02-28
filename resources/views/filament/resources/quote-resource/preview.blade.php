@php
    use App\Modules\Settings\Models\CompanyBranding;
    use App\Modules\Settings\Models\TaxSetting;
    use Illuminate\Support\Number;

    $branding    = CompanyBranding::getActive();
    $taxSetting  = TaxSetting::getDefault();
    $taxRate     = $taxSetting ? floatval($taxSetting->rate) : 5;
    $taxName     = $taxSetting ? $taxSetting->name : 'VAT';

    // Fetch business details from active company branding
    
    if ($branding) {
        $companyName = $branding->company_name;
        $companyAddress = $branding->company_address;
        $companyPhone = $branding->company_phone;
        $companyEmail = $branding->company_email;
        $companyWebsite = $branding->company_website;
        $taxRegistration = $branding->tax_registration_number;
        $companyLogo = $branding->logo_url; // URL to logo from storage
    } else {
        // Fallback values if no branding is set
        $companyName = 'TunerStop Tyres & Acc. Trading L.L.C';
        $companyAddress = 'Warehouse 3, No. 36, Street 4B, Ras Al Khor Industrial 2';
        $companyPhone = null;
        $companyEmail = null;
        $companyWebsite = null;
        $taxRegistration = '100479491100003';
        $companyLogo = null;
    }
@endphp

<div class="space-y-6 p-6">
    {{-- Header with Logo and Company Details --}}
    <div class="flex justify-between items-start border-b pb-4">
        <div class="flex-1">
            @if($companyLogo)
                <img src="{{ $companyLogo }}" alt="{{ $companyName }}" class="h-16 mb-4">
            @else
                <div class="text-2xl font-bold text-gray-900 mb-4">
                    {{ $companyName }}
                </div>
            @endif
            
            <div class="text-sm text-gray-600 space-y-1">
                <p>{{ $companyAddress }}</p>
                @if($companyPhone)
                    <p>Phone: {{ $companyPhone }}</p>
                @endif
                @if($companyEmail)
                    <p>Email: {{ $companyEmail }}</p>
                @endif
                @if($companyWebsite)
                    <p>Website: {{ $companyWebsite }}</p>
                @endif
                <p class="font-medium mt-2">Tax Registration Number: {{ $taxRegistration }}</p>
            </div>
        </div>
        
        <div class="text-right">
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Proforma Invoice</h2>
            
            <x-filament::badge size="lg" :color="$record->quote_status?->color() ?? 'gray'">
                {{ $record->quote_status?->label() ?? 'Draft' }}
            </x-filament::badge>
            
            <div class="mt-4 space-y-1 text-sm">
                <div class="flex justify-between gap-8">
                    <span class="text-gray-600">Quote number:</span>
                    <span class="font-medium">{{ $record->quote_number }}</span>
                </div>
                <div class="flex justify-between gap-8">
                    <span class="text-gray-600">Date:</span>
                    <span class="font-medium">{{ $record->issue_date->format('Y-m-d') }}</span>
                </div>
                @if($record->valid_until)
                <div class="flex justify-between gap-8">
                    <span class="text-gray-600">Valid Until:</span>
                    <span class="font-medium">{{ $record->valid_until->format('Y-m-d') }}</span>
                </div>
                @endif
            </div>
        </div>
    </div>
    
    {{-- Customer Details --}}
    <div class="grid grid-cols-2 gap-8">
        <div>
            <h3 class="text-sm font-semibold text-gray-700 mb-2">Customer</h3>
            <div class="text-sm space-y-1">
                <p class="font-medium text-gray-900">{{ $record->customer->name }}</p>
                @if($record->customer->phone)
                    <p class="text-gray-600">{{ $record->customer->phone }}</p>
                @endif
                @if($record->customer->email)
                    <p class="text-gray-600">{{ $record->customer->email }}</p>
                @endif
                @if($record->customer->tax_registration_number)
                    <p class="text-gray-600">Tax Registration Number: {{ $record->customer->tax_registration_number }}</p>
                @endif
            </div>
        </div>
        
        @if($record->warehouse)
        <div>
            <h3 class="text-sm font-semibold text-gray-700 mb-2">From</h3>
            <div class="text-sm space-y-1">
                <p class="font-medium text-gray-900">{{ $record->warehouse->name }}</p>
                @if($record->warehouse->address)
                    <p class="text-gray-600">{{ $record->warehouse->address }}</p>
                @endif
            </div>
        </div>
        @endif
    </div>
    
    {{-- Line Items --}}
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item / Description</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Qty</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @foreach($record->items as $index => $item)
                <tr>
                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">
                        {{ $index + 1 }}
                    </td>
                    <td class="px-4 py-4">
                        @php $snap = $item->variant_snapshot ?? []; @endphp
                        <div class="flex items-start gap-3">
                            @php
                                $imageUrl = $snap['image'] ?? null;
                                // Handle relative storage paths
                                if ($imageUrl && !str_starts_with($imageUrl, 'http')) {
                                    $imageUrl = \Illuminate\Support\Facades\Storage::url($imageUrl);
                                }
                            @endphp
                            @if($imageUrl)
                                <img src="{{ $imageUrl }}" alt="{{ $item->product_name }}"
                                     style="width:56px;height:56px;object-fit:cover;border-radius:4px;border:1px solid #e5e7eb;flex-shrink:0;">
                            @else
                                <div style="width:56px;height:56px;background:#f3f4f6;border:1px solid #e5e7eb;border-radius:4px;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:9px;color:#9ca3af;text-align:center;">
                                    No<br>Img
                                </div>
                            @endif
                            <div>
                                <div class="text-sm font-medium text-gray-900">{{ $item->product_name }}</div>
                                @if($item->brand_name)
                                    <div class="text-xs text-gray-500">Brand: {{ $item->brand_name }}</div>
                                @endif
                                @if($item->sku)
                                    <div class="text-xs text-gray-500">SKU: {{ $item->sku }}</div>
                                @endif
                                @if(!empty($snap['finish']) || !empty($snap['size']) || !empty($snap['bolt_pattern']) || !empty($snap['offset']))
                                    <div class="text-xs text-gray-600 mt-1">
                                        @if(!empty($snap['finish']))Finish: {{ $snap['finish'] }}@endif
                                        @if(!empty($snap['size'])) | Size: {{ $snap['size'] }}@endif
                                        @if(!empty($snap['bolt_pattern'])) | Bolt: {{ $snap['bolt_pattern'] }}@endif
                                        @if(!empty($snap['offset'])) | Offset: {{ $snap['offset'] }}@endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                        {{ $item->quantity }}
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                        {{ Number::currency($item->unit_price, $record->currency ?? 'AED') }}
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900 text-right">
                        {{ Number::currency($item->line_total, $record->currency ?? 'AED') }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    
    {{-- Totals --}}
    <div class="flex justify-end">
        <div class="w-80 space-y-2">
            <div class="flex justify-between text-sm">
                <span class="text-gray-600">Subtotal:</span>
                <span class="font-medium">{{ Number::currency($record->sub_total, $record->currency ?? 'AED') }}</span>
            </div>
            
            @if($record->discount > 0)
            <div class="flex justify-between text-sm">
                <span class="text-gray-600">Discount:</span>
                <span class="font-medium text-red-600">-{{ Number::currency($record->discount, $record->currency ?? 'AED') }}</span>
            </div>
            @endif
            
            <div class="flex justify-between text-sm">
                <span class="text-gray-600">Shipping:</span>
                <span class="font-medium">{{ Number::currency($record->shipping ?? 0, $record->currency ?? 'AED') }}</span>
            </div>
            
            @php
                // Calculate VAT on-the-fly if DB value is 0 (handles old records)
                $displayVat = floatval($record->vat);
                if ($displayVat == 0 && floatval($record->total) > 0 && floatval($record->sub_total) > 0) {
                    $displayVat = round(floatval($record->total) - floatval($record->sub_total), 2);
                }
                if ($displayVat == 0 && floatval($record->sub_total) > 0) {
                    $displayVat = round(floatval($record->sub_total) * ($taxRate / 100), 2);
                }
            @endphp
            <div class="flex justify-between text-sm">
                <span class="text-gray-600">{{ $taxName }} ({{ number_format($taxRate, 0) }}%):</span>
                <span class="font-medium">{{ Number::currency($displayVat, $record->currency ?? 'AED') }}</span>
            </div>
            
            <div class="border-t pt-2 flex justify-between text-base font-bold">
                <span class="text-gray-900">Total:</span>
                <span class="text-gray-900">{{ Number::currency($record->total, $record->currency ?? 'AED') }}</span>
            </div>
        </div>
    </div>
    
    {{-- Notes --}}
    @if($record->order_notes)
    <div class="border-t pt-4">
        <h3 class="text-sm font-semibold text-gray-700 mb-2">Notes</h3>
        <p class="text-sm text-gray-600 whitespace-pre-wrap">{{ $record->order_notes }}</p>
    </div>
    @endif
</div>
