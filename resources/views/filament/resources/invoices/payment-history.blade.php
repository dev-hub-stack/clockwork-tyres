<div class="overflow-x-auto">
    <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
        <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
            <tr>
                <th scope="col" class="px-6 py-3">Date</th>
                <th scope="col" class="px-6 py-3">Amount</th>
                <th scope="col" class="px-6 py-3">Method</th>
                <th scope="col" class="px-6 py-3">Reference</th>
                <th scope="col" class="px-6 py-3">Recorded By</th>
                <th scope="col" class="px-6 py-3">Notes</th>
            </tr>
        </thead>
        <tbody>
            @forelse($payments as $payment)
                <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700">
                    <td class="px-6 py-4">
                        {{ $payment->payment_date ? $payment->payment_date->format('M d, Y') : '-' }}
                    </td>
                    <td class="px-6 py-4 font-medium text-gray-900 dark:text-white">
                        {{ number_format($payment->amount, 2) }}
                    </td>
                    <td class="px-6 py-4">
                        {{ ucfirst(str_replace('_', ' ', $payment->payment_method->value ?? $payment->payment_method)) }}
                    </td>
                    <td class="px-6 py-4">
                        {{ $payment->reference_number ?? '-' }}
                    </td>
                    <td class="px-6 py-4">
                        {{ $payment->recordedBy->name ?? 'System' }}
                    </td>
                    <td class="px-6 py-4">
                        {{ $payment->notes ?? '-' }}
                    </td>
                </tr>
            @empty
                <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700">
                    <td colspan="6" class="px-6 py-4 text-center">
                        No payments recorded.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
