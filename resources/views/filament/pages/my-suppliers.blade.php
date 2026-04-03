<x-filament-panels::page>
    <div class="space-y-6">
        <section class="rounded-3xl border border-gray-200 bg-white shadow-sm">
            <div class="flex flex-col gap-4 border-b border-gray-100 px-6 py-5 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Supplier Network</p>
                    <h1 class="text-3xl font-semibold tracking-tight text-gray-950">My Suppliers</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-gray-600">
                        Manage approved and pending supplier relationships, check connection status, and jump into procurement from the retailer CRM.
                    </p>
                </div>

                <div class="flex flex-wrap gap-3">
                    <a
                        href="{{ url('/admin/explore-suppliers') }}"
                        class="inline-flex items-center justify-center rounded-xl border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50"
                    >
                        Explore Suppliers
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
                    <p class="mt-1 text-sm text-slate-600">Supplier network lives in admin</p>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Approved Suppliers</p>
                    <p class="mt-3 text-lg font-semibold text-slate-900">{{ $connectionSummary['approved_suppliers'] ?? 0 }}</p>
                    <p class="mt-1 text-sm text-slate-600">Pending requests: {{ $connectionSummary['pending_requests'] ?? 0 }}</p>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Supplier Limit</p>
                    <p class="mt-3 text-lg font-semibold text-slate-900">{{ $connectionSummary['supplier_limit'] ?? '-' }}</p>
                    <p class="mt-1 text-sm text-slate-600">Remaining slots: {{ $connectionSummary['remaining_slots'] ?? '-' }}</p>
                </article>
            </div>
        </section>

        <section class="rounded-3xl border border-gray-200 bg-white shadow-sm">
            <div class="flex flex-col gap-4 border-b border-gray-100 px-6 py-5 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Connected Suppliers</p>
                    <h2 class="text-2xl font-semibold tracking-tight text-gray-950">Supplier relationship list</h2>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <label class="min-w-[16rem]">
                        <span class="sr-only">Search connected suppliers</span>
                        <input
                            type="search"
                            wire:model.live.debounce.300ms="search"
                            placeholder="Search supplier, type, or status"
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
                            <option value="approved">Approved</option>
                            <option value="pending">Pending</option>
                            <option value="rejected">Rejected</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </label>

                    <div class="rounded-full bg-amber-50 px-3 py-2 text-xs font-medium text-amber-700 ring-1 ring-inset ring-amber-200">
                        Procurement keeps manual supplier selection
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.2em] text-gray-500">Supplier</th>
                            <th class="px-4 py-4 text-left text-xs font-semibold uppercase tracking-[0.2em] text-gray-500">Type</th>
                            <th class="px-4 py-4 text-left text-xs font-semibold uppercase tracking-[0.2em] text-gray-500">Status</th>
                            <th class="px-4 py-4 text-left text-xs font-semibold uppercase tracking-[0.2em] text-gray-500">Approved At</th>
                            <th class="px-4 py-4 text-left text-xs font-semibold uppercase tracking-[0.2em] text-gray-500">Reports</th>
                            <th class="px-4 py-4 text-left text-xs font-semibold uppercase tracking-[0.2em] text-gray-500">Warehouse Note</th>
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
                                            <p class="text-xs text-gray-500">{{ $row['note'] ?? '-' }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-sm text-gray-700">{{ $row['type'] ?? '-' }}</td>
                                <td class="px-4 py-4">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $this->statusBadgeClasses($row['status_value'] ?? 'unknown') }}">
                                        {{ $row['status'] ?? '-' }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-sm text-gray-700">{{ $row['approved_at'] ?? '-' }}</td>
                                <td class="px-4 py-4 text-sm text-gray-700">{{ $row['reports_addon'] ?? '-' }}</td>
                                <td class="px-4 py-4 text-sm text-gray-600">{{ $row['warehouse_note'] ?? '-' }}</td>
                                <td class="px-6 py-4 text-right">
                                    @if (($row['status_value'] ?? null) === 'approved' && ! empty($row['supplier_id']))
                                        <a
                                            href="{{ url('/admin/procurement-workbench?supplier='.$row['supplier_id']) }}"
                                            class="inline-flex items-center rounded-xl bg-slate-900 px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800"
                                        >
                                            Open Procurement
                                        </a>
                                    @else
                                        <a
                                            href="{{ url('/admin/explore-suppliers') }}"
                                            class="inline-flex items-center rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50"
                                        >
                                            Manage Request
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-16 text-center">
                                    <div class="mx-auto max-w-sm">
                                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 text-slate-500">
                                            <x-heroicon-o-building-storefront class="h-7 w-7" />
                                        </div>
                                        <h3 class="mt-4 text-base font-semibold text-gray-900">No supplier relationships match the current filters</h3>
                                        <p class="mt-2 text-sm text-gray-500">
                                            Change the search text or status filter to review more supplier relationships.
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
