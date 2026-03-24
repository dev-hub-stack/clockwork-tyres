@props([
    'labelHeader' => 'Label',
    'months' => collect(),
    'rows' => collect(),
    'mode' => 'sales',
])

@php
    $totalQty = $rows->sum('total_qty');
    $totalValue = $rows->sum('total_value');
    $totalProfit = $rows->sum('total_profit');
    $formatValue = static function (float $value): string {
        $decimals = abs($value - round($value)) < 0.00001 ? 0 : 2;
        return number_format($value, $decimals);
    };
    $totalAdded = $rows->sum('total_added');
    $totalSold = $rows->sum('total_sold');
@endphp

<div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
    <style>
        .report-table-wrap {
            overflow-x: auto;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
        }
        .report-table {
            width: 100%;
            min-width: 980px;
            border-collapse: collapse;
            font-size: 14px;
            color: #1e293b;
        }
        .report-table th,
        .report-table td {
            border: 1px solid #dbe4f0;
            padding: 12px 14px;
        }
        .report-table thead tr:first-child {
            background: #dce8ff;
            color: #0f172a;
        }
        .report-table thead tr:last-child {
            background: #edf4ff;
            color: #334155;
        }
        .report-table tbody tr:nth-child(even) {
            background: #fafcff;
        }
        .report-table tfoot tr {
            background: #f8fafc;
            font-weight: 700;
        }
        .report-table th {
            font-weight: 700;
            text-align: center;
        }
        .report-table td:first-child,
        .report-table th:first-child {
            text-align: left;
            min-width: 220px;
        }
        .report-table td:not(:first-child) {
            text-align: center;
        }
        .report-table-empty {
            padding: 42px;
            text-align: center;
            color: #64748b;
        }
    </style>

