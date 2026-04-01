<x-filament-panels::page>
    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-1">
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <h3 class="text-base font-semibold text-gray-950">Current Business Account</h3>

                @if (! empty($this->currentAccountSummary))
                    <dl class="mt-4 space-y-4 text-sm text-gray-600">
                        <div>
                            <dt class="font-medium text-gray-950">Name</dt>
                            <dd class="mt-1">{{ $this->currentAccountSummary['name'] ?? 'N/A' }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-gray-950">Slug</dt>
                            <dd class="mt-1">{{ $this->currentAccountSummary['slug'] ?? 'N/A' }}</dd>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-1">
                            <div>
                                <dt class="font-medium text-gray-950">Type</dt>
                                <dd class="mt-1 capitalize">{{ $this->currentAccountSummary['account_type'] ?? 'retailer' }}</dd>
                            </div>
                            <div>
                                <dt class="font-medium text-gray-950">Status</dt>
                                <dd class="mt-1 capitalize">{{ $this->currentAccountSummary['status'] ?? 'active' }}</dd>
                            </div>
                        </div>
                    </dl>
                @else
                    <p class="mt-4 text-sm text-gray-600">No active business account is currently selected.</p>
                @endif
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <h3 class="text-base font-semibold text-gray-950">How it works</h3>
                <ul class="mt-4 space-y-3 text-sm text-gray-600">
                    <li>Switching context updates quotes, invoices, procurement queues, and other business-scoped CRM data.</li>
                    <li>Your user session stays the same. This does not impersonate another business login.</li>
                    <li>Super admin remains a governance role and does not use this context switcher.</li>
                </ul>
            </div>
        </div>

        <div class="space-y-6 xl:col-span-2">
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <form wire:submit="save" class="space-y-6">
                    {{ $this->form }}

                    <div class="flex items-center justify-between gap-3 border-t border-gray-200 pt-4">
                        <p class="text-sm text-gray-500">
                            Available accounts: {{ count($this->availableAccountSummaries) }}
                        </p>

                        <x-filament::button type="submit">
                            Switch Account
                        </x-filament::button>
                    </div>
                </form>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <h3 class="text-base font-semibold text-gray-950">Available Business Accounts</h3>
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    @foreach ($this->availableAccountSummaries as $account)
                        <div class="rounded-lg border border-gray-200 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-medium text-gray-950">{{ $account['name'] }}</p>
                                    <p class="mt-1 text-sm text-gray-500">{{ $account['slug'] }}</p>
                                </div>

                                @if (($this->currentAccountSummary['id'] ?? null) === ($account['id'] ?? null))
                                    <span class="rounded-full bg-primary-50 px-2.5 py-1 text-xs font-medium text-primary-700">
                                        Active
                                    </span>
                                @endif
                            </div>

                            <div class="mt-4 grid gap-3 text-sm text-gray-600 sm:grid-cols-2">
                                <div>
                                    <p class="font-medium text-gray-950">Type</p>
                                    <p class="mt-1 capitalize">{{ $account['account_type'] ?? 'retailer' }}</p>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-950">Capabilities</p>
                                    <p class="mt-1">
                                        {{ ! empty($account['retail_enabled']) ? 'Retail' : 'No retail' }}
                                        /
                                        {{ ! empty($account['wholesale_enabled']) ? 'Wholesale' : 'No wholesale' }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
