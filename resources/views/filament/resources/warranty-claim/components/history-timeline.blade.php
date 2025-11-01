<div class="space-y-4">
    @forelse($getRecord()->histories()->latest()->take(10)->get() as $history)
        <div class="flex gap-4">
            <!-- Timeline Icon -->
            <div class="flex flex-col items-center">
                <div class="flex items-center justify-center w-10 h-10 rounded-full bg-{{ $history->action_type->getColor() }}-100 dark:bg-{{ $history->action_type->getColor() }}-900">
                    <x-filament::icon 
                        :icon="$history->action_type->getIcon()" 
                        class="w-5 h-5 text-{{ $history->action_type->getColor() }}-600 dark:text-{{ $history->action_type->getColor() }}-400"
                    />
                </div>
                @if(!$loop->last)
                    <div class="w-0.5 h-full bg-gray-200 dark:bg-gray-700 mt-2"></div>
                @endif
            </div>

            <!-- Timeline Content -->
            <div class="flex-1 pb-6">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <div class="flex items-center gap-2">
                            <h4 class="font-semibold text-gray-900 dark:text-white">
                                {{ $history->action_type->getLabel() }}
                            </h4>
                            <x-filament::badge :color="$history->action_type->getColor()" size="sm">
                                {{ $history->action_type->value }}
                            </x-filament::badge>
                        </div>
                        
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            {{ $history->description }}
                        </p>

                        @if($history->metadata)
                            <div class="mt-2 p-2 bg-gray-50 dark:bg-gray-800 rounded text-xs">
                                @if(isset($history->metadata['url']))
                                    <a href="{{ $history->metadata['url'] }}" 
                                       target="_blank" 
                                       class="text-primary-600 hover:underline flex items-center gap-1">
                                        <x-filament::icon icon="heroicon-o-link" class="w-3 h-3" />
                                        {{ $history->metadata['url'] }}
                                    </a>
                                @endif
                                @if(isset($history->metadata['notes']))
                                    <p class="mt-1">{{ $history->metadata['notes'] }}</p>
                                @endif
                            </div>
                        @endif

                        <div class="mt-2 flex items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
                            <span class="flex items-center gap-1">
                                <x-filament::icon icon="heroicon-o-user" class="w-3 h-3" />
                                {{ $history->user->name ?? 'System' }}
                            </span>
                            <span class="flex items-center gap-1">
                                <x-filament::icon icon="heroicon-o-clock" class="w-3 h-3" />
                                {{ $history->created_at->format('M d, Y H:i') }}
                                <span class="text-gray-400">({{ $history->created_at->diffForHumans() }})</span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="text-center py-8 text-gray-500 dark:text-gray-400">
            <x-filament::icon icon="heroicon-o-clock" class="w-12 h-12 mx-auto mb-2 text-gray-400" />
            <p>No activity recorded yet</p>
        </div>
    @endforelse

    @if($getRecord()->histories()->count() > 10)
        <div class="text-center pt-4 border-t border-gray-200 dark:border-gray-700">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Showing latest 10 of {{ $getRecord()->histories()->count() }} activities
            </p>
        </div>
    @endif
</div>
