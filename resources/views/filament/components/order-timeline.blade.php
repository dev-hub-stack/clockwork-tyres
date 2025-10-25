@php
    $isQuote = $record->document_type === 'quote';
    $isInvoice = $record->document_type === 'invoice';
    
    // Define timeline steps based on document type
    if ($isQuote) {
        $steps = [
            [
                'label' => 'Draft',
                'status' => 'draft',
                'date' => $record->created_at,
                'completed' => true,
                'icon' => 'heroicon-o-document-text',
            ],
            [
                'label' => 'Sent',
                'status' => 'sent',
                'date' => $record->sent_at,
                'completed' => in_array($record->quote_status?->value, ['sent', 'approved', 'rejected']),
                'icon' => 'heroicon-o-paper-airplane',
            ],
            [
                'label' => 'Approved',
                'status' => 'approved',
                'date' => $record->approved_at,
                'completed' => $record->quote_status?->value === 'approved',
                'icon' => 'heroicon-o-check-circle',
            ],
        ];
        
        // Add rejected if applicable
        if ($record->quote_status?->value === 'rejected') {
            $steps[] = [
                'label' => 'Rejected',
                'status' => 'rejected',
                'date' => $record->updated_at,
                'completed' => true,
                'icon' => 'heroicon-o-x-circle',
                'isRejected' => true,
            ];
        }
    } else {
        $steps = [
            [
                'label' => 'Created',
                'status' => 'pending',
                'date' => $record->created_at,
                'completed' => true,
                'icon' => 'heroicon-o-document-plus',
            ],
            [
                'label' => 'Processing',
                'status' => 'processing',
                'date' => $record->processing_started_at ?? null,
                'completed' => in_array($record->order_status?->value, ['processing', 'shipped', 'completed']),
                'icon' => 'heroicon-o-cog-6-tooth',
            ],
            [
                'label' => 'Shipped',
                'status' => 'shipped',
                'date' => $record->shipped_at,
                'completed' => in_array($record->order_status?->value, ['shipped', 'completed']),
                'icon' => 'heroicon-o-truck',
            ],
            [
                'label' => 'Completed',
                'status' => 'completed',
                'date' => $record->completed_at ?? null,
                'completed' => $record->order_status?->value === 'completed',
                'icon' => 'heroicon-o-check-badge',
            ],
        ];
        
        // Add cancelled if applicable
        if ($record->order_status?->value === 'cancelled') {
            $steps[] = [
                'label' => 'Cancelled',
                'status' => 'cancelled',
                'date' => $record->updated_at,
                'completed' => true,
                'icon' => 'heroicon-o-x-mark',
                'isCancelled' => true,
            ];
        }
    }
@endphp

<div class="p-6 bg-white dark:bg-gray-800 rounded-lg">
    <h3 class="text-lg font-semibold mb-6 text-gray-900 dark:text-gray-100">
        {{ $isQuote ? 'Quote' : 'Order' }} Timeline
    </h3>
    
    <div class="relative">
        <!-- Timeline Line -->
        <div class="absolute left-8 top-0 bottom-0 w-0.5 bg-gray-200 dark:bg-gray-700"></div>
        
        <!-- Timeline Steps -->
        <div class="space-y-6">
            @foreach($steps as $index => $step)
                <div class="relative flex items-start">
                    <!-- Icon -->
                    <div class="flex-shrink-0 w-16 h-16 rounded-full flex items-center justify-center z-10
                        {{ $step['completed'] 
                            ? (isset($step['isRejected']) ? 'bg-red-500' : (isset($step['isCancelled']) ? 'bg-gray-500' : 'bg-green-500'))
                            : 'bg-gray-300 dark:bg-gray-600' }}">
                        @if($step['completed'])
                            @if(isset($step['isRejected']) || isset($step['isCancelled']))
                                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            @else
                                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                            @endif
                        @else
                            <div class="w-4 h-4 rounded-full bg-white dark:bg-gray-800"></div>
                        @endif
                    </div>
                    
                    <!-- Content -->
                    <div class="ml-6 flex-1">
                        <div class="flex items-center justify-between">
                            <h4 class="text-base font-semibold
                                {{ $step['completed'] 
                                    ? (isset($step['isRejected']) ? 'text-red-700 dark:text-red-400' : (isset($step['isCancelled']) ? 'text-gray-700 dark:text-gray-400' : 'text-green-700 dark:text-green-400'))
                                    : 'text-gray-500 dark:text-gray-400' }}">
                                {{ $step['label'] }}
                            </h4>
                            
                            @if($step['date'])
                                <span class="text-sm text-gray-600 dark:text-gray-400">
                                    {{ $step['date']->format('M d, Y g:i A') }}
                                </span>
                            @endif
                        </div>
                        
                        @if(!$step['completed'] && $index > 0 && $steps[$index - 1]['completed'])
                            <p class="mt-1 text-sm text-blue-600 dark:text-blue-400">
                                ⏳ Next step in the process
                            </p>
                        @endif
                        
                        <!-- Additional Info -->
                        @if($step['status'] === 'shipped' && $step['completed'] && $record->tracking_number)
                            <div class="mt-2 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
                                <p class="text-sm text-blue-900 dark:text-blue-200">
                                    <strong>Tracking:</strong> {{ $record->tracking_number }}
                                    @if($record->shipping_carrier)
                                        via {{ $record->shipping_carrier }}
                                    @endif
                                </p>
                                @if($record->tracking_url)
                                    <a href="{{ $record->tracking_url }}" target="_blank" 
                                       class="text-sm text-blue-600 dark:text-blue-400 hover:underline inline-flex items-center mt-1">
                                        Track Shipment
                                        <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                        </svg>
                                    </a>
                                @endif
                            </div>
                        @endif
                        
                        @if($step['status'] === 'completed' && $step['completed'])
                            <div class="mt-2 p-3 bg-green-50 dark:bg-green-900/20 rounded-lg border border-green-200 dark:border-green-800">
                                <p class="text-sm text-green-900 dark:text-green-200">
                                    <strong>Payment Status:</strong> {{ ucfirst($record->payment_status?->value ?? 'Pending') }}
                                </p>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    
    <!-- Summary -->
    <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
        <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <p class="text-gray-600 dark:text-gray-400">Created</p>
                <p class="font-medium text-gray-900 dark:text-gray-100">
                    {{ $record->created_at->format('M d, Y') }}
                </p>
            </div>
            
            @if($isInvoice && $record->valid_until)
                <div>
                    <p class="text-gray-600 dark:text-gray-400">Due Date</p>
                    <p class="font-medium {{ $record->valid_until->isPast() && !$record->isFullyPaid() ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-gray-100' }}">
                        {{ $record->valid_until->format('M d, Y') }}
                        @if($record->valid_until->isPast() && !$record->isFullyPaid())
                            <span class="text-xs">(Overdue)</span>
                        @endif
                    </p>
                </div>
            @endif
            
            @if($isQuote && $record->valid_until)
                <div>
                    <p class="text-gray-600 dark:text-gray-400">Valid Until</p>
                    <p class="font-medium text-gray-900 dark:text-gray-100">
                        {{ $record->valid_until->format('M d, Y') }}
                    </p>
                </div>
            @endif
        </div>
    </div>
</div>
