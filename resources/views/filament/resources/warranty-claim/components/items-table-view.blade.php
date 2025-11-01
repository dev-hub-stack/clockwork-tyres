@php
    $record = $this->record;
@endphp

<div class="overflow-x-auto">
    <table class="w-full text-sm border-collapse">
        <thead>
            <tr class="bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
                <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Product</th>
                <th class="px-4 py-3 text-center font-semibold text-gray-700 dark:text-gray-300">Quantity</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Issue</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Resolution</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            @forelse($record->items as $item)
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                    <td class="px-4 py-3">
                        <div class="flex flex-col">
                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ $item->productVariant->sku ?? 'N/A' }}</span>
                            <span class="text-xs text-gray-600 dark:text-gray-400">
                                {{ $item->productVariant->product->brand?->name ?? '' }} 
                                {{ $item->productVariant->product->model?->name ?? '' }}
                            </span>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-primary-100 dark:bg-primary-900 text-primary-700 dark:text-primary-300 font-semibold text-sm">
                            {{ $item->quantity }}
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        <p class="text-gray-700 dark:text-gray-300 text-sm">{{ $item->issue_description }}</p>
                    </td>
                    <td class="px-4 py-3">
                        <x-filament::badge :color="$item->resolution_action->getColor()">
                            {{ $item->resolution_action->getLabel() }}
                        </x-filament::badge>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                        No items found
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
    
    @if($record->items->count() > 0)
        <div class="mt-4 px-4 py-2 bg-gray-50 dark:bg-gray-900 rounded-lg">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                Total Items: {{ $record->items->sum('quantity') }}
            </span>
        </div>
    @endif
</div>