<div class="report-table-wrap">
    <table class="report-table">
        <thead>
            @if ($mode === 'profit')
                <tr class="bg-slate-100 text-slate-900">
                    <th class="min-w-52 border-b border-r border-slate-200 px-4 py-4 text-left font-semibold">{{ $labelHeader }}</th>
                    @foreach ($months as $month)
                        <th class="border-b border-r border-slate-200 px-4 py-3 text-center font-semibold">{{ $month['label'] }}</th>
                    @endforeach
                    <th class="border-b px-4 py-3 text-center font-semibold">Total</th>
                </tr>
            @elseif ($mode === 'inventory')
                <tr class="bg-slate-100 text-slate-900">
                    <th rowspan="2" class="min-w-52 border-b border-r border-slate-200 px-4 py-4 text-left font-semibold">{{ $labelHeader }}</th>
                    @foreach ($months as $month)
                        <th colspan="2" class="border-b border-r border-slate-200 px-4 py-3 text-center font-semibold">{{ $month['label'] }}</th>
                    @endforeach
                    <th colspan="2" class="border-b px-4 py-3 text-center font-semibold">Total</th>
                </tr>
                <tr class="bg-slate-50 text-slate-700">
                    @foreach ($months as $month)
                        <th class="border-b border-r border-slate-200 px-4 py-2 text-center font-medium">Added</th>
                        <th class="border-b border-r border-slate-200 px-4 py-2 text-center font-medium">Sold</th>
                    @endforeach
                    <th class="border-b border-slate-200 px-4 py-2 text-center font-medium">Added</th>
                    <th class="border-b border-slate-200 px-4 py-2 text-center font-medium">Sold</th>
                </tr>
            @else
                <tr class="bg-slate-100 text-slate-900">
                    <th rowspan="2" class="min-w-52 border-b border-r border-slate-200 px-4 py-4 text-left font-semibold">{{ $labelHeader }}</th>
                    @foreach ($months as $month)
                        <th colspan="2" class="border-b border-r border-slate-200 px-4 py-3 text-center font-semibold">{{ $month['label'] }}</th>
                    @endforeach
                    <th colspan="2" class="border-b px-4 py-3 text-center font-semibold">Total</th>
                </tr>
                <tr class="bg-slate-50 text-slate-700">
                    @foreach ($months as $month)
                        <th class="border-b border-r border-slate-200 px-4 py-2 text-center font-medium">Qty</th>
                        <th class="border-b border-r border-slate-200 px-4 py-2 text-center font-medium">Value</th>
                    @endforeach
                    <th class="border-b border-slate-200 px-4 py-2 text-center font-medium">Qty</th>
                    <th class="border-b border-slate-200 px-4 py-2 text-center font-medium">Value</th>
                </tr>
            @endif
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr class="border-b border-slate-100 odd:bg-white even:bg-slate-50/50">
                    <td class="border-r border-slate-200 px-4 py-3 font-medium">{{ $row['label'] }}</td>
                    @if ($mode === 'profit')
                        @foreach ($months as $month)
                            @php($monthData = $row['months'][$month['key']] ?? ['profit' => 0])
                            <td class="border-r border-slate-200 px-4 py-3 text-center">{{ $formatValue((float) $monthData['profit']) }}</td>
                        @endforeach
                        <td class="px-4 py-3 text-center font-semibold">{{ $formatValue((float) $row['total_profit']) }}</td>
                    @elseif ($mode === 'inventory')
                        @foreach ($months as $month)
                            @php($monthData = $row['months'][$month['key']] ?? ['added' => 0, 'sold' => 0, 'details' => []])
                            <td class="border-r border-slate-200 px-4 py-3 text-center">{{ number_format($monthData['added']) }}</td>
                            <td class="border-r border-slate-200 px-4 py-3 text-center">
                                @if (! empty($monthData['details']))
                                    <button
                                        type="button"
                                        class="inventory-sold-trigger"
                                        data-dimension="{{ e($row['label']) }}"
                                        data-month="{{ $month['key'] }}"
                                        style="border: 0; background: transparent; color: #2563eb; font-weight: 700; text-decoration: underline; cursor: pointer;"
                                    >{{ number_format($monthData['sold']) }}</button>
                                @else
                                    {{ number_format($monthData['sold']) }}
                                @endif
                            </td>
                        @endforeach
                        <td class="px-4 py-3 text-center font-semibold">{{ number_format($row['total_added']) }}</td>
                        <td class="px-4 py-3 text-center font-semibold">{{ number_format($row['total_sold']) }}</td>
                    @else
                        @foreach ($months as $month)
                            @php($monthData = $row['months'][$month['key']] ?? ['qty' => 0, 'value' => 0])
                            <td class="border-r border-slate-200 px-4 py-3 text-center">{{ number_format($monthData['qty']) }}</td>
                            <td class="border-r border-slate-200 px-4 py-3 text-center">{{ $formatValue((float) $monthData['value']) }}</td>
                        @endforeach
                        <td class="px-4 py-3 text-center font-semibold">{{ number_format($row['total_qty']) }}</td>
                        <td class="px-4 py-3 text-center font-semibold">{{ $formatValue((float) $row['total_value']) }}</td>
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $mode === 'profit' ? $months->count() + 2 : ($months->count() * 2) + 3 }}" class="report-table-empty">No invoice data matched the selected filters.</td>
                </tr>
            @endforelse
        </tbody>
        @if ($rows->isNotEmpty())
            <tfoot>
                <tr class="bg-slate-100 text-slate-900">
                    <th class="border-r border-slate-200 px-4 py-4 text-left font-semibold">TOTAL</th>
                    @if ($mode === 'profit')
                        @foreach ($months as $month)
                            @php($monthProfit = $rows->sum(fn (array $row) => $row['months'][$month['key']]['profit'] ?? 0))
                            <th class="border-r border-slate-200 px-4 py-4 text-center font-semibold">{{ $formatValue((float) $monthProfit) }}</th>
                        @endforeach
                        <th class="px-4 py-4 text-center font-semibold">{{ $formatValue((float) $totalProfit) }}</th>
                    @elseif ($mode === 'inventory')
                        @foreach ($months as $month)
                            @php($monthAdded = $rows->sum(fn (array $row) => $row['months'][$month['key']]['added'] ?? 0))
                            @php($monthSold = $rows->sum(fn (array $row) => $row['months'][$month['key']]['sold'] ?? 0))
                            <th class="border-r border-slate-200 px-4 py-4 text-center font-semibold">{{ number_format($monthAdded) }}</th>
                            <th class="border-r border-slate-200 px-4 py-4 text-center font-semibold">{{ number_format($monthSold) }}</th>
                        @endforeach
                        <th class="px-4 py-4 text-center font-semibold">{{ number_format($totalAdded) }}</th>
                        <th class="px-4 py-4 text-center font-semibold">{{ number_format($totalSold) }}</th>
                    @else
                        @foreach ($months as $month)
                            @php
                                $monthQty = $rows->sum(fn (array $row) => $row['months'][$month['key']]['qty'] ?? 0);
                                $monthValue = $rows->sum(fn (array $row) => $row['months'][$month['key']]['value'] ?? 0);
                            @endphp
                            <th class="border-r border-slate-200 px-4 py-4 text-center font-semibold">{{ number_format($monthQty) }}</th>
                            <th class="border-r border-slate-200 px-4 py-4 text-center font-semibold">{{ $formatValue((float) $monthValue) }}</th>
                        @endforeach
                        <th class="px-4 py-4 text-center font-semibold">{{ number_format($totalQty) }}</th>
                        <th class="px-4 py-4 text-center font-semibold">{{ $formatValue((float) $totalValue) }}</th>
                    @endif
                </tr>
            </tfoot>
        @endif
    </table>
</div>