<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 text-emerald-950">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700">Supplier admin</p>
            <h1 class="mt-1 text-2xl font-semibold">Supplier Intake Workbench</h1>
            <p class="mt-2 text-sm leading-6 text-emerald-900">
                George described this as the supplier-side intake flow for procurement requests. Incoming orders land under Quotes &amp; Proformas, the supplier reviews them, and approved quotes convert to invoices using the same reporting CRM behavior.
            </p>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-xl border border-emerald-100 bg-white/70 p-3">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-emerald-700">Current account</p>
                    <p class="mt-1 text-sm font-semibold text-emerald-950">{{ $currentAccountSummary['name'] ?? 'No supplier account selected' }}</p>
                    <p class="text-xs text-emerald-700">{{ $currentAccountSummary['type'] ?? 'Supplier' }}</p>
                </div>
                <div class="rounded-xl border border-emerald-100 bg-white/70 p-3">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-emerald-700">Retailer links</p>
                    <p class="mt-1 text-2xl font-semibold text-emerald-950">{{ $currentAccountSummary['retailer_connections'] ?? 0 }}</p>
                    <p class="text-xs text-emerald-700">Approved accounts feeding this inbox.</p>
                </div>
                <div class="rounded-xl border border-emerald-100 bg-white/70 p-3">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-emerald-700">Quotes in inbox</p>
                    <p class="mt-1 text-2xl font-semibold text-emerald-950">{{ $currentAccountSummary['open_quotes'] ?? 0 }}</p>
                    <p class="text-xs text-emerald-700">Live quote requests from connected retailers.</p>
                </div>
                <div class="rounded-xl border border-emerald-100 bg-white/70 p-3">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-emerald-700">Latest signal</p>
                    <p class="mt-1 text-sm font-semibold text-emerald-950">{{ $currentAccountSummary['latest_signal'] ?? 'No live requests' }}</p>
                    <p class="text-xs text-emerald-700">Quote approval converts to invoice.</p>
                </div>
            </div>
        </div>

        @if (! empty($signalCards))
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                @foreach ($signalCards as $card)
                    <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $card['label'] ?? 'Signal' }}</p>
                        <p class="mt-2 text-3xl font-semibold text-gray-950">{{ $card['value'] ?? 0 }}</p>
                        <p class="mt-2 text-sm leading-6 text-gray-600">{{ $card['note'] ?? '' }}</p>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-[280px_minmax(0,1fr)]">
            <aside class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Supplier stages</p>
                        <h2 class="text-lg font-semibold text-gray-900">Intake workflow</h2>
                    </div>
                    <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">Quotes &amp; Proformas</span>
                </div>

                <div class="mt-4 space-y-3">
                    @foreach ($statusRail as $stage)
                        <div class="flex gap-3 rounded-xl border border-gray-100 p-3 {{ $stage['state'] === 'active' ? 'bg-emerald-50 ring-1 ring-emerald-200' : 'bg-gray-50' }}">
                            <div class="mt-1 h-3 w-3 rounded-full {{ $stage['state'] === 'active' ? 'bg-emerald-600' : 'bg-gray-300' }}"></div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="text-sm font-semibold text-gray-900">{{ $stage['label'] }}</p>
                                    @if(($stage['count'] ?? 0) > 0)
                                        <span class="rounded-full bg-white px-2 py-0.5 text-[11px] font-semibold text-gray-700 shadow-sm">{{ $stage['count'] }}</span>
                                    @endif
                                </div>
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
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Incoming queue</p>
                            <h2 class="text-lg font-semibold text-gray-900">Supplier procurement inbox</h2>
                        </div>
                        <div class="rounded-full bg-amber-50 px-3 py-1 text-xs font-medium text-amber-700">
                            Quotes &amp; Proformas inbox
                        </div>
                    </div>

                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Request #</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Type</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Retailer</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Account</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">SKU</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Size</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Qty</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Reference</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Status</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Note</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                @forelse ($incomingRequests as $row)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $row['request_number'] ?? '-' }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-700">{{ $row['document_type'] ?? '-' }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-700">{{ $row['retailer'] ?? '-' }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-700">{{ $row['account'] ?? '-' }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-700">{{ $row['sku'] ?? '-' }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-700">{{ $row['size'] ?? '-' }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-700">{{ $row['quantity'] ?? 0 }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-700">{{ $row['reference'] ?? '-' }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-700">{{ $row['status'] ?? '-' }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-600">{{ $row['note'] ?? '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="10" class="px-4 py-10 text-center text-sm text-gray-500">
                                            No procurement requests are loaded yet.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
                    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                        <div class="flex items-center justify-between border-b border-gray-100 pb-4">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Workflow notes</p>
                                <h3 class="text-lg font-semibold text-gray-900">How George described supplier intake</h3>
                            </div>
                        </div>

                        <div class="mt-4 space-y-3">
                            @foreach ($workflowNotes as $note)
                                <div class="rounded-xl border border-dashed border-gray-200 bg-gray-50 p-4">
                                    <p class="text-sm font-semibold text-gray-900">{{ $note['title'] ?? 'Note' }}</p>
                                    <p class="mt-1 text-xs leading-5 text-gray-600">{{ $note['copy'] ?? '' }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="space-y-6">
                        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Action checklist</p>
                            <h3 class="mt-1 text-lg font-semibold text-gray-900">Supplier-side response flow</h3>

                            <ul class="mt-4 list-disc space-y-2 pl-5 text-sm leading-6 text-gray-700">
                                @foreach ($actionChecklist as $item)
                                    <li>{{ $item }}</li>
                                @endforeach
                            </ul>
                        </div>

                        <div class="rounded-2xl border border-gray-200 bg-emerald-50 p-5 shadow-sm">
                            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Preview mode</p>
                            <h3 class="mt-1 text-lg font-semibold text-gray-900">Same storefront, read-only</h3>
                            <p class="mt-2 text-sm leading-6 text-gray-700">
                                Supplier accounts keep the shared storefront preview mode so they can search by vehicle or size and inspect their products, but cart and checkout stay disabled.
                            </p>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</x-filament-panels::page>
