<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-2xl border border-sky-200 bg-sky-50 p-5 text-sky-950">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-sky-600">Retailer admin</p>
            <h1 class="mt-1 text-2xl font-semibold">Procurement Workbench</h1>
            <p class="mt-2 text-sm leading-6 text-sky-900">
                This is a backend workbench for building procurement requests. It stays separate from storefront checkout and now reflects George's grouped supplier flow: one retailer workbench, multiple supplier sections, one submit action.
            </p>
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
                        @foreach ($supplierGroups as $group)
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
                        @endforeach
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
                            <span class="rounded-full bg-sky-100 px-3 py-1 text-xs font-semibold text-sky-700">{{ $placeOrderCallout['action_label'] }}</span>
                            <span class="text-sm text-gray-600">{{ $placeOrderCallout['supporting_note'] }}</span>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-gray-200 bg-amber-50 p-5 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Workbench note</p>
                        <h3 class="mt-1 text-lg font-semibold text-gray-900">Separate supplier orders, unified retailer action</h3>
                        <p class="mt-2 text-sm leading-6 text-gray-700">
                            Retailers can add tyres from multiple suppliers into the same workbench. The UI keeps each supplier grouped and the backend splits the final submission into separate supplier-side orders, quotes, invoices, and stock movements.
                        </p>
                    </div>
                </div>
            </section>
        </div>
    </div>
</x-filament-panels::page>
