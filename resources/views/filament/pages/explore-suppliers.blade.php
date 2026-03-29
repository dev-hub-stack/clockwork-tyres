<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-2xl border border-indigo-200 bg-indigo-50 p-5 text-indigo-950">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-indigo-700">Retailer admin</p>
            <h1 class="mt-1 text-2xl font-semibold">Explore Suppliers</h1>
            <p class="mt-2 text-sm leading-6 text-indigo-900">
                George asked for supplier discovery to move into the backend. This page is the admin-side directory for wholesale-enabled accounts that can be added into the retailer network.
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
                <p class="mt-1 text-sm text-gray-600">Approved suppliers: {{ $currentAccountSummary['supplier_count'] ?? 0 }}</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Supplier limit</p>
                <p class="mt-2 text-lg font-semibold text-gray-900">{{ $entitlementSummary['supplier_limit'] ?? '-' }}</p>
                <p class="mt-1 text-sm text-gray-600">Remaining slots: {{ $entitlementSummary['remaining_slots'] ?? '-' }}</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Can add more</p>
                <p class="mt-2 text-lg font-semibold text-gray-900">{{ $entitlementSummary['can_add_more'] ?? '-' }}</p>
                <p class="mt-1 text-sm text-gray-600">Reports add-on: {{ $entitlementSummary['reports_addon'] ?? '-' }}</p>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-2 border-b border-gray-100 pb-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Supplier directory</p>
                    <h2 class="text-lg font-semibold text-gray-900">Wholesale-enabled accounts</h2>
                </div>
                <div class="rounded-full bg-amber-50 px-3 py-1 text-xs font-medium text-amber-700">
                    Basic retail plan supports up to 3 suppliers
                </div>
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Supplier</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Type</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Base Plan</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Reports Add-on</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Connected Retailers</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Connection Status</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Next Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @forelse ($supplierRows as $row)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $row['supplier'] ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ $row['type'] ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ $row['base_plan'] ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ $row['reports_addon'] ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ $row['connected_retailers'] ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ $row['connection_status'] ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ $row['next_action'] ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-10 text-center text-sm text-gray-500">
                                    No wholesale-enabled supplier accounts are available yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>
