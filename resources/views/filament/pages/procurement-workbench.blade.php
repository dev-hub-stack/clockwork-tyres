<x-filament-panels::page>
    <style>
        .procurement-shell {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .procurement-summary-grid {
            display: grid;
            gap: 0.875rem;
            grid-template-columns: repeat(1, minmax(0, 1fr));
        }

        .procurement-summary-card {
            border: 1px solid #e5e7eb;
            border-radius: 1.5rem;
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            padding: 1rem 1.125rem;
            min-height: 8.25rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.05);
        }

        .procurement-summary-card--account {
            background: linear-gradient(135deg, #f8fafc 0%, #eef2ff 100%);
        }

        .procurement-summary-label {
            font-size: 0.7rem;
            line-height: 1.15;
            font-weight: 700;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: #64748b;
        }

        .procurement-summary-value {
            margin-top: 0.85rem;
            font-size: 1.75rem;
            line-height: 1.05;
            font-weight: 700;
            letter-spacing: -0.03em;
            color: #0f172a;
        }

        .procurement-summary-note {
            margin-top: 0.4rem;
            font-size: 0.875rem;
            line-height: 1.5;
            color: #475569;
        }

        .procurement-result-shell {
            display: grid;
            gap: 1rem;
            align-items: center;
            grid-template-columns: minmax(0, 1fr);
        }

        .procurement-result-metrics {
            display: grid;
            gap: 0.75rem;
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .procurement-result-metric {
            border: 1px solid #e5e7eb;
            border-radius: 1.25rem;
            background: #f8fafc;
            padding: 1rem;
            min-height: 6.5rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            text-align: center;
        }

        .procurement-result-metric--price {
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
        }

        .procurement-result-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.6rem;
            margin-top: 1rem;
        }

        .procurement-result-chip {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            padding: 0.4rem 0.7rem;
            font-size: 0.8125rem;
            font-weight: 600;
            color: #475569;
        }

        .procurement-result-actions {
            display: flex;
            justify-content: flex-start;
        }

        @media (min-width: 768px) {
            .procurement-summary-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .procurement-summary-card--account {
                grid-column: span 2;
            }
        }

        @media (min-width: 1280px) {
            .procurement-summary-grid {
                grid-template-columns: minmax(0, 1.35fr) repeat(3, minmax(0, 0.82fr));
            }

            .procurement-summary-card--account {
                grid-column: span 1;
            }

            .procurement-result-shell {
                grid-template-columns: minmax(0, 1fr) 24rem auto;
            }

            .procurement-result-actions {
                justify-content: flex-end;
            }
        }
    </style>

    @php
        $supplierOptions = collect($supplierConnectionGroups)
            ->filter(fn (array $group): bool => ($group['connection_status'] ?? null) === 'approved')
            ->map(fn (array $group): array => [
                'id' => (int) ($group['supplier_id'] ?? 0),
                'name' => (string) ($group['supplier_name'] ?? 'Supplier'),
            ])
            ->filter(fn (array $group): bool => $group['id'] > 0)
            ->values();

        $cartSections = collect($supplierCatalogSections)
            ->map(function (array $section): array {
                $offers = collect($section['offers'] ?? [])
                    ->filter(fn (array $offer): bool => (int) ($offer['selected_quantity'] ?? 0) > 0)
                    ->values()
                    ->all();

                return array_merge($section, [
                    'offers' => $offers,
                    'selected_line_count' => count($offers),
                    'selected_quantity_total' => collect($offers)->sum('selected_quantity'),
                    'selected_subtotal' => round((float) collect($offers)->sum('line_total'), 2),
                ]);
            })
            ->filter(fn (array $section): bool => ! empty($section['offers']))
            ->values();

        $historyRows = $activeView === 'pending' ? $pendingOrderHistory : $orderHistory;
    @endphp

    <div class="procurement-shell">
        <div class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
            <div class="max-w-3xl">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-gray-500">Retailer Admin</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight text-gray-950">Procurement</h1>
                <p class="mt-3 text-sm leading-6 text-gray-600">
                    Search approved supplier stock, compare supplier offers, build one grouped cart, and place one admin-side order that the backend
                    splits into separate supplier requests.
                </p>
            </div>

            <div class="mt-6 procurement-summary-grid">
                <div class="procurement-summary-card procurement-summary-card--account">
                    <div>
                        <p class="procurement-summary-label">Current business account</p>
                        <p class="procurement-summary-value">{{ $currentAccountSummary['account']['name'] ?? 'No active retail account' }}</p>
                    </div>
                    <p class="procurement-summary-note">Ship-to warehouse, procurement history, and supplier eligibility stay scoped to this active retailer account.</p>
                </div>

                <div class="procurement-summary-card">
                    <p class="procurement-summary-label">Approved suppliers</p>
                    <p class="procurement-summary-value">{{ $checkoutSummary['approved_suppliers'] ?? 0 }}</p>
                    <p class="procurement-summary-note">Connected suppliers available for admin-side ordering.</p>
                </div>

                <div class="procurement-summary-card">
                    <p class="procurement-summary-label">Selected lines</p>
                    <p class="procurement-summary-value">{{ $checkoutSummary['selected_lines'] ?? 0 }}</p>
                    <p class="procurement-summary-note">Grouped request lines currently sitting in the shared cart.</p>
                </div>

                <div class="procurement-summary-card">
                    <p class="procurement-summary-label">Cart subtotal</p>
                    <p class="procurement-summary-value">AED {{ number_format($checkoutSummary['subtotal'] ?? 0, 2) }}</p>
                    <p class="procurement-summary-note">Final submit fans out into supplier-side requests after review.</p>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            @foreach ([
                'search' => 'Search',
                'cart' => 'Cart',
                'orders' => 'My Orders',
                'pending' => 'Pending Orders',
            ] as $view => $label)
                <button
                    type="button"
                    wire:click="setActiveView('{{ $view }}')"
                    class="inline-flex items-center rounded-2xl border px-4 py-2.5 text-sm font-semibold transition {{ $activeView === $view ? 'border-gray-950 bg-gray-950 text-white' : 'border-gray-200 bg-white text-gray-700 hover:border-gray-300 hover:text-gray-950' }}"
                >
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <div class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]">
                <label class="space-y-2">
                    <span class="text-xs font-semibold uppercase tracking-[0.2em] text-gray-500">Ship To</span>
                    <select
                        wire:model.live="shipToWarehouseId"
                        class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        @forelse ($shipToWarehouseOptions as $warehouseId => $warehouseName)
                            <option value="{{ $warehouseId }}">{{ $warehouseName }}</option>
                        @empty
                            <option value="">No warehouse selected</option>
                        @endforelse
                    </select>
                </label>

                <label class="space-y-2">
                    <span class="text-xs font-semibold uppercase tracking-[0.2em] text-gray-500">PO #</span>
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="purchaseOrderNumber"
                        placeholder="Optional purchase order reference"
                        class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                </label>

                <div class="flex items-end justify-end gap-3">
                    @if ($activeView !== 'cart')
                        <button
                            type="button"
                            wire:click="setActiveView('cart')"
                            class="inline-flex items-center rounded-2xl border border-gray-200 bg-white px-4 py-3 text-sm font-semibold text-gray-700 transition hover:border-gray-300 hover:text-gray-950"
                        >
                            View Cart ({{ $checkoutSummary['selected_lines'] ?? 0 }})
                        </button>
                    @endif

                    @if ($activeView === 'cart')
                        <button
                            type="button"
                            wire:click="submitGroupedProcurement"
                            wire:loading.attr="disabled"
                            wire:target="submitGroupedProcurement"
                            @disabled(($checkoutSummary['selected_lines'] ?? 0) < 1)
                            class="inline-flex items-center rounded-2xl bg-gray-950 px-5 py-3 text-sm font-semibold text-white transition hover:bg-gray-800 disabled:cursor-not-allowed disabled:bg-gray-300"
                        >
                            <span wire:loading.remove wire:target="submitGroupedProcurement">Place Order</span>
                            <span wire:loading wire:target="submitGroupedProcurement">Submitting...</span>
                        </button>
                    @endif
                </div>
            </div>
        </div>

        @if ($activeView === 'search')
            <div class="space-y-6">
                <div class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                    <div class="grid gap-4 lg:grid-cols-[repeat(5,minmax(0,1fr))_auto]">
                        <label class="space-y-2">
                            <span class="text-xs font-semibold uppercase tracking-[0.2em] text-gray-500">Width</span>
                            <input type="text" wire:model.defer="searchWidth" placeholder="245" class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        </label>
                        <label class="space-y-2">
                            <span class="text-xs font-semibold uppercase tracking-[0.2em] text-gray-500">Height</span>
                            <input type="text" wire:model.defer="searchHeight" placeholder="30" class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        </label>
                        <label class="space-y-2">
                            <span class="text-xs font-semibold uppercase tracking-[0.2em] text-gray-500">Rim Size</span>
                            <input type="text" wire:model.defer="searchRimSize" placeholder="19" class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        </label>
                        <label class="space-y-2">
                            <span class="text-xs font-semibold uppercase tracking-[0.2em] text-gray-500">Minimum Qty</span>
                            <input type="number" min="1" wire:model.defer="searchMinimumQty" class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        </label>
                        <label class="space-y-2">
                            <span class="text-xs font-semibold uppercase tracking-[0.2em] text-gray-500">Supplier</span>
                            <select wire:model.defer="searchSupplierId" class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">All approved suppliers</option>
                                @foreach ($supplierOptions as $supplier)
                                    <option value="{{ $supplier['id'] }}">{{ $supplier['name'] }}</option>
                                @endforeach
                            </select>
                        </label>

                        <div class="flex items-end gap-3">
                            <button
                                type="button"
                                wire:click="applySearchFilters"
                                class="inline-flex items-center rounded-2xl bg-gray-950 px-5 py-3 text-sm font-semibold text-white transition hover:bg-gray-800"
                            >
                                View Results
                            </button>
                            <button
                                type="button"
                                wire:click="resetSearchFilters"
                                class="inline-flex items-center rounded-2xl border border-gray-200 bg-white px-5 py-3 text-sm font-semibold text-gray-700 transition hover:border-gray-300 hover:text-gray-950"
                            >
                                Reset
                            </button>
                        </div>
                    </div>
                </div>

                <div class="rounded-3xl border border-gray-200 bg-white shadow-sm">
                    <div class="flex flex-col gap-4 border-b border-gray-100 px-6 py-5 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-gray-500">Procurement Search Results</p>
                            <h2 class="mt-1 text-2xl font-semibold text-gray-950">Search approved supplier stock</h2>
                        </div>
                        <p class="text-sm text-gray-600">{{ count($searchResults) }} grouped tyre result(s) matching the current filter.</p>
                    </div>

                    @if ($searchResults === [])
                        <div class="px-6 py-8 text-sm leading-6 text-gray-600">
                            No supplier tyre results match the current procurement filter. Adjust width, height, rim size, minimum quantity, or supplier.
                        </div>
                    @else
                        <div class="divide-y divide-gray-100">
                            @foreach ($searchResults as $result)
                                <div class="px-6 py-5">
                                    <div class="procurement-result-shell">
                                        <div>
                                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-indigo-500">{{ $result['brand_name'] }}</p>
                                            <h3 class="mt-1 text-2xl font-semibold text-gray-950">{{ $result['model_name'] }}</h3>
                                            <div class="procurement-result-meta">
                                                <span class="procurement-result-chip">Size {{ $result['full_size'] }}</span>
                                                <span class="procurement-result-chip">Load {{ $result['load_index'] }}</span>
                                                <span class="procurement-result-chip">Speed {{ $result['speed_rating'] }}</span>
                                                <span class="procurement-result-chip">Year {{ $result['dot_year'] }}</span>
                                                <span class="procurement-result-chip">Run Flat {{ $result['runflat_label'] }}</span>
                                            </div>
                                        </div>

                                        <div class="procurement-result-metrics">
                                            <div class="procurement-result-metric">
                                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Suppliers</p>
                                                <p class="mt-2 text-2xl font-semibold text-gray-950">{{ $result['offer_count'] }}</p>
                                            </div>
                                            <div class="procurement-result-metric">
                                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Total available</p>
                                                <p class="mt-2 text-2xl font-semibold text-gray-950">{{ $result['available_quantity_total'] }}</p>
                                            </div>
                                            <div class="procurement-result-metric procurement-result-metric--price">
                                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Best unit price</p>
                                                <p class="mt-2 text-lg font-semibold text-gray-950">AED {{ number_format($result['best_unit_price'], 2) }}</p>
                                            </div>
                                        </div>

                                        <div class="procurement-result-actions">
                                            <button
                                                type="button"
                                                wire:click="toggleResultExpansion('{{ $result['result_key'] }}')"
                                                class="inline-flex min-h-[3.5rem] items-center justify-center rounded-2xl border border-gray-200 bg-white px-5 py-3 text-sm font-semibold text-gray-700 transition hover:border-gray-300 hover:text-gray-950"
                                            >
                                                {{ in_array($result['result_key'], $expandedResultKeys, true) ? 'Hide suppliers' : 'Compare suppliers' }}
                                            </button>
                                        </div>
                                    </div>

                                    @if (in_array($result['result_key'], $expandedResultKeys, true))
                                        <div class="mt-5 overflow-hidden rounded-2xl border border-gray-200">
                                            <div class="grid grid-cols-[minmax(0,1.2fr)_9rem_8rem_8rem_10rem_10rem] gap-4 bg-gray-50 px-4 py-3 text-xs font-semibold uppercase tracking-[0.2em] text-gray-500">
                                                <span>Supplier Offer</span>
                                                <span class="text-right">SKU</span>
                                                <span class="text-right">Available</span>
                                                <span class="text-right">Unit Price</span>
                                                <span class="text-center">Qty</span>
                                                <span class="text-right">Action</span>
                                            </div>

                                            <div class="divide-y divide-gray-100">
                                                @foreach ($result['supplier_rows'] as $row)
                                                    <div class="grid grid-cols-[minmax(0,1.2fr)_9rem_8rem_8rem_10rem_10rem] gap-4 px-4 py-4 text-sm text-gray-700">
                                                        <div>
                                                            <p class="font-semibold text-gray-950">{{ $row['supplier_name'] }}</p>
                                                            <p class="mt-1 text-xs text-gray-500">{{ $row['warehouse_name'] ?? 'Warehouse pending' }}</p>
                                                        </div>
                                                        <div class="text-right font-medium text-gray-900">{{ $row['sku'] }}</div>
                                                        <div class="text-right font-medium text-gray-900">{{ $row['available_quantity'] }}</div>
                                                        <div class="text-right font-semibold text-gray-950">AED {{ number_format($row['unit_price'], 2) }}</div>
                                                        <div class="flex items-center justify-center gap-2">
                                                            <button type="button" wire:click="decrementQuantity({{ $row['offer_id'] }})" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-gray-200 bg-gray-50 text-lg font-semibold text-gray-700 transition hover:bg-gray-100">-</button>
                                                            <input type="number" min="0" max="{{ max(0, $row['available_quantity']) }}" wire:model.live="selectedQuantities.{{ $row['offer_id'] }}" class="h-9 w-16 rounded-xl border border-gray-200 text-center text-sm font-semibold text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                                            <button type="button" wire:click="incrementQuantity({{ $row['offer_id'] }})" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-gray-200 bg-gray-50 text-lg font-semibold text-gray-700 transition hover:bg-gray-100">+</button>
                                                        </div>
                                                        <div class="flex justify-end">
                                                            <button
                                                                type="button"
                                                                wire:click="addRecommendedQuantity({{ $row['offer_id'] }}, {{ max(1, min(4, (int) ($row['available_quantity'] ?? 1))) }})"
                                                                class="inline-flex items-center rounded-2xl bg-gray-950 px-4 py-2 text-sm font-semibold text-white transition hover:bg-gray-800"
                                                            >
                                                                Add to cart
                                                            </button>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        @elseif ($activeView === 'cart')
            <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
                <section class="space-y-6">
                    @forelse ($cartSections as $section)
                        <div class="rounded-3xl border border-gray-200 bg-white shadow-sm">
                            <div class="flex flex-col gap-4 border-b border-gray-100 px-6 py-5 lg:flex-row lg:items-start lg:justify-between">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-gray-500">Supplier Section</p>
                                    <h2 class="mt-1 text-2xl font-semibold text-gray-950">{{ $section['supplier_name'] }}</h2>
                                    <p class="mt-2 max-w-3xl text-sm leading-6 text-gray-600">{{ $section['supplier_summary'] }}</p>
                                </div>

                                <div class="grid gap-3 sm:grid-cols-2">
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
                                @foreach ($section['offers'] as $offer)
                                    <div class="grid gap-4 px-6 py-5 lg:grid-cols-[minmax(0,1fr)_12rem_12rem] lg:items-center">
                                        <div>
                                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-indigo-500">{{ $offer['brand_name'] }}</p>
                                            <h3 class="mt-1 text-xl font-semibold text-gray-950">{{ $offer['model_name'] }}</h3>
                                            <div class="mt-3 flex flex-wrap gap-3 text-sm text-gray-600">
                                                <span>SKU {{ $offer['sku'] }}</span>
                                                <span>Size {{ $offer['full_size'] }}</span>
                                                <span>{{ $offer['warehouse_name'] ?? 'Warehouse pending' }}</span>
                                            </div>
                                        </div>

                                        <div class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-4">
                                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Quantity</p>
                                            <div class="mt-3 flex items-center justify-between gap-2">
                                                <button type="button" wire:click="decrementQuantity({{ $offer['offer_id'] }})" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-gray-200 bg-white text-lg font-semibold text-gray-700 transition hover:bg-gray-100">-</button>
                                                <input type="number" min="0" max="{{ max(0, $offer['available_quantity']) }}" wire:model.live="selectedQuantities.{{ $offer['offer_id'] }}" class="h-9 w-16 rounded-xl border border-gray-200 text-center text-sm font-semibold text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                                <button type="button" wire:click="incrementQuantity({{ $offer['offer_id'] }})" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-gray-200 bg-white text-lg font-semibold text-gray-700 transition hover:bg-gray-100">+</button>
                                            </div>
                                        </div>

                                        <div class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-4 text-right">
                                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Line total</p>
                                            <p class="mt-2 text-2xl font-semibold text-gray-950">AED {{ number_format($offer['line_total'], 2) }}</p>
                                            <p class="mt-2 text-xs text-gray-500">Unit AED {{ number_format($offer['unit_price'], 2) }}</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <div class="rounded-3xl border border-dashed border-gray-300 bg-white p-8 text-sm leading-6 text-gray-600 shadow-sm">
                            No procurement lines are in the cart yet. Search supplier stock, compare offers, and add lines to the grouped cart first.
                        </div>
                    @endforelse
                </section>

                <aside class="space-y-6">
                    <div class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-gray-500">Checkout summary</p>
                        <h2 class="mt-2 text-2xl font-semibold text-gray-950">Grouped supplier checkout</h2>

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

                        @if (! empty($latestSubmissionSummary))
                            <div class="mt-5 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
                                <p class="font-semibold">{{ $latestSubmissionSummary['submission_number'] ?? 'Latest submission' }}</p>
                                <p class="mt-1">
                                    Created {{ $latestSubmissionSummary['request_count'] ?? 0 }} supplier request(s)
                                    across {{ $latestSubmissionSummary['supplier_count'] ?? 0 }} supplier section(s).
                                </p>
                            </div>
                        @endif
                    </div>
                </aside>
            </div>
        @else
            <div class="rounded-3xl border border-gray-200 bg-white shadow-sm">
                <div class="flex flex-col gap-4 border-b border-gray-100 px-6 py-5 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-gray-500">{{ $activeView === 'pending' ? 'Pending Orders' : 'My Orders' }}</p>
                        <h2 class="mt-1 text-2xl font-semibold text-gray-950">{{ $activeView === 'pending' ? 'Awaiting supplier completion' : 'Placed procurement activity' }}</h2>
                    </div>
                    <p class="text-sm text-gray-600">{{ count($historyRows) }} row(s) in the current procurement history view.</p>
                </div>

                @if ($historyRows === [])
                    <div class="px-6 py-8 text-sm leading-6 text-gray-600">
                        No procurement history is available in this view yet.
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100">
                            <thead class="bg-gray-50">
                                <tr class="text-left text-xs font-semibold uppercase tracking-[0.2em] text-gray-500">
                                    <th class="px-6 py-3">Document</th>
                                    <th class="px-6 py-3">Type</th>
                                    <th class="px-6 py-3">Customer / Supplier</th>
                                    <th class="px-6 py-3">Status</th>
                                    <th class="px-6 py-3">Total</th>
                                    <th class="px-6 py-3">Occurred</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white text-sm text-gray-700">
                                @foreach ($historyRows as $row)
                                    <tr>
                                        <td class="px-6 py-4 font-semibold text-gray-950">{{ $row['document_number'] }}</td>
                                        <td class="px-6 py-4">{{ $row['document_type_label'] }}</td>
                                        <td class="px-6 py-4">{{ $row['customer_name'] }}</td>
                                        <td class="px-6 py-4">{{ $row['status_label'] }}</td>
                                        <td class="px-6 py-4">AED {{ number_format((float) ($row['total'] ?? 0), 2) }}</td>
                                        <td class="px-6 py-4">{{ $row['occurred_at'] ?? 'Pending date' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-filament-panels::page>
