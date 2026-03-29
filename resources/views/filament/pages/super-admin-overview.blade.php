<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-2xl border border-violet-200 bg-violet-50 p-5 text-violet-950">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-violet-700">Governance shell</p>
            <h1 class="mt-1 text-2xl font-semibold">Super Admin Overview</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-violet-900">
                This is the platform control tower George described. It is intentionally focused on accounts, subscriptions, reports add-ons, analytics, and operational visibility. It does not manage supplier product or inventory editing.
            </p>
            <p class="mt-3 inline-flex rounded-full bg-white px-3 py-1 text-xs font-semibold text-violet-700 ring-1 ring-violet-200">
                Read-only surface for governance, subscriptions, and platform visibility.
            </p>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            @foreach ($metricCards as $card)
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $card['label'] }}</p>
                    <p class="mt-3 text-2xl font-semibold text-gray-900">{{ $card['value'] }}</p>
                    <p class="mt-2 text-sm leading-6 text-gray-600">{{ $card['note'] }}</p>
                </div>
            @endforeach
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            @foreach ($accountGovernanceCards as $card)
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $card['label'] }}</p>
                    <p class="mt-3 text-2xl font-semibold text-gray-900">{{ $card['value'] }}</p>
                    <p class="mt-2 text-sm leading-6 text-gray-600">{{ $card['note'] }}</p>
                </div>
            @endforeach
        </div>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-2 border-b border-gray-100 pb-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Account directory</p>
                        <h2 class="text-lg font-semibold text-gray-900">Governance-first account view</h2>
                    </div>
                    <div class="rounded-full bg-violet-50 px-3 py-1 text-xs font-medium text-violet-700">
                        No product or inventory editing here
                    </div>
                </div>

                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                @foreach ($accountDirectoryColumns as $column)
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">{{ $column }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            @foreach ($accountRows as $row)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $row['account'] }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-700">{{ $row['type'] }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-700">{{ $row['status'] }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-700">{{ $row['base_plan'] }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-700">{{ $row['reports_addon'] }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-700">{{ $row['wholesale'] }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-700">{{ $row['retail'] }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-700">{{ $row['connected_retailers'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            <aside class="space-y-6">
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Account creation</p>
                    <h3 class="mt-1 text-lg font-semibold text-gray-900">Create and manage accounts</h3>

                    <div class="mt-4 space-y-3">
                        @foreach ($accountGovernanceActions as $action)
                            <div class="rounded-xl border border-dashed border-gray-200 bg-gray-50 p-4">
                                <p class="text-sm font-semibold text-gray-900">{{ $action }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Reports add-on</p>
                    <h3 class="mt-1 text-lg font-semibold text-gray-900">Tier management</h3>

                    <div class="mt-4 space-y-3">
                        @foreach ($reportAddOnTiers as $tier)
                            <div class="rounded-xl border border-dashed border-gray-200 bg-gray-50 p-4">
                                <p class="text-sm font-semibold text-gray-900">{{ $tier['label'] }}</p>
                                <p class="mt-1 text-sm text-violet-700">{{ $tier['price'] }}</p>
                                <p class="mt-2 text-xs leading-5 text-gray-600">{{ $tier['note'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Guardrails</p>
                    <h3 class="mt-1 text-lg font-semibold text-gray-900">What super admin cannot do</h3>

                    <div class="mt-4 space-y-3">
                        @foreach ($guardrailCards as $card)
                            <div class="rounded-xl border border-dashed border-gray-200 bg-gray-50 p-4">
                                <p class="text-sm font-semibold text-gray-900">{{ $card['label'] }}</p>
                                <p class="mt-1 text-sm text-violet-700">{{ $card['value'] }}</p>
                                <p class="mt-2 text-xs leading-5 text-gray-600">{{ $card['note'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Governance actions</p>
                    <h3 class="mt-1 text-lg font-semibold text-gray-900">What super admin controls</h3>

                    <ul class="mt-4 list-disc space-y-2 pl-5 text-sm leading-6 text-gray-700">
                        @foreach ($governanceActions as $action)
                            <li>{{ $action }}</li>
                        @endforeach
                    </ul>
                </div>
            </aside>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            @foreach ($opsPanels as $panel)
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                    <p class="text-sm font-semibold text-gray-900">{{ $panel['title'] }}</p>
                    <p class="mt-2 text-sm leading-6 text-gray-600">{{ $panel['description'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
