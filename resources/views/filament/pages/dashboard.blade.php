<x-filament-panels::page>
    @if (! $isSuperAdmin)
        <x-filament::section>
            <div class="space-y-2">
                <h2 class="text-xl font-semibold text-gray-950 dark:text-white">Platform Dashboard</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    This dashboard is now reserved for super-admin platform monitoring. Use the CRM navigation for business operations in the current account.
                </p>
            </div>
        </x-filament::section>
    @else
        <div class="space-y-6">
            <x-filament::section>
                <div class="space-y-2">
                    <h2 class="text-2xl font-semibold text-gray-950 dark:text-white">Platform Dashboard</h2>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Governance view for business accounts, subscriptions, procurement flow, and tyre import health.
                    </p>
                </div>
            </x-filament::section>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                @foreach ($headlineStats as $stat)
                    <x-filament::section>
                        <div class="space-y-1">
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $stat['label'] }}</p>
                            <p class="text-3xl font-semibold text-gray-950 dark:text-white">{{ $stat['value'] }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $stat['hint'] }}</p>
                        </div>
                    </x-filament::section>
                @endforeach
            </div>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                @foreach ($commerceStats as $stat)
                    <x-filament::section>
                        <div class="space-y-1">
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $stat['label'] }}</p>
                            <p class="text-2xl font-semibold text-gray-950 dark:text-white">{{ $stat['value'] }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $stat['hint'] }}</p>
                        </div>
                    </x-filament::section>
                @endforeach
            </div>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                @foreach ($businessMixStats as $stat)
                    <x-filament::section>
                        <div class="space-y-1">
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $stat['label'] }}</p>
                            <p class="text-2xl font-semibold text-gray-950 dark:text-white">{{ $stat['value'] }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $stat['hint'] }}</p>
                        </div>
                    </x-filament::section>
                @endforeach
            </div>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                @foreach ($operationalStats as $stat)
                    <x-filament::section>
                        <div class="space-y-1">
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $stat['label'] }}</p>
                            <p class="text-2xl font-semibold text-gray-950 dark:text-white">{{ $stat['value'] }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $stat['hint'] }}</p>
                        </div>
                    </x-filament::section>
                @endforeach
            </div>

            <div class="grid gap-6 xl:grid-cols-2">
                <x-filament::section>
                    <x-slot name="heading">Recent Business Accounts</x-slot>
                    <x-slot name="description">Latest tenant creation and current subscription posture.</x-slot>

                    @if (count($recentAccounts) === 0)
                        <p class="text-sm text-gray-500 dark:text-gray-400">No business accounts have been created yet.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                                <thead>
                                    <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                        <th class="px-3 py-2">Account</th>
                                        <th class="px-3 py-2">Type</th>
                                        <th class="px-3 py-2">Status</th>
                                        <th class="px-3 py-2">Plan</th>
                                        <th class="px-3 py-2">Created</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                                    @foreach ($recentAccounts as $account)
                                        <tr>
                                            <td class="px-3 py-3">
                                                <a href="{{ $account['url'] }}" class="font-medium text-primary-600 hover:underline dark:text-primary-400">
                                                    {{ $account['name'] }}
                                                </a>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $account['slug'] }}</div>
                                            </td>
                                            <td class="px-3 py-3">{{ $account['type'] }}</td>
                                            <td class="px-3 py-3">{{ $account['status'] }}</td>
                                            <td class="px-3 py-3">{{ $account['plan'] }}</td>
                                            <td class="px-3 py-3">
                                                <div>{{ $account['created_at'] }}</div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">by {{ $account['created_by'] }}</div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-filament::section>

                <x-filament::section>
                    <x-slot name="heading">Procurement Queue Snapshot</x-slot>
                    <x-slot name="description">Latest supplier-retailer requests moving through procurement lifecycle.</x-slot>

                    @if (count($recentProcurementRequests) === 0)
                        <p class="text-sm text-gray-500 dark:text-gray-400">No procurement requests have been submitted yet.</p>
                    @else
                        <div class="space-y-3">
                            @foreach ($recentProcurementRequests as $request)
                                <a href="{{ $request['url'] }}" class="block rounded-xl border border-gray-200 px-4 py-3 transition hover:border-primary-300 hover:bg-gray-50 dark:border-white/10 dark:hover:bg-white/5">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="space-y-1">
                                            <div class="font-medium text-gray-950 dark:text-white">{{ $request['request_number'] }}</div>
                                            <div class="text-sm text-gray-600 dark:text-gray-400">
                                                {{ $request['retailer'] ?? 'Unknown retailer' }} -> {{ $request['supplier'] ?? 'Unknown supplier' }}
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-sm font-medium text-gray-950 dark:text-white">{{ $request['stage'] }}</div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $request['submitted_at'] }}</div>
                                        </div>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </x-filament::section>
            </div>

            <x-filament::section>
                <x-slot name="heading">Tyre Import Alerts</x-slot>
                <x-slot name="description">Uploads with invalid headers, invalid rows, or duplicate grouping issues.</x-slot>

                @if (count($importAlerts) === 0)
                    <p class="text-sm text-gray-500 dark:text-gray-400">No tyre import alerts are currently open.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                            <thead>
                                <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    <th class="px-3 py-2">File</th>
                                    <th class="px-3 py-2">Account</th>
                                    <th class="px-3 py-2">Status</th>
                                    <th class="px-3 py-2">Invalid Rows</th>
                                    <th class="px-3 py-2">Duplicate Rows</th>
                                    <th class="px-3 py-2">Uploaded</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                                @foreach ($importAlerts as $alert)
                                    <tr>
                                        <td class="px-3 py-3 font-medium text-gray-950 dark:text-white">{{ $alert['file_name'] }}</td>
                                        <td class="px-3 py-3">{{ $alert['account'] }}</td>
                                        <td class="px-3 py-3">{{ $alert['status'] }}</td>
                                        <td class="px-3 py-3">{{ $alert['invalid_rows'] }}</td>
                                        <td class="px-3 py-3">{{ $alert['duplicate_rows'] }}</td>
                                        <td class="px-3 py-3">{{ $alert['uploaded_at'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-filament::section>
        </div>
    @endif
</x-filament-panels::page>
