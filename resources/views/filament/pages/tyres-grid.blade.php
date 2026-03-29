<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-950">
            <div class="flex flex-col gap-3">
                <div class="flex flex-wrap items-center gap-2">
                    <p class="text-sm font-semibold uppercase tracking-wide text-amber-700">
                        {{ $category_definition['label'] ?? 'Tyres' }}
                    </p>
                    @if (($category_definition['launch_status'] ?? null) === 'launch')
                        <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold uppercase tracking-wide text-emerald-800">
                            Launch category
                        </span>
                    @endif
                </div>

                <div>
                    <h1 class="text-xl font-semibold">Tyres Grid</h1>
                    <p class="text-sm text-amber-900">
                        This is a separate tyres-only admin surface. George's sample sheet is still pending, so the field list and import mapping stay intentionally generic for now.
                    </p>
                </div>

                @if (!empty($pricing_levels))
                    <div class="flex flex-wrap gap-2">
                        @foreach ($pricing_levels as $pricing_level)
                            <span class="rounded-full bg-white px-2.5 py-1 text-xs font-medium text-amber-900 ring-1 ring-amber-200">
                                {{ $this->pricingLevelLabel($pricing_level) }}
                            </span>
                        @endforeach
                    </div>
                @endif

                @if (!empty($launch_notes))
                    <ul class="list-disc space-y-1 pl-5 text-sm text-amber-900">
                        @foreach ($launch_notes as $note)
                            <li>{{ ucfirst($note) }}.</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-200 px-4 py-3">
                <h2 class="text-lg font-semibold text-gray-900">Tyre catalogue scaffold</h2>
                <p class="text-sm text-gray-600">
                    Placeholder rows only. The launch grid will be wired to the final sheet once George shares the tyre import file.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">SKU</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Brand</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Pattern</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Size</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Load Index</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Speed Rating</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Retail Price</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Wholesale L1</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Wholesale L2</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Wholesale L3</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Note</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @forelse ($tyres_data as $row)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $row['sku'] ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ $row['brand'] ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ $row['pattern'] ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ $row['size'] ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ $row['load_index'] ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ $row['speed_rating'] ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">
                                    {{ isset($row['retail_price']) && $row['retail_price'] !== null ? number_format((float) $row['retail_price'], 2) : '-' }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700">
                                    {{ isset($row['wholesale_lvl1_price']) && $row['wholesale_lvl1_price'] !== null ? number_format((float) $row['wholesale_lvl1_price'], 2) : '-' }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700">
                                    {{ isset($row['wholesale_lvl2_price']) && $row['wholesale_lvl2_price'] !== null ? number_format((float) $row['wholesale_lvl2_price'], 2) : '-' }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700">
                                    {{ isset($row['wholesale_lvl3_price']) && $row['wholesale_lvl3_price'] !== null ? number_format((float) $row['wholesale_lvl3_price'], 2) : '-' }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ $row['availability_note'] ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="px-4 py-10 text-center text-sm text-gray-500">
                                    No tyre scaffold rows are loaded yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>
