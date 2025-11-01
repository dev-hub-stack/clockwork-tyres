<x-filament-widgets::widget>
    <x-filament::section
        heading="Activity History"
        icon="heroicon-o-clock"
        :collapsed="false"
    >
        @if($record)
            @include('filament.resources.warranty-claim.components.history-timeline', ['record' => $record])
        @else
            <div class="text-gray-500">No activity history available.</div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
