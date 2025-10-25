<div class="p-4 bg-white dark:bg-gray-800 rounded-lg">
    <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">Stock Availability Check</h3>
    
    <div class="space-y-3">
        @foreach($stockInfo as $info)
            <div class="flex items-center justify-between p-3 rounded-lg border 
                {{ $info['warning'] ? 'bg-red-50 border-red-200 dark:bg-red-900/20 dark:border-red-800' : 'bg-green-50 border-green-200 dark:bg-green-900/20 dark:border-green-800' }}">
                <div class="flex-1">
                    <p class="font-medium text-gray-900 dark:text-gray-100">{{ $info['product'] }}</p>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Quantity Needed: {{ $info['quantity'] }}
                        @if(isset($info['warehouse']))
                            <span class="mx-2">•</span>
                            <span class="font-medium">{{ $info['warehouse'] }}</span>
                        @endif
                    </p>
                </div>
                
                <div class="text-right">
                    @if($info['status'] === 'Non-Stock')
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                            Non-Stock Item
                        </span>
                    @elseif($info['warning'])
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                            ⚠️ Only {{ $info['available'] }} Available
                        </span>
                    @else
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                            ✓ {{ $info['available'] }} Available
                        </span>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
    
    @if($stockInfo->contains('warning', true))
        <div class="mt-4 p-4 bg-yellow-50 border border-yellow-200 rounded-lg dark:bg-yellow-900/20 dark:border-yellow-800">
            <p class="text-sm text-yellow-800 dark:text-yellow-200">
                <strong>⚠️ Warning:</strong> Some items have insufficient stock. Processing will continue but you may need to order more inventory.
            </p>
        </div>
    @endif
    
    <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg dark:bg-blue-900/20 dark:border-blue-800">
        <p class="text-sm text-blue-800 dark:text-blue-200">
            <strong>ℹ️ Note:</strong> When you proceed, inventory will be allocated for stocked items. The order status will change to "Processing".
        </p>
    </div>
</div>
