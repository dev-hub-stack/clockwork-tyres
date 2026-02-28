@php
    use Illuminate\Support\Number;
    $branding = App\Modules\Settings\Models\CompanyBranding::getActive();
@endphp

<div class="space-y-6 p-6">
    {{-- Header with Logo and Invoice Badge --}}
    <div class="flex justify-between items-start">
        <div>
            @if($branding && $branding->logo_url)
                <img src="{{ $branding->logo_url }}" alt="Logo" class="h-16 w-auto mb-4">
            @else
                <div class="text-2xl font-bold text-gray-900">{{ $branding->company_name ?? 'Company Name' }}</div>
            @endif
            
            <div class="text-sm text-gray-600 mt-2">
                @if($branding)
                    {{ $branding->company_address }}<br>
                    {{ $branding->company_phone }}<br>
                    {{ $branding->company_email }}<br>
                    @if($branding->tax_registration_number)
                        <strong>TRN:</strong> {{ $branding->tax_registration_number }}
                    @endif
                @endif
            </div>
        </div>
        
        <div class="text-right">
            <div class="flex gap-2 justify-end mb-2">
                <x-filament::badge :color="match($record->payment_status->value) {
                    'pending' => 'warning',
                    'partial' => 'info',
                    'paid' => 'success',
                    'refunded' => 'secondary',
                    'failed' => 'danger',
                    default => 'secondary'
                }">
                    {{ $record->payment_status->label() }}
                </x-filament::badge>
                
                <x-filament::badge :color="match($record->order_status->value) {
                    'pending' => 'warning',
                    'processing' => 'info',
                    'shipped' => 'primary',
                    'completed' => 'success',
                    'cancelled' => 'danger',
                    default => 'secondary'
                }">
                    {{ $record->order_status->label() }}
                </x-filament::badge>
            </div>
            
            <div class="text-xs text-gray-500">
                Invoice #: <strong>{{ $record->order_number }}</strong>
            </div>
        </div>
    </div>

    <hr class="border-gray-200">

    {{-- Invoice Details --}}
    <div class="grid grid-cols-2 gap-6">
        <div>
            <h3 class="text-sm font-semibold text-gray-700 mb-2">Bill To:</h3>
            <div class="text-sm text-gray-600">
                <strong>{{ $record->customer->name }}</strong><br>
                @if($record->customer->email)
                    {{ $record->customer->email }}<br>
                @endif
                @if($record->customer->phone)
                    {{ $record->customer->phone }}<br>
                @endif
                @if($record->customer->address)
                    {{ $record->customer->address }}
                @endif
            </div>
        </div>
        
        <div class="text-right">
            <div class="text-sm space-y-1">
                <div><span class="text-gray-600">Issue Date:</span> <strong>{{ $record->issue_date->format('M d, Y') }}</strong></div>
                <div><span class="text-gray-600">Due Date:</span> <strong>{{ $record->valid_until->format('M d, Y') }}</strong></div>
                @if($record->warehouse)
                    <div><span class="text-gray-600">Warehouse:</span> <strong>{{ $record->warehouse->name }}</strong></div>
                @endif
            </div>
        </div>
    </div>

    <hr class="border-gray-200">

    {{-- Line Items Table --}}
    <div>
        <h3 class="text-sm font-semibold text-gray-700 mb-3">Items</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="border-b-2 border-gray-300 bg-gray-50">
                    <tr>
                        <th class="text-left py-2 px-3">#</th>
                        <th class="text-left py-2 px-3">Item / Description</th>
                        <th class="text-right py-2 px-3">Qty</th>
                        <th class="text-right py-2 px-3">Price</th>
                        <th class="text-right py-2 px-3">Discount</th>
                        <th class="text-right py-2 px-3">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($record->items as $item)
                    <tr class="border-b border-gray-200">
                        <td class="py-2 px-3">{{ $loop->iteration }}</td>
                        <td class="py-2 px-3">
                            <strong>{{ $item->product_name }}</strong><br>
                            <span class="text-xs text-gray-500">SKU: {{ $item->sku }}</span>
                        </td>
                        <td class="text-right py-2 px-3">{{ $item->quantity }}</td>
                        <td class="text-right py-2 px-3">{{ Number::currency($item->unit_price, 'AED') }}</td>
                        <td class="text-right py-2 px-3">{{ Number::currency($item->discount ?? 0, 'AED') }}</td>
                        <td class="text-right py-2 px-3 font-semibold">{{ Number::currency($item->line_total, 'AED') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <hr class="border-gray-200">

    {{-- Totals Section --}}
    <div class="flex justify-end">
        <div class="w-80 space-y-2 text-sm">
            <div class="flex justify-between">
                <span class="text-gray-600">Subtotal:</span>
                <span class="font-medium">{{ Number::currency($record->sub_total, 'AED') }}</span>
            </div>
            @if($record->discount > 0)
            <div class="flex justify-between">
                <span class="text-gray-600">Discount:</span>
                <span class="font-medium text-red-600">-{{ Number::currency($record->discount, 'AED') }}</span>
            </div>
            @endif
            @if($record->vat > 0)
            <div class="flex justify-between">
                <span class="text-gray-600">VAT (5%):</span>
                <span class="font-medium">{{ Number::currency($record->vat, 'AED') }}</span>
            </div>
            @endif
            <div class="flex justify-between">
                <span class="text-gray-600">Shipping:</span>
                <span class="font-medium">{{ Number::currency($record->shipping ?? 0, 'AED') }}</span>
            </div>
            <hr class="border-gray-300">
            <div class="flex justify-between text-lg font-bold">
                <span>Total:</span>
                <span>{{ Number::currency($record->total, 'AED') }}</span>
            </div>
            @if($record->paid_amount > 0)
            <div class="flex justify-between text-green-600">
                <span>Paid:</span>
                <span class="font-semibold">-{{ Number::currency($record->paid_amount, 'AED') }}</span>
            </div>
            @endif
            @if($record->outstanding_amount > 0)
            <div class="flex justify-between text-red-600 text-lg font-bold">
                <span>Balance Due:</span>
                <span>{{ Number::currency($record->outstanding_amount, 'AED') }}</span>
            </div>
            @endif
        </div>
    </div>

    {{-- Payment History --}}
    @if($record->payments->count() > 0)
    <hr class="border-gray-200">
    <div>
        <h3 class="text-sm font-semibold text-gray-700 mb-3">Payment History</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="border-b-2 border-gray-300 bg-gray-50">
                    <tr>
                        <th class="text-left py-2 px-3">Payment #</th>
                        <th class="text-left py-2 px-3">Date</th>
                        <th class="text-left py-2 px-3">Method</th>
                        <th class="text-left py-2 px-3">Reference</th>
                        <th class="text-right py-2 px-3">Amount</th>
                        <th class="text-center py-2 px-3">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($record->payments as $payment)
                    <tr class="border-b border-gray-200">
                        <td class="py-2 px-3 font-mono text-xs">{{ $payment->payment_number }}</td>
                        <td class="py-2 px-3">{{ $payment->payment_date->format('M d, Y') }}</td>
                        <td class="py-2 px-3">{{ ucfirst(str_replace('_', ' ', $payment->payment_method)) }}</td>
                        <td class="py-2 px-3">{{ $payment->reference_number ?? '-' }}</td>
                        <td class="text-right py-2 px-3 font-semibold">{{ Number::currency($payment->amount, 'AED') }}</td>
                        <td class="text-center py-2 px-3">
                            <x-filament::badge :color="$payment->status === 'completed' ? 'success' : 'warning'">
                                {{ ucfirst($payment->status) }}
                            </x-filament::badge>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Expenses --}}
    @if($record->expenses->count() > 0)
    <hr class="border-gray-200">
    <div>
        <h3 class="text-sm font-semibold text-gray-700 mb-3">Expenses</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="border-b-2 border-gray-300 bg-gray-50">
                    <tr>
                        <th class="text-left py-2 px-3">Expense #</th>
                        <th class="text-left py-2 px-3">Date</th>
                        <th class="text-left py-2 px-3">Type</th>
                        <th class="text-left py-2 px-3">Vendor</th>
                        <th class="text-right py-2 px-3">Amount</th>
                        <th class="text-center py-2 px-3">Payment Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($record->expenses as $expense)
                    <tr class="border-b border-gray-200">
                        <td class="py-2 px-3 font-mono text-xs">{{ $expense->expense_number }}</td>
                        <td class="py-2 px-3">{{ $expense->expense_date->format('M d, Y') }}</td>
                        <td class="py-2 px-3">{{ $expense->getExpenseTypeLabel() }}</td>
                        <td class="py-2 px-3">{{ $expense->vendor_name ?? '-' }}</td>
                        <td class="text-right py-2 px-3 font-semibold">{{ Number::currency($expense->amount, 'AED') }}</td>
                        <td class="text-center py-2 px-3">
                            <x-filament::badge :color="$expense->payment_status === 'paid' ? 'success' : 'warning'">
                                {{ ucfirst($expense->payment_status) }}
                            </x-filament::badge>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot class="border-t-2 border-gray-300 bg-gray-50">
                    <tr>
                        <td colspan="4" class="py-2 px-3 text-right font-semibold">Total Expenses:</td>
                        <td class="text-right py-2 px-3 font-bold">{{ Number::currency($record->total_expenses, 'AED') }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    @endif

    {{-- Shipping Tracking --}}
    @if($record->tracking_number)
    <hr class="border-gray-200">
    <div>
        <h3 class="text-sm font-semibold text-gray-700 mb-3">Shipping Information</h3>
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <span class="text-gray-600">Tracking Number:</span>
                    <strong class="ml-2">{{ $record->tracking_number }}</strong>
                </div>
                <div>
                    <span class="text-gray-600">Carrier:</span>
                    <strong class="ml-2">{{ $record->shipping_carrier }}</strong>
                </div>
                @if($record->shipped_at)
                <div>
                    <span class="text-gray-600">Shipped:</span>
                    <strong class="ml-2">{{ $record->shipped_at->format('M d, Y h:i A') }}</strong>
                </div>
                @endif
                @if($record->tracking_url)
                <div>
                    <a href="{{ $record->tracking_url }}" target="_blank" class="text-blue-600 hover:underline">
                        Track Shipment →
                    </a>
                </div>
                @endif
            </div>
        </div>
    </div>
    @endif

    {{-- Notes --}}
    @if($record->order_notes || $record->internal_notes)
    <hr class="border-gray-200">
    <div class="grid grid-cols-2 gap-6">
        @if($record->order_notes)
        <div>
            <h3 class="text-sm font-semibold text-gray-700 mb-2">Customer Notes</h3>
            <div class="text-sm text-gray-600 bg-gray-50 p-3 rounded">
                {{ $record->order_notes }}
            </div>
        </div>
        @endif
        
        @if($record->internal_notes)
        <div>
            <h3 class="text-sm font-semibold text-gray-700 mb-2">Internal Notes</h3>
            <div class="text-sm text-gray-600 bg-yellow-50 p-3 rounded">
                {{ $record->internal_notes }}
            </div>
        </div>
        @endif
    </div>
    @endif

    {{-- Footer --}}
    @if($branding && $branding->invoice_footer)
    <hr class="border-gray-200">
    <div class="text-center text-xs text-gray-500">
        {{ $branding->invoice_footer }}
    </div>
    @endif
</div>
