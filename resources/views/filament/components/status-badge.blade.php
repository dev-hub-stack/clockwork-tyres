<x-filament::badge 
    :color="$status->getColor()" 
    :icon="$status->getIcon()"
    size="lg"
>
    {{ $status->getLabel() }}
</x-filament::badge>
