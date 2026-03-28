@props([
    'labelHeader' => 'Label',
    'months' => collect(),
    'rows' => collect(),
    'mode' => 'sales',
    'quantityHeader' => 'Qty',
])

@php
    $isProfit = $mode === 'profit';
    $isInventory = $mode === 'inventory';
    $totalQty = $rows->sum('total_qty');
    $totalValue = $rows->sum('total_value');
    $totalProfit = $rows->sum('total_profit');
    $totalAdded = $rows->sum('total_added');
    $totalSold = $rows->sum('total_sold');
    $emptyColspan = $isProfit ? $months->count() + 2 : ($months->count() * 2) + 3;
    $formatValue = static function (float $value): string {
        $decimals = abs($value - round($value)) < 0.00001 ? 0 : 2;
        return number_format($value, $decimals);
    };
@endphp

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
            @if ($isProfit)
                <tr>
                    <th>{{ $labelHeader }}</th>
                    @foreach ($months as $month)
                        <th>{{ $month['label'] }}</th>
                    @endforeach
                    <th>Total</th>
                </tr>
            @else
                <tr>
                    <th rowspan="2">{{ $labelHeader }}</th>
                    @foreach ($months as $month)
                        <th colspan="2">{{ $month['label'] }}</th>
                    @endforeach
                    <th colspan="2">Total</th>
                </tr>
                <tr>
                    @foreach ($months as $month)
                        @if ($isInventory)
                            <th>Added</th>
                            <th>Sold</th>
                        @else
                            <th>{{ $quantityHeader }}</th>
                            <th>Value</th>
                        @endif
                    @endforeach
                    @if ($isInventory)
                        <th>Added</th>
                        <th>Sold</th>
                    @else
                        <th>{{ $quantityHeader }}</th>
                        <th>Value</th>
                    @endif
                </tr>
            @endif
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['label'] }}</td>
                    @foreach ($months as $month)
                        @php
                            $monthData = $row['months'][$month['key']] ?? [];
                        @endphp

                        @if ($isProfit)
                            <td>{{ $formatValue((float) ($monthData['profit'] ?? 0)) }}</td>
                        @elseif ($isInventory)
                            <td>{{ number_format((int) ($monthData['added'] ?? 0)) }}</td>
                            <td>
                                @if (! empty($monthData['details']))
                                    <button
                                        type="button"
                                        class="inventory-sold-trigger"
                                        data-dimension="{{ e($row['label']) }}"
                                        data-month="{{ $month['key'] }}"
                                        style="border: 0; background: transparent; color: #2563eb; font-weight: 700; text-decoration: underline; cursor: pointer;"
                                    >{{ number_format((int) ($monthData['sold'] ?? 0)) }}</button>
                                @else
                                    {{ number_format((int) ($monthData['sold'] ?? 0)) }}
                                @endif
                            </td>
                        @else
                            <td>
                                @if (! empty($monthData['details']))
                                    <button
                                        type="button"
                                        class="sales-qty-trigger"
                                        data-dimension="{{ e($row['label']) }}"
                                        data-month="{{ $month['key'] }}"
                                        style="border: 0; background: transparent; color: #2563eb; font-weight: 700; text-decoration: underline; cursor: pointer;"
                                    >{{ number_format((int) ($monthData['qty'] ?? 0)) }}</button>
                                @else
                                    {{ number_format((int) ($monthData['qty'] ?? 0)) }}
                                @endif
                            </td>
                            <td>{{ $formatValue((float) ($monthData['value'] ?? 0)) }}</td>
                        @endif
                    @endforeach

                    @if ($isProfit)
                        <td>{{ $formatValue((float) ($row['total_profit'] ?? 0)) }}</td>
                    @elseif ($isInventory)
                        <td>{{ number_format((int) ($row['total_added'] ?? 0)) }}</td>
                        <td>{{ number_format((int) ($row['total_sold'] ?? 0)) }}</td>
                    @else
                        <td>{{ number_format((int) ($row['total_qty'] ?? 0)) }}</td>
                        <td>{{ $formatValue((float) ($row['total_value'] ?? 0)) }}</td>
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $emptyColspan }}" class="report-table-empty">No invoice data matched the selected filters.</td>
                </tr>
            @endforelse
        </tbody>
        @if ($rows->isNotEmpty())
            <tfoot>
                <tr>
                    <th>TOTAL</th>
                    @foreach ($months as $month)
                        @php
                            $monthProfit = $rows->sum(function (array $row) use ($month) {
                                return $row['months'][$month['key']]['profit'] ?? 0;
                            });
                            $monthAdded = $rows->sum(function (array $row) use ($month) {
                                return $row['months'][$month['key']]['added'] ?? 0;
                            });
                            $monthSold = $rows->sum(function (array $row) use ($month) {
                                return $row['months'][$month['key']]['sold'] ?? 0;
                            });
                            $monthQty = $rows->sum(function (array $row) use ($month) {
                                return $row['months'][$month['key']]['qty'] ?? 0;
                            });
                            $monthValue = $rows->sum(function (array $row) use ($month) {
                                return $row['months'][$month['key']]['value'] ?? 0;
                            });
                        @endphp

                        @if ($isProfit)
                            <th>{{ $formatValue((float) $monthProfit) }}</th>
                        @elseif ($isInventory)
                            <th>{{ number_format((int) $monthAdded) }}</th>
                            <th>{{ number_format((int) $monthSold) }}</th>
                        @else
                            <th>{{ number_format((int) $monthQty) }}</th>
                            <th>{{ $formatValue((float) $monthValue) }}</th>
                        @endif
                    @endforeach

                    @if ($isProfit)
                        <th>{{ $formatValue((float) $totalProfit) }}</th>
                    @elseif ($isInventory)
                        <th>{{ number_format((int) $totalAdded) }}</th>
                        <th>{{ number_format((int) $totalSold) }}</th>
                    @else
                        <th>{{ number_format((int) $totalQty) }}</th>
                        <th>{{ $formatValue((float) $totalValue) }}</th>
                    @endif
                </tr>
            </tfoot>
        @endif
    </table>
</div>