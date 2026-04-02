<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="max-w-3xl">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-gray-500">Retailer Admin</p>
                    <h1 class="mt-2 text-3xl font-semibold tracking-tight text-gray-950">Procurement Checkout</h1>
                    <p class="mt-3 text-sm leading-6 text-gray-600">
                        Build one admin-side procurement cart across approved suppliers, keep each supplier grouped, and place the full order in one action.
                        The backend will split the checkout into separate supplier requests, quotes, and invoices.
                    </p>
                </div>

                <div class="grid w-full gap-3 sm:grid-cols-2 lg:w-[28rem]">
                    <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Current business account</p>
                        <p class="mt-2 text-lg font-semibold text-gray-950">{{ $currentAccountSummary['account']['name'] ?? 'No active retail account' }}</p>
                        <p class="mt-1 text-xs text-gray-500">Only retailer-enabled accounts can place procurement orders.</p>
                    </div>
                    <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Approved suppliers</p>
                        <p class="mt-2 text-lg font-semibold text-gray-950">{{ $checkoutSummary['approved_suppliers'] ?? 0 }}</p>
                        <p class="mt-1 text-xs text-gray-500">These supplier connections are eligible for grouped admin checkout.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
            <section class="space-y-6">
                @forelse ($supplierCatalogSections as $section)
                    <div class="rounded-3xl border border-gray-200 bg-white shadow-sm">
                        <div class="flex flex-col gap-4 border-b border-gray-100 px-6 py-5 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-gray-500">Approved Supplier</p>
                                <h2 class="mt-1 text-2xl font-semibold text-gray-950">{{ $section['supplier_name'] }}</h2>
                                <p class="mt-2 max-w-3xl text-sm leading-6 text-gray-600">{{ $section['supplier_summary'] }}</p>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-3">
                                <div class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 text-center">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Available lines</p>
                                    <p class="mt-2 text-lg font-semibold text-gray-950">{{ $section['offer_count'] }}</p>
                                </div>
                                <div class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 text-center">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Selected qty</p>
                                    <p class="mt-2 text-lg font-semibold text-gray-950">{{ $section['selected_quantity_total'] }}</p>
                                </div>
                                <div class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 text-center">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Supplier subtotal</p>
                                    <p class="mt-2 text-lg font-semibold text-gray-950">AED {{ number_format($section['selected_subtotal'], 2) }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="divide-y divide-gray-100">
                            @forelse ($section['offers'] as $offer)
                                <div class="grid gap-6 px-6 py-5 lg:grid-cols-[10rem_minmax(0,1fr)_16rem]">
                                    <div class="flex items-center justify-center rounded-2xl border border-gray-200 bg-gray-50 p-4">
                                        @if (! empty($offer['image_url']))
                                            <img src="{{ $offer['image_url'] }}" alt="{{ $offer['brand_name'] }} {{ $offer['model_name'] }}" class="max-h-36 w-full object-contain" />
                                        @else
                                            <div class="flex h-36 w-full items-center justify-center rounded-2xl bg-white text-center text-sm font-semibold uppercase tracking-[0.2em] text-gray-400">
                                                {{ $offer['brand_name'] }}
                                            </div>
                                        @endif
                                    </div>

                                    <div class="space-y-3">
                                        <div>
                                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-indigo-500">{{ $offer['brand_name'] }}</p>
                                            <h3 class="mt-1 text-2xl font-semibold text-gray-950">{{ $offer['model_name'] }}</h3>
                                            <p class="mt-2 text-sm text-gray-600">
                                                {{ $offer['summary'] !== '' ? $offer['summary'] : 'Supplier catalogue line ready for procurement.' }}
                                            </p>
                                        </div>

                                        <dl class="grid gap-x-8 gap-y-2 text-sm sm:grid-cols-2">
                                            <div class="flex justify-between gap-4 border-b border-dashed border-gray-200 py-1">
                                                <dt class="font-medium text-gray-500">SKU</dt>
                                                <dd class="font-semibold text-gray-900">{{ $offer['sku'] }}</dd>
                                            </div>
                                            <div class="flex justify-between gap-4 border-b border-dashed border-gray-200 py-1">
                                                <dt class="font-medium text-gray-500">Size</dt>
                                                <dd class="font-semibold text-gray-900">{{ $offer['full_size'] }}</dd>
                                            </div>
                                            <div class="flex justify-between gap-4 border-b border-dashed border-gray-200 py-1">
                                                <dt class="font-medium text-gray-500">DOT / Year</dt>
                                                <dd class="font-semibold text-gray-900">{{ $offer['dot_year'] ?? 'N/A' }}</dd>
                                            </div>
                                            <div class="flex justify-between gap-4 border-b border-dashed border-gray-200 py-1">
                                                <dt class="font-medium text-gray-500">Origin</dt>
                                                <dd class="font-semibold text-gray-900">{{ $offer['country'] ?? 'N/A' }}</dd>
                                            </div>
                                            <div class="flex justify-between gap-4 border-b border-dashed border-gray-200 py-1 sm:col-span-2">
                                                <dt class="font-medium text-gray-500">Available</dt>
                                                <dd class="font-semibold text-gray-900">{{ $offer['available_quantity'] }} in stock</dd>
                                            </div>
                                        </dl>
                                    </div>

                                    <div class="flex flex-col justify-between gap-4 rounded-2xl border border-gray-200 bg-gray-50 p-4">
                                        <div class="space-y-2 text-right">
                                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-gray-500">Wholesale price</p>
                                            <p class="text-3xl font-semibold tracking-tight text-gray-950">AED {{ number_format($offer['unit_price'], 2) }}</p>
                                            <p class="text-xs text-gray-500">{{ $offer['warehouse_name'] ? 'Warehouse: ' . $offer['warehouse_name'] : 'Warehouse assigned on supplier side' }}</p>
                                        </div>

                                        <div class="space-y-3">
                                            <div class="rounded-2xl border border-gray-300 bg-white p-3">
                                                <div class="flex items-center justify-between gap-2">
                                                    <button
                                                        type="button"
                                                        wire:click="decrementQuantity({{ $offer['offer_id'] }})"
                                                        class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-gray-200 bg-gray-50 text-xl font-semibold text-gray-700 transition hover:bg-gray-100"
                                                    >
                                                        -
                                                    </button>

                                                    <input
                                                        type="number"
                                                        min="0"
                                                        max="{{ max(0, $offer['available_quantity']) }}"
                                                        wire:model.live="selectedQuantities.{{ $offer['offer_id'] }}"
                                                        class="h-10 w-20 rounded-xl border border-gray-200 text-center text-sm font-semibold text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                    />

                                                    <button
                                                        type="button"
                                                        wire:click="incrementQuantity({{ $offer['offer_id'] }})"
                                                        class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-gray-200 bg-gray-50 text-xl font-semibold text-gray-700 transition hover:bg-gray-100"
                                                    >
                                                        +
                                                    </button>
                                                </div>
                                            </div>

                                            <div class="flex items-center justify-between text-sm">
                                                <span class="font-medium text-gray-500">Line total</span>
                                                <span class="font-semibold text-gray-950">AED {{ number_format($offer['line_total'], 2) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="px-6 py-8 text-sm leading-6 text-gray-600">
                                    No supplier tyre offers with live stock are available yet for this connection.
                                </div>
                            @endforelse
                        </div>
                    </div>
                @empty
                    <div class="rounded-3xl border border-dashed border-gray-300 bg-white p-8 text-sm leading-6 text-gray-600 shadow-sm">
                        No approved supplier connections are ready for procurement checkout yet. Connect suppliers first, then their stocked tyre offers will appear here.
                    </div>
                @endforelse
            </section>

            <aside class="space-y-6">
                <div class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-gray-500">Checkout summary</p>
                    <h2 class="mt-2 text-2xl font-semibold text-gray-950">One submit action, split per supplier</h2>

                    <dl class="mt-5 space-y-4">
                        <div class="flex items-center justify-between">
                            <dt class="text-sm text-gray-500">Suppliers in cart</dt>
                            <dd class="text-lg font-semibold text-gray-950">{{ $checkoutSummary['selected_suppliers'] ?? 0 }}</dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-sm text-gray-500">Selected lines</dt>
                            <dd class="text-lg font-semibold text-gray-950">{{ $checkoutSummary['selected_lines'] ?? 0 }}</dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-sm text-gray-500">Quantity total</dt>
                            <dd class="text-lg font-semibold text-gray-950">{{ $checkoutSummary['quantity_total'] ?? 0 }}</dd>
                        </div>
                        <div class="border-t border-dashed border-gray-200 pt-4">
                            <div class="flex items-center justify-between">
                                <dt class="text-sm font-medium text-gray-500">Subtotal</dt>
                                <dd class="text-2xl font-semibold tracking-tight text-gray-950">AED {{ number_format($checkoutSummary['subtotal'] ?? 0, 2) }}</dd>
                            </div>
                        </div>
                    </dl>

                    <p class="mt-5 text-sm leading-6 text-gray-600">{{ $checkoutSummary['supporting_note'] ?? '' }}</p>

                    <button
                        type="button"
                        wire:click="submitGroupedProcurement"
                        wire:loading.attr="disabled"
                        wire:target="submitGroupedProcurement"
                        @disabled(($checkoutSummary['selected_lines'] ?? 0) < 1)
                        class="mt-6 inline-flex w-full items-center justify-center rounded-2xl bg-gray-950 px-4 py-3 text-sm font-semibold text-white transition hover:bg-gray-800 disabled:cursor-not-allowed disabled:bg-gray-300"
                    >
                        <span wire:loading.remove wire:target="submitGroupedProcurement">{{ $checkoutSummary['action_label'] ?? 'Place Order' }}</span>
                        <span wire:loading wire:target="submitGroupedProcurement">Submitting...</span>
                    </button>

                    @if (! empty($latestSubmissionSummary))
                        <div class="mt-5 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
                            <p class="font-semibold">{{ $latestSubmissionSummary['submission_number'] ?? 'Latest submission' }}</p>
                            <p class="mt-1">
                                Created {{ $latestSubmissionSummary['request_count'] ?? 0 }} supplier request(s)
                                across {{ $latestSubmissionSummary['supplier_count'] ?? 0 }} supplier section(s).
                            </p>
                            <p class="mt-1 text-xs text-emerald-700">Submitted at {{ $latestSubmissionSummary['submitted_at'] ?? 'Pending timestamp' }}</p>
                        </div>
                    @endif
                </div>

                <div class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-gray-500">Recent procurement activity</p>
                    <h2 class="mt-2 text-xl font-semibold text-gray-950">Quotes, orders, and invoices</h2>

                    <div class="mt-5 space-y-3">
                        @forelse ($recentProcurementSignals as $signal)
                            <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <p class="text-sm font-semibold text-gray-950">{{ $signal['document_number'] }}</p>
                                        <p class="mt-1 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $signal['document_type_label'] }} · {{ $signal['status_label'] }}</p>
                                    </div>
                                    <span class="text-xs text-gray-500">{{ $signal['occurred_at'] ?? 'Pending date' }}</span>
                                </div>
                                <p class="mt-3 text-sm text-gray-700">{{ $signal['signal_summary'] }}</p>
                            </div>
                        @empty
                            <div class="rounded-2xl border border-dashed border-gray-300 bg-gray-50 p-4 text-sm leading-6 text-gray-600">
                                No quote, order, or invoice activity is tied to this retailer account yet.
                            </div>
                        @endforelse
                    </div>
                </div>
            </aside>
        </div>
    </div>
</x-filament-panels::page>
