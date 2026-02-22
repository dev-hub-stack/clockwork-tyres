@php
    use App\Modules\Settings\Models\CompanyBranding;
    
    // Fetch business details from active company branding
    $branding = CompanyBranding::getActive();
    
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
                {{ $record->quote_status?->label() ?? 'N/A' }}
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
                        <div class="text-sm font-medium text-gray-900">{{ $item->product_name }}</div>
                        @if($item->sku)
                            <div class="text-xs text-gray-500">SKU: {{ $item->sku }}</div>
                        @endif
                        @if($item->variant_snapshot)
                            <div class="text-xs text-gray-600 mt-1">
                                @if(isset($item->variant_snapshot['size']))
                                    Size: {{ $item->variant_snapshot['size'] }}
                                @endif
                                @if(isset($item->variant_snapshot['bolt_pattern']))
                                    | Bolt Pattern: {{ $item->variant_snapshot['bolt_pattern'] }}
                                @endif
                                @if(isset($item->variant_snapshot['offset']))
                                    | Offset: {{ $item->variant_snapshot['offset'] }}
                                @endif
                            </div>
                        @endif
                        @if($item->tax_inclusive && $item->tax_amount > 0)
                            <div class="text-xs text-gray-500 mt-1">
                                VAT on Sales ({{ number_format(($item->tax_amount / ($item->unit_price * $item->quantity - $item->discount)) * 100, 0) }}%)
                            </div>
                        @endif
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
            
            @if($record->shipping > 0)
            <div class="flex justify-between text-sm">
                <span class="text-gray-600">Shipping:</span>
                <span class="font-medium">{{ Number::currency($record->shipping, $record->currency ?? 'AED') }}</span>
            </div>
            @endif
            
            @if($record->vat > 0)
            <div class="flex justify-between text-sm">
                <span class="text-gray-600">VAT:</span>
                <span class="font-medium">{{ Number::currency($record->vat, $record->currency ?? 'AED') }}</span>
            </div>
            @endif
            
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
