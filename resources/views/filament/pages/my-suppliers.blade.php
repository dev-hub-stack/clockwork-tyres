<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-2xl border border-cyan-200 bg-cyan-50 p-5 text-cyan-950">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-cyan-700">Retailer admin</p>
            <h1 class="mt-1 text-2xl font-semibold">My Suppliers</h1>
            <p class="mt-2 text-sm leading-6 text-cyan-900">
                This page represents the supplier network George wants inside the backend. Approved and pending supplier relationships live here, and procurement requests can be launched from this network instead of the old Clockwork backend.
            </p>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Current account</p>
                <p class="mt-2 text-lg font-semibold text-gray-900">{{ $currentAccountSummary['name'] ?? 'Unknown' }}</p>
                <p class="mt-1 text-sm text-gray-600">{{ $currentAccountSummary['account_type'] ?? '-' }}</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Base plan</p>
                <p class="mt-2 text-lg font-semibold text-gray-900">{{ $currentAccountSummary['base_plan'] ?? '-' }}</p>
                <p class="mt-1 text-sm text-gray-600">Supplier network lives in admin</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Approved suppliers</p>
                <p class="mt-2 text-lg font-semibold text-gray-900">{{ $connectionSummary['approved_suppliers'] ?? 0 }}</p>
                <p class="mt-1 text-sm text-gray-600">Pending requests: {{ $connectionSummary['pending_requests'] ?? 0 }}</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Supplier limit</p>
                <p class="mt-2 text-lg font-semibold text-gray-900">{{ $connectionSummary['supplier_limit'] ?? '-' }}</p>
                <p class="mt-1 text-sm text-gray-600">Remaining slots: {{ $connectionSummary['remaining_slots'] ?? '-' }}</p>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-2 border-b border-gray-100 pb-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Connected suppliers</p>
                    <h2 class="text-lg font-semibold text-gray-900">Supplier relationship list</h2>
                </div>
                <div class="rounded-full bg-amber-50 px-3 py-1 text-xs font-medium text-amber-700">
                    Procurement uses manual supplier selection
                </div>
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Supplier</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Type</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Approved At</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Reports Add-on</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Warehouse Note</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Note</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @forelse ($supplierRows as $row)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $row['supplier'] ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ $row['type'] ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ $row['status'] ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ $row['approved_at'] ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ $row['reports_addon'] ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ $row['warehouse_note'] ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ $row['note'] ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-10 text-center text-sm text-gray-500">
                                    No supplier relationships are connected yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>
