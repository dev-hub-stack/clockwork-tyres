<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Claim Overview Section --}}
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center justify-between">
                    <span>Claim Overview</span>
                </div>
            </x-slot>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <x-filament::section.heading class="text-sm font-medium text-gray-500 dark:text-gray-400">
                        Claim Number
                    </x-filament::section.heading>
                    <div class="mt-1 flex items-center gap-2">
                        <x-filament::icon icon="heroicon-o-hashtag" class="w-4 h-4 text-gray-400" />
                        <span class="text-lg font-bold">{{ $record->claim_number }}</span>
                    </div>
                </div>

                <div>
                    <x-filament::section.heading class="text-sm font-medium text-gray-500 dark:text-gray-400">
                        Status
                    </x-filament::section.heading>
                    <div class="mt-1">
                        <x-filament::badge :color="$record->status->getColor()" :icon="$record->status->getIcon()">
                            {{ $record->status->getLabel() }}
                        </x-filament::badge>
                    </div>
                </div>

                <div>
                    <x-filament::section.heading class="text-sm font-medium text-gray-500 dark:text-gray-400">
                        Claim Date
                    </x-filament::section.heading>
                    <div class="mt-1 flex items-center gap-2">
                        <x-filament::icon icon="heroicon-o-calendar" class="w-4 h-4 text-gray-400" />
                        <span>{{ $record->claim_date?->format('M d, Y') ?? 'N/A' }}</span>
                    </div>
                </div>

                <div>
                    <x-filament::section.heading class="text-sm font-medium text-gray-500 dark:text-gray-400">
                        Resolution Date
                    </x-filament::section.heading>
                    <div class="mt-1 flex items-center gap-2">
                        <x-filament::icon icon="heroicon-o-check-circle" class="w-4 h-4 text-gray-400" />
                        <span>{{ $record->resolution_date?->format('M d, Y') ?? 'Not resolved' }}</span>
                    </div>
                </div>
            </div>
        </x-filament::section>

        {{-- Customer Information --}}
        <x-filament::section>
            <x-slot name="heading">Customer Information</x-slot>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <x-filament::section.heading class="text-sm font-medium text-gray-500 dark:text-gray-400">
                        Customer
                    </x-filament::section.heading>
                    <div class="mt-1 flex items-center gap-2">
                        <x-filament::icon icon="heroicon-o-building-office-2" class="w-4 h-4 text-gray-400" />
                        <a href="/admin/customers/{{ $record->customer_id }}" class="text-primary-600 hover:underline">
                            {{ $record->customer->business_name ?? 'N/A' }}
                        </a>
                    </div>
                </div>

                @if($record->invoice_id)
                <div>
                    <x-filament::section.heading class="text-sm font-medium text-gray-500 dark:text-gray-400">
                        Invoice
                    </x-filament::section.heading>
                    <div class="mt-1 flex items-center gap-2">
                        <x-filament::icon icon="heroicon-o-document-text" class="w-4 h-4 text-gray-400" />
                        <a href="/admin/invoices/{{ $record->invoice_id }}" class="text-primary-600 hover:underline">
                            {{ $record->invoice->order_number ?? 'N/A' }}
                        </a>
                    </div>
                </div>
                @endif

                <div>
                    <x-filament::section.heading class="text-sm font-medium text-gray-500 dark:text-gray-400">
                        Warehouse
                    </x-filament::section.heading>
                    <div class="mt-1 flex items-center gap-2">
                        <x-filament::icon icon="heroicon-o-building-storefront" class="w-4 h-4 text-gray-400" />
                        <span>{{ $record->warehouse->warehouse_name ?? 'N/A' }}</span>
                    </div>
                </div>

                <div>
                    <x-filament::section.heading class="text-sm font-medium text-gray-500 dark:text-gray-400">
                        Sales Representative
                    </x-filament::section.heading>
                    <div class="mt-1 flex items-center gap-2">
                        <x-filament::icon icon="heroicon-o-user" class="w-4 h-4 text-gray-400" />
                        <span>{{ $record->representative->name ?? 'N/A' }}</span>
                    </div>
                </div>
            </div>
        </x-filament::section>

        {{-- Claimed Items --}}
        <x-filament::section>
            <x-slot name="heading">Claimed Items</x-slot>

            @include('filament.resources.warranty-claim.components.items-table', ['record' => $record])
        </x-filament::section>

        {{-- Notes --}}
        @if($record->notes || $record->internal_notes)
        <x-filament::section collapsible collapsed>
            <x-slot name="heading">Notes</x-slot>

            @if($record->notes)
            <div class="mb-4">
                <x-filament::section.heading class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">
                    Customer Notes
                </x-filament::section.heading>
                <div class="prose dark:prose-invert max-w-none">
                    {{ $record->notes }}
                </div>
            </div>
            @endif

            @if($record->internal_notes)
            <div>
                <x-filament::section.heading class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">
                    Internal Notes
                </x-filament::section.heading>
                <div class="prose dark:prose-invert max-w-none">
                    {{ $record->internal_notes }}
                </div>
            </div>
            @endif
        </x-filament::section>
        @endif

        {{-- Activity History --}}
        <x-filament::section>
            <x-slot name="heading">Activity History</x-slot>

            @include('filament.resources.warranty-claim.components.history-timeline', ['record' => $record])
        </x-filament::section>

        {{-- Metadata --}}
        <x-filament::section collapsible collapsed>
            <x-slot name="heading">Metadata</x-slot>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <x-filament::section.heading class="text-sm font-medium text-gray-500 dark:text-gray-400">
                        Created By
                    </x-filament::section.heading>
                    <div class="mt-1 flex items-center gap-2">
                        <x-filament::icon icon="heroicon-o-user-plus" class="w-4 h-4 text-gray-400" />
                        <span>{{ $record->createdBy->name ?? 'N/A' }}</span>
                    </div>
                </div>

                <div>
                    <x-filament::section.heading class="text-sm font-medium text-gray-500 dark:text-gray-400">
                        Created At
                    </x-filament::section.heading>
                    <div class="mt-1 flex items-center gap-2">
                        <x-filament::icon icon="heroicon-o-calendar" class="w-4 h-4 text-gray-400" />
                        <span>{{ $record->created_at->format('M d, Y H:i') }}</span>
                    </div>
                </div>

                <div>
                    <x-filament::section.heading class="text-sm font-medium text-gray-500 dark:text-gray-400">
                        Last Updated
                    </x-filament::section.heading>
                    <div class="mt-1 flex items-center gap-2">
                        <x-filament::icon icon="heroicon-o-arrow-path" class="w-4 h-4 text-gray-400" />
                        <span>{{ $record->updated_at->diffForHumans() }}</span>
                    </div>
                </div>

                @if($record->resolved_by)
                <div>
                    <x-filament::section.heading class="text-sm font-medium text-gray-500 dark:text-gray-400">
                        Resolved By
                    </x-filament::section.heading>
                    <div class="mt-1 flex items-center gap-2">
                        <x-filament::icon icon="heroicon-o-user-check" class="w-4 h-4 text-gray-400" />
                        <span>{{ $record->resolvedBy->name ?? 'N/A' }}</span>
                    </div>
                </div>
                @endif

                <div>
                    <x-filament::section.heading class="text-sm font-medium text-gray-500 dark:text-gray-400">
                        Total Items
                    </x-filament::section.heading>
                    <div class="mt-1 flex items-center gap-2">
                        <x-filament::icon icon="heroicon-o-cube" class="w-4 h-4 text-gray-400" />
                        <span>{{ $record->items->count() }} items</span>
                    </div>
                </div>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
