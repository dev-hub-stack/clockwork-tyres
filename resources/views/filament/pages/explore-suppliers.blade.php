<x-filament-panels::page>
    <div class="space-y-6">
        <section class="rounded-3xl border border-gray-200 bg-white shadow-sm">
            <div class="flex flex-col gap-4 border-b border-gray-100 px-6 py-5 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Supplier Network</p>
                    <h1 class="text-3xl font-semibold tracking-tight text-gray-950">Explore Suppliers</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-gray-600">
                        Browse wholesale-enabled supplier accounts, review plan fit, and send connection requests from the same CRM surface as procurement and quotes.
                    </p>
                </div>

                <div class="flex flex-wrap gap-3">
                    <a
                        href="{{ url('/admin/my-suppliers') }}"
                        class="inline-flex items-center justify-center rounded-xl border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50"
                    >
                        My Suppliers
                    </a>
                    <a
                        href="{{ url('/admin/procurement-workbench') }}"
                        class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800"
                    >
                        Open Procurement
                    </a>
                </div>
            </div>

            <div class="grid gap-4 px-6 py-5 md:grid-cols-2 xl:grid-cols-4">
                <article class="rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Business Account</p>
                    <p class="mt-3 text-lg font-semibold text-slate-900">{{ $currentAccountSummary['name'] ?? 'Unknown' }}</p>
                    <p class="mt-1 text-sm text-slate-600">{{ $currentAccountSummary['account_type'] ?? '-' }}</p>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Plan</p>
                    <p class="mt-3 text-lg font-semibold text-slate-900">{{ $currentAccountSummary['base_plan'] ?? '-' }}</p>
                    <p class="mt-1 text-sm text-slate-600">Approved suppliers: {{ $currentAccountSummary['supplier_count'] ?? 0 }}</p>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Supplier Limit</p>
                    <p class="mt-3 text-lg font-semibold text-slate-900">{{ $entitlementSummary['supplier_limit'] ?? '-' }}</p>
                    <p class="mt-1 text-sm text-slate-600">Remaining slots: {{ $entitlementSummary['remaining_slots'] ?? '-' }}</p>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Network Status</p>
                    <p class="mt-3 text-lg font-semibold text-slate-900">{{ $entitlementSummary['can_add_more'] ?? '-' }}</p>
                    <p class="mt-1 text-sm text-slate-600">Reports add-on: {{ $entitlementSummary['reports_addon'] ?? '-' }}</p>
                </article>
            </div>
        </section>

        <section class="rounded-3xl border border-gray-200 bg-white shadow-sm">
            <div class="flex flex-col gap-4 border-b border-gray-100 px-6 py-5 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Supplier Directory</p>
                    <h2 class="text-2xl font-semibold tracking-tight text-gray-950">Wholesale-ready suppliers</h2>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <label class="min-w-[16rem]">
                        <span class="sr-only">Search suppliers</span>
                        <input
                            type="search"
                            wire:model.live.debounce.300ms="search"
                            placeholder="Search supplier, type, or plan"
                            class="w-full rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20"
                        />
                    </label>

                    <label class="min-w-[12rem]">
                        <span class="sr-only">Filter by status</span>
                        <select
                            wire:model.live="statusFilter"
                            class="w-full rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20"
                        >
                            <option value="all">All statuses</option>
                            <option value="available">Available</option>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </label>

                    <div class="rounded-full bg-amber-50 px-3 py-2 text-xs font-medium text-amber-700 ring-1 ring-inset ring-amber-200">
                        Starter retailers can connect up to 3 suppliers
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.2em] text-gray-500">Supplier</th>
                            <th class="px-4 py-4 text-left text-xs font-semibold uppercase tracking-[0.2em] text-gray-500">Type</th>
                            <th class="px-4 py-4 text-left text-xs font-semibold uppercase tracking-[0.2em] text-gray-500">Plan</th>
                            <th class="px-4 py-4 text-left text-xs font-semibold uppercase tracking-[0.2em] text-gray-500">Reports</th>
                            <th class="px-4 py-4 text-left text-xs font-semibold uppercase tracking-[0.2em] text-gray-500">Connected Retailers</th>
                            <th class="px-4 py-4 text-left text-xs font-semibold uppercase tracking-[0.2em] text-gray-500">Status</th>
                            <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-[0.2em] text-gray-500">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @forelse ($this->filteredSupplierRows as $row)
                            <tr class="hover:bg-gray-50/80">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-sm font-semibold text-slate-700">
                                            {{ collect(explode(' ', $row['supplier'] ?? 'Supplier'))->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->implode('') }}
                                        </div>
                                        <div>
                                            <p class="text-sm font-semibold text-gray-900">{{ $row['supplier'] ?? '-' }}</p>
                                            <p class="text-xs text-gray-500">Wholesale-enabled supplier account</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-sm text-gray-700">{{ $row['type'] ?? '-' }}</td>
                                <td class="px-4 py-4 text-sm text-gray-700">{{ $row['base_plan'] ?? '-' }}</td>
                                <td class="px-4 py-4 text-sm text-gray-700">{{ $row['reports_addon'] ?? '-' }}</td>
                                <td class="px-4 py-4 text-sm text-gray-700">{{ $row['connected_retailers'] ?? '-' }}</td>
                                <td class="px-4 py-4">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $this->statusBadgeClasses($row['connection_status_value'] ?? 'available') }}">
                                        {{ $row['connection_status'] ?? '-' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    @php($actionKey = $row['action_key'] ?? 'connect')
                                    @if ($actionKey === 'connected')
                                        <a
                                            href="{{ url('/admin/procurement-workbench?supplier='.$row['supplier_id']) }}"
                                            class="{{ $this->actionButtonClasses($actionKey) }}"
                                        >
                                            Open Procurement
                                        </a>
                                    @elseif ($actionKey === 'pending')
                                        <span class="{{ $this->actionButtonClasses($actionKey) }}">
                                            Request Pending
                                        </span>
                                    @else
                                        <button
                                            type="button"
                                            wire:click="requestSupplier({{ $row['supplier_id'] }})"
                                            @disabled(! $canAddSuppliers && $actionKey === 'connect')
                                            class="{{ $this->actionButtonClasses($actionKey) }} disabled:cursor-not-allowed disabled:opacity-50"
                                        >
                                            {{ $row['next_action'] ?? 'Add Supplier' }}
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-16 text-center">
                                    <div class="mx-auto max-w-sm">
                                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 text-slate-500">
                                            <x-heroicon-o-magnifying-glass class="h-7 w-7" />
                                        </div>
                                        <h3 class="mt-4 text-base font-semibold text-gray-900">No suppliers match the current filters</h3>
                                        <p class="mt-2 text-sm text-gray-500">
                                            Try changing the search text or status filter to broaden the supplier directory.
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-filament-panels::page>
