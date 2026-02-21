@php
    $record = $this->record;
    $colorMap = [
        'gray'    => ['bg' => '#f3f4f6', 'text' => '#6b7280', 'border' => '#d1d5db'],
        'blue'    => ['bg' => '#eff6ff', 'text' => '#3b82f6', 'border' => '#93c5fd'],
        'green'   => ['bg' => '#f0fdf4', 'text' => '#16a34a', 'border' => '#86efac'],
        'purple'  => ['bg' => '#faf5ff', 'text' => '#9333ea', 'border' => '#d8b4fe'],
        'orange'  => ['bg' => '#fff7ed', 'text' => '#ea580c', 'border' => '#fdba74'],
        'info'    => ['bg' => '#ecfeff', 'text' => '#0891b2', 'border' => '#67e8f9'],
        'warning' => ['bg' => '#fffbeb', 'text' => '#d97706', 'border' => '#fcd34d'],
        'success' => ['bg' => '#f0fdf4', 'text' => '#16a34a', 'border' => '#86efac'],
        'danger'  => ['bg' => '#fef2f2', 'text' => '#dc2626', 'border' => '#fca5a5'],
    ];
    $histories = $record->histories()->latest()->get();
@endphp

<div style="position:relative; padding-left:2rem;">
    {{-- Vertical line --}}
    <div style="position:absolute;left:0.85rem;top:1.25rem;bottom:1.25rem;width:2px;background:#e5e7eb;"></div>

    @forelse($histories as $history)
        @php
            $c = $colorMap[$history->action_type->getColor()] ?? $colorMap['gray'];
        @endphp

        <div style="position:relative; margin-bottom:1rem; display:flex; align-items:flex-start; gap:0.875rem;">
            {{-- Dot --}}
            <div style="position:absolute;left:-2rem;width:1.75rem;height:1.75rem;border-radius:50%;background:{{ $c['bg'] }};border:2px solid {{ $c['border'] }};display:flex;align-items:center;justify-content:center;flex-shrink:0;z-index:1;">
                <x-filament::icon :icon="$history->action_type->getIcon()" style="width:0.875rem;height:0.875rem;color:{{ $c['text'] }};" />
            </div>

            {{-- Card --}}
            <div style="flex:1;background:#fff;border:1px solid #e5e7eb;border-left:4px solid {{ $c['border'] }};border-radius:0.5rem;padding:0.75rem 1rem;box-shadow:0 1px 2px rgba(0,0,0,.04);">
                {{-- Header row --}}
                <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.5rem;margin-bottom:0.375rem;">
                    <div style="display:flex;align-items:center;gap:0.5rem;">
                        <span style="font-weight:600;font-size:0.875rem;color:#111827;">
                            {{ $history->action_type->getLabel() }}
                        </span>
                        <span style="font-size:0.7rem;font-weight:500;padding:0.1rem 0.5rem;border-radius:9999px;background:{{ $c['bg'] }};color:{{ $c['text'] }};border:1px solid {{ $c['border'] }};">
                            {{ $history->action_type->value }}
                        </span>
                    </div>
                    <span style="font-size:0.7rem;color:#9ca3af;white-space:nowrap;">
                        {{ $history->created_at->format('d M Y, H:i') }}
                        &nbsp;&bull;&nbsp;{{ $history->created_at->diffForHumans() }}
                    </span>
                </div>

                {{-- Description --}}
                <p style="font-size:0.8125rem;color:#4b5563;margin:0 0 0.375rem;">
                    {{ $history->description }}
                </p>

                {{-- Metadata --}}
                @if($history->metadata)
                    <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:0.375rem;padding:0.5rem 0.625rem;font-size:0.75rem;margin-bottom:0.375rem;">
                        @if(isset($history->metadata['url']))
                            <a href="{{ $history->metadata['url'] }}" target="_blank"
                               style="color:#6366f1;text-decoration:none;display:flex;align-items:center;gap:0.25rem;word-break:break-all;">
                                <x-filament::icon icon="heroicon-o-link" style="width:0.75rem;height:0.75rem;flex-shrink:0;" />
                                {{ $history->metadata['url'] }}
                            </a>
                        @endif
                        @if(isset($history->metadata['notes']))
                            <p style="margin:0.25rem 0 0;color:#374151;">{{ $history->metadata['notes'] }}</p>
                        @endif
                    </div>
                @endif

                {{-- Footer --}}
                <div style="display:flex;align-items:center;gap:0.375rem;font-size:0.7rem;color:#9ca3af;">
                    <x-filament::icon icon="heroicon-o-user" style="width:0.75rem;height:0.75rem;" />
                    <span>{{ $history->user->name ?? 'System' }}</span>
                </div>
            </div>
        </div>
    @empty
        <div style="text-align:center;padding:2rem;color:#9ca3af;">
            <p style="margin:0;">No activity recorded yet</p>
        </div>
    @endforelse
</div>
