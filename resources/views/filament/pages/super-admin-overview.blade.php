<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-2xl border border-violet-200 bg-violet-50 p-5 text-violet-950">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-violet-700">Governance shell</p>
            <h1 class="mt-1 text-2xl font-semibold">Super Admin Overview</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-violet-900">
                This is the platform control tower George described. It is intentionally focused on live accounts, subscriptions, reports add-ons, analytics, and operational visibility. It does not manage supplier product or inventory editing.
            </p>
            <p class="mt-3 inline-flex rounded-full bg-white px-3 py-1 text-xs font-semibold text-violet-700 ring-1 ring-violet-200">
                Live governance surface for accounts, subscriptions, and reports add-ons.
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
                        No impersonation, no approval queue, no product editing
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
                            @forelse ($accountRows as $row)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                        <button
                                            type="button"
                                            wire:click="selectAccount({{ $row['id'] }})"
                                            class="font-semibold text-violet-700 hover:text-violet-900"
                                        >
                                            {{ $row['account'] }}
                                        </button>
                                        <p class="mt-1 text-xs text-gray-500">{{ $row['slug'] }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700">{{ $row['type'] }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-700">{{ $row['status'] }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-700">{{ $row['base_plan'] }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-700">{{ $row['reports_addon'] }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-700">{{ $row['wholesale'] }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-700">{{ $row['retail'] }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-700">{{ $row['approved_connections'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-10 text-center text-sm text-gray-500">
                                        No live accounts are available yet.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <aside class="space-y-6">
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Account creation</p>
                    <h3 class="mt-1 text-lg font-semibold text-gray-900">Create supplier, retailer, or mixed account</h3>

                    <form wire:submit.prevent="createAccount" class="mt-4 space-y-4">
                        <div>
                            <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Account name</label>
                            <input wire:model.defer="createAccountForm.name" type="text" class="mt-2 w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-violet-500 focus:ring-violet-500" />
                            @error('name') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Slug</label>
                            <input wire:model.defer="createAccountForm.slug" type="text" class="mt-2 w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-violet-500 focus:ring-violet-500" />
                            <p class="mt-1 text-xs text-gray-500">Leave blank to auto-generate from the account name.</p>
                            @error('slug') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Account type</label>
                                <select wire:model.defer="createAccountForm.account_type" class="mt-2 w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-violet-500 focus:ring-violet-500">
                                    @foreach ($accountTypeOptions as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Status</label>
                                <select wire:model.defer="createAccountForm.status" class="mt-2 w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-violet-500 focus:ring-violet-500">
                                    @foreach ($accountStatusOptions as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="flex items-center gap-3 rounded-xl border border-gray-200 bg-gray-50 px-3 py-3 text-sm text-gray-700">
                                <input wire:model.defer="createAccountForm.retail_enabled" type="checkbox" class="rounded border-gray-300 text-violet-600 focus:ring-violet-500" />
                                Retail enabled
                            </label>
                            <label class="flex items-center gap-3 rounded-xl border border-gray-200 bg-gray-50 px-3 py-3 text-sm text-gray-700">
                                <input wire:model.defer="createAccountForm.wholesale_enabled" type="checkbox" class="rounded border-gray-300 text-violet-600 focus:ring-violet-500" />
                                Wholesale enabled
                            </label>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Base plan</label>
                                <select wire:model.defer="createAccountForm.base_subscription_plan" class="mt-2 w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-violet-500 focus:ring-violet-500">
                                    @foreach ($subscriptionPlanOptions as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Reports customer limit</label>
                                <input wire:model.defer="createAccountForm.reports_customer_limit" type="number" min="1" class="mt-2 w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-violet-500 focus:ring-violet-500" />
                                @error('reports_customer_limit') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <label class="flex items-center gap-3 rounded-xl border border-gray-200 bg-gray-50 px-3 py-3 text-sm text-gray-700">
                            <input wire:model.defer="createAccountForm.reports_subscription_enabled" type="checkbox" class="rounded border-gray-300 text-violet-600 focus:ring-violet-500" />
                            Reports add-on enabled
                        </label>

                        <button type="submit" class="w-full rounded-xl bg-violet-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-violet-700">
                            Create account
                        </button>
                    </form>
                </div>

                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Selected account</p>
                    <h3 class="mt-1 text-lg font-semibold text-gray-900">Manage live subscription and status</h3>

                    @if ($selectedAccountId && ! empty($selectedAccountSummary))
                        <div class="mt-4 rounded-xl border border-violet-100 bg-violet-50 p-4">
                            <p class="text-sm font-semibold text-violet-950">{{ $selectedAccountSummary['name'] }}</p>
                            <p class="mt-1 text-xs text-violet-700">{{ $selectedAccountSummary['slug'] }}</p>
                            <div class="mt-3 grid gap-2 sm:grid-cols-3">
                                <div class="rounded-lg bg-white px-3 py-2">
                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Type</p>
                                    <p class="mt-1 text-sm font-semibold text-gray-900">{{ $selectedAccountSummary['type'] }}</p>
                                </div>
                                <div class="rounded-lg bg-white px-3 py-2">
                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Status</p>
                                    <p class="mt-1 text-sm font-semibold text-gray-900">{{ $selectedAccountSummary['status'] }}</p>
                                </div>
                                <div class="rounded-lg bg-white px-3 py-2">
                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Connections</p>
                                    <p class="mt-1 text-sm font-semibold text-gray-900">{{ $selectedAccountSummary['approved_connections'] }}</p>
                                </div>
                            </div>
                        </div>

                        <form wire:submit.prevent="saveSelectedAccount" class="mt-4 space-y-4">
                            <div>
                                <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Account name</label>
                                <input wire:model.defer="manageAccountForm.name" type="text" class="mt-2 w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-violet-500 focus:ring-violet-500" />
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Slug</label>
                                <input wire:model.defer="manageAccountForm.slug" type="text" class="mt-2 w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-violet-500 focus:ring-violet-500" />
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Account type</label>
                                    <select wire:model.defer="manageAccountForm.account_type" class="mt-2 w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-violet-500 focus:ring-violet-500">
                                        @foreach ($accountTypeOptions as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Status</label>
                                    <select wire:model.defer="manageAccountForm.status" class="mt-2 w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-violet-500 focus:ring-violet-500">
                                        @foreach ($accountStatusOptions as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2">
                                <label class="flex items-center gap-3 rounded-xl border border-gray-200 bg-gray-50 px-3 py-3 text-sm text-gray-700">
                                    <input wire:model.defer="manageAccountForm.retail_enabled" type="checkbox" class="rounded border-gray-300 text-violet-600 focus:ring-violet-500" />
                                    Retail enabled
                                </label>
                                <label class="flex items-center gap-3 rounded-xl border border-gray-200 bg-gray-50 px-3 py-3 text-sm text-gray-700">
                                    <input wire:model.defer="manageAccountForm.wholesale_enabled" type="checkbox" class="rounded border-gray-300 text-violet-600 focus:ring-violet-500" />
                                    Wholesale enabled
                                </label>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Base plan</label>
                                    <select wire:model.defer="manageAccountForm.base_subscription_plan" class="mt-2 w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-violet-500 focus:ring-violet-500">
                                        @foreach ($subscriptionPlanOptions as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Reports customer limit</label>
                                    <input wire:model.defer="manageAccountForm.reports_customer_limit" type="number" min="1" class="mt-2 w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-violet-500 focus:ring-violet-500" />
                                </div>
                            </div>

                            <label class="flex items-center gap-3 rounded-xl border border-gray-200 bg-gray-50 px-3 py-3 text-sm text-gray-700">
                                <input wire:model.defer="manageAccountForm.reports_subscription_enabled" type="checkbox" class="rounded border-gray-300 text-violet-600 focus:ring-violet-500" />
                                Reports add-on enabled
                            </label>

                            <button type="submit" class="w-full rounded-xl bg-gray-900 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-gray-800">
                                Save governance changes
                            </button>
                        </form>
                    @else
                        <div class="mt-4 rounded-xl border border-dashed border-gray-200 bg-gray-50 p-4 text-sm text-gray-600">
                            Select an account from the directory to manage status, capabilities, and subscription settings.
                        </div>
                    @endif

                    @if (! empty($latestGovernanceAction))
                        <div class="mt-4 rounded-xl border border-emerald-100 bg-emerald-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">{{ $latestGovernanceAction['label'] }}</p>
                            <p class="mt-1 text-sm font-semibold text-emerald-950">{{ $latestGovernanceAction['summary'] }}</p>
                            <p class="mt-2 text-xs leading-5 text-emerald-800">{{ $latestGovernanceAction['note'] }}</p>
                        </div>
                    @endif
                </div>

                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Reports add-on</p>
                    <h3 class="mt-1 text-lg font-semibold text-gray-900">Live tier summary</h3>

                    <div class="mt-4 space-y-3">
                        @forelse ($reportAddOnTiers as $tier)
                            <div class="rounded-xl border border-dashed border-gray-200 bg-gray-50 p-4">
                                <p class="text-sm font-semibold text-gray-900">{{ $tier['label'] }}</p>
                                <p class="mt-1 text-sm text-violet-700">{{ $tier['summary'] }}</p>
                                <p class="mt-2 text-xs leading-5 text-gray-600">{{ $tier['note'] }}</p>
                            </div>
                        @empty
                            <div class="rounded-xl border border-dashed border-gray-200 bg-gray-50 p-4">
                                <p class="text-sm font-semibold text-gray-900">No live report tiers yet</p>
                                <p class="mt-1 text-sm text-violet-700">Active report subscriptions will appear here once they exist.</p>
                            </div>
                        @endforelse
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
