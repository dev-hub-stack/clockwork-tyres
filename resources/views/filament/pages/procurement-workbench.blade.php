<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-2xl border border-sky-200 bg-sky-50 p-5 text-sky-950">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-sky-600">Retailer admin</p>
            <h1 class="mt-1 text-2xl font-semibold">Procurement Workbench</h1>
            <p class="mt-2 text-sm leading-6 text-sky-900">
                This is a backend workbench for building procurement requests. It stays separate from storefront checkout and follows George's grouped-by-supplier admin checkout rule: one active retail account, multiple supplier sections, one submit action.
            </p>
            <div class="mt-4 flex flex-wrap gap-2 text-xs font-medium">
                <span class="rounded-full bg-white px-3 py-1 text-sky-700">
                    Current account: {{ $currentAccountSummary['account']['name'] ?? 'No active retail account' }}
                </span>
                <span class="rounded-full bg-white px-3 py-1 text-sky-700">
                    Approved supplier groups: {{ $plannedSubmission['supplier_count'] ?? 0 }}
                </span>
                <span class="rounded-full bg-white px-3 py-1 text-sky-700">
                    Submit action: {{ $placeOrderCallout['action_label'] ?? 'Place Order' }}
                </span>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            @foreach ($requestSummary as $summary)
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $summary['label'] }}</p>
                    <p class="mt-3 text-2xl font-semibold text-gray-900">{{ $summary['value'] }}</p>
                    <p class="mt-2 text-sm leading-6 text-gray-600">{{ $summary['note'] }}</p>
                </div>
            @endforeach
        </div>

        <div class="grid gap-6 lg:grid-cols-[280px_minmax(0,1fr)]">
            <aside class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Status rail</p>
                        <h2 class="text-lg font-semibold text-gray-900">Procurement stages</h2>
                    </div>
                    <span class="rounded-full bg-sky-100 px-3 py-1 text-xs font-semibold text-sky-700">Request cart</span>
                </div>

                <div class="mt-4 space-y-3">
                    @foreach ($statusRail as $stage)
                        <div class="flex gap-3 rounded-xl border border-gray-100 p-3 {{ $stage['state'] === 'active' ? 'bg-sky-50 ring-1 ring-sky-200' : 'bg-gray-50' }}">
                            <div class="mt-1 h-3 w-3 rounded-full {{ $stage['state'] === 'active' ? 'bg-sky-600' : 'bg-gray-300' }}"></div>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-900">{{ $stage['label'] }}</p>
                                <p class="mt-1 text-xs leading-5 text-gray-600">{{ $stage['description'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </aside>

            <section class="space-y-6">
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-col gap-2 border-b border-gray-100 pb-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Grouped supplier cart</p>
                            <h2 class="text-lg font-semibold text-gray-900">One workbench, supplier-separated sections</h2>
                        </div>
                        <div class="rounded-full bg-amber-50 px-3 py-1 text-xs font-medium text-amber-700">
                            One submit action fans out per supplier
                        </div>
                    </div>

                    <div class="mt-4 space-y-4">
                        @forelse ($supplierGroups as $group)
                            <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
                                <div class="flex flex-col gap-3 border-b border-gray-200 pb-4 md:flex-row md:items-center md:justify-between">
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $group['supplier_reference'] }}</p>
                                        <h3 class="text-lg font-semibold text-gray-900">{{ $group['supplier_name'] }}</h3>
                                        <p class="mt-1 text-sm leading-6 text-gray-600">{{ $group['summary'] }}</p>
                                    </div>
                                    <span class="rounded-full bg-white px-3 py-1 text-xs font-medium text-gray-500">{{ $group['status'] }}</span>
                                </div>

                                <div class="mt-4 overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-white">
                                            <tr>
                                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">SKU</th>
                                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Product</th>
                                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Size</th>
                                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Qty</th>
                                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Source</th>
                                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Status</th>
                                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Note</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 bg-white">
                                            @foreach ($group['items'] as $row)
                                                <tr class="hover:bg-gray-50">
                                                    <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $row['sku'] }}</td>
                                                    <td class="px-4 py-3 text-sm text-gray-700">{{ $row['product_name'] }}</td>
                                                    <td class="px-4 py-3 text-sm text-gray-700">{{ $row['size'] }}</td>
                                                    <td class="px-4 py-3 text-sm text-gray-700">{{ $row['quantity'] }}</td>
                                                    <td class="px-4 py-3 text-sm text-gray-700">{{ $row['source'] }}</td>
                                                    <td class="px-4 py-3 text-sm text-gray-700">{{ $row['status'] }}</td>
                                                    <td class="px-4 py-3 text-sm text-gray-600">{{ $row['note'] }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-2xl border border-dashed border-gray-300 bg-white p-6 text-sm leading-6 text-gray-600">
                                No approved suppliers are linked to {{ $currentAccountSummary['account']['name'] ?? 'the active retailer account' }} yet. George's grouped-by-supplier checkout only opens once the account has approved supplier connections.
                            </div>
                        @endforelse
                    </div>
                </div>

                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-col gap-2 border-b border-gray-100 pb-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Recent procurement signals</p>
                            <h2 class="text-lg font-semibold text-gray-900">Live quote, order, and invoice activity</h2>
                        </div>
                        <div class="rounded-full bg-sky-50 px-3 py-1 text-xs font-medium text-sky-700">
                            Account-aware CRM history
                        </div>
                    </div>

                    <div class="mt-4 space-y-3">
                        @forelse ($recentProcurementSignals as $signal)
                            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                                <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900">{{ $signal['document_number'] }}</p>
                                        <p class="mt-1 text-xs font-medium uppercase tracking-wide text-gray-500">
                                            {{ $signal['document_type_label'] }} · {{ $signal['status_label'] }}
                                        </p>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        {{ $signal['occurred_at'] ?? 'Pending date' }}
                                    </div>
                                </div>
                                <p class="mt-3 text-sm text-gray-700">{{ $signal['signal_summary'] }}</p>
                                <p class="mt-1 text-xs leading-5 text-gray-600">{{ $signal['customer_name'] }} · {{ $signal['channel'] ?? 'Account activity' }}</p>
                            </div>
                        @empty
                            <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-4 text-sm leading-6 text-gray-600">
                                No quote, order, or invoice activity is tied to {{ $currentAccountSummary['account']['name'] ?? 'the active retailer account' }} yet.
                            </div>
                        @endforelse
                    </div>
                </div>

                <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
                    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                        <div class="flex items-center justify-between border-b border-gray-100 pb-4">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Place order</p>
                                <h3 class="text-lg font-semibold text-gray-900">{{ $placeOrderCallout['title'] }}</h3>
                            </div>
                        </div>

                        <p class="mt-4 text-sm leading-6 text-gray-700">{{ $placeOrderCallout['description'] }}</p>

                        <div class="mt-4 grid gap-3 md:grid-cols-3">
                            @foreach ($placeOrderCallout['highlights'] as $highlight)
                                <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm leading-6 text-gray-700">
                                    {{ $highlight }}
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-5 flex flex-wrap items-center gap-3">
                            <button
                                type="button"
                                wire:click="submitGroupedProcurement"
                                wire:loading.attr="disabled"
                                wire:target="submitGroupedProcurement"
                                @disabled(empty($plannedSubmission['supplier_orders']))
                                class="inline-flex items-center rounded-full bg-sky-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700 disabled:cursor-not-allowed disabled:bg-sky-300"
                            >
                                <span wire:loading.remove wire:target="submitGroupedProcurement">{{ $placeOrderCallout['action_label'] }}</span>
                                <span wire:loading wire:target="submitGroupedProcurement">Submitting...</span>
                            </button>
                            <span class="text-sm text-gray-600">{{ $placeOrderCallout['supporting_note'] }}</span>
                        </div>

                        @if (! empty($latestSubmissionSummary))
                            <div class="mt-4 rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-900">
                                <p class="font-semibold">{{ $latestSubmissionSummary['submission_number'] ?? 'Latest submission' }}</p>
                                <p class="mt-1">
                                    Created {{ $latestSubmissionSummary['request_count'] ?? 0 }} supplier request(s)
                                    across {{ $latestSubmissionSummary['supplier_count'] ?? 0 }} supplier group(s).
                                </p>
                                <p class="mt-1 text-xs text-sky-700">Submitted at {{ $latestSubmissionSummary['submitted_at'] ?? 'Pending timestamp' }}</p>
                            </div>
                        @endif
                    </div>

                    <div class="rounded-2xl border border-gray-200 bg-amber-50 p-5 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Workbench note</p>
                        <h3 class="mt-1 text-lg font-semibold text-gray-900">Grouped supplier sections, one retailer action</h3>
                        <p class="mt-2 text-sm leading-6 text-gray-700">
                            Retailers can work inside the active account and add requests from multiple approved suppliers into the same workbench. The UI keeps each supplier grouped and the backend splits the final submission into separate supplier-side orders, quotes, invoices, and stock movements.
                        </p>
                    </div>
                </div>
            </section>
        </div>
    </div>
</x-filament-panels::page>
