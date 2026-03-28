<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $payload['title'] }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #0f172a;
            font-size: 12px;
        }
        h1 {
            margin: 0 0 8px;
            font-size: 24px;
        }
        p {
            margin: 0 0 6px;
        }
        .meta {
            margin: 0 0 18px;
            color: #475569;
        }
        .section-title {
            margin: 18px 0 8px;
            font-size: 16px;
            font-weight: bold;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th,
        td {
            border: 1px solid #cbd5e1;
            padding: 6px 8px;
            text-align: center;
        }
        th:first-child,
        td:first-child {
            text-align: left;
        }
        thead th {
            background: #e2e8f0;
        }
        tfoot th,
        tfoot td {
            background: #f8fafc;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <h1>{{ $payload['title'] }}</h1>
    <p>{{ $payload['description'] }}</p>
    <div class="meta">
        <p>Range: {{ $payload['filters']['startMonth'] }} to {{ $payload['filters']['endMonth'] }}</p>
        <p>Channel: {{ ucfirst((string) $payload['filters']['channel']) }}</p>
        @if (! empty($payload['filters']['brand']))
            <p>Brand: {{ $payload['filters']['brand'] }}</p>
        @endif
        @if (! empty($payload['filters']['category']))
            <p>Category: {{ $payload['filters']['category'] }}</p>
        @endif
        @if (! empty($payload['filters']['search']))
            <p>Search: {{ $payload['filters']['search'] }}</p>
        @endif
        @if (! empty($payload['filters']['dealer']))
            <p>Dealer: {{ $payload['filters']['dealer'] }}</p>
        @endif
        @if (! empty($payload['filters']['user']))
            <p>User: {{ $payload['filters']['user'] }}</p>
        @endif
    </div>

    @if ($payload['type'] === 'pivot')
        <table>
            <thead>
                @if ($payload['mode'] === 'profit')
                    <tr>
                        <th>{{ $payload['labelHeader'] }}</th>
                        @foreach ($payload['months'] as $month)
                            <th>{{ $month['label'] }}</th>
                        @endforeach
                        <th>Total</th>
                    </tr>
                @else
                    <tr>
                        <th rowspan="2">{{ $payload['labelHeader'] }}</th>
                        @foreach ($payload['months'] as $month)
                            <th colspan="{{ $payload['mode'] === 'inventory' ? 2 : 2 }}">{{ $month['label'] }}</th>
                        @endforeach
                        <th colspan="2">Total</th>
                    </tr>
                    <tr>
                        @foreach ($payload['months'] as $month)
                            @if ($payload['mode'] === 'inventory')
                                <th>Added</th>
                                <th>Sold</th>
                            @else
                                <th>Qty</th>
                                <th>Value</th>
                            @endif
                        @endforeach
                        @if ($payload['mode'] === 'inventory')
                            <th>Added</th>
                            <th>Sold</th>
                        @else
                            <th>Qty</th>
                            <th>Value</th>
                        @endif
                    </tr>
                @endif
            </thead>
            <tbody>
                @foreach ($payload['rows'] as $row)
                    <tr>
                        <td>{{ $row['label'] }}</td>
                        @if ($payload['mode'] === 'profit')
                            @foreach ($payload['months'] as $month)
                                <td>{{ number_format((float) ($row['months'][$month['key']]['profit'] ?? 0), 2) }}</td>
                            @endforeach
                            <td>{{ number_format((float) $row['total_profit'], 2) }}</td>
                        @elseif ($payload['mode'] === 'inventory')
                            @foreach ($payload['months'] as $month)
                                <td>{{ number_format((int) ($row['months'][$month['key']]['added'] ?? 0)) }}</td>
                                <td>{{ number_format((int) ($row['months'][$month['key']]['sold'] ?? 0)) }}</td>
                            @endforeach
                            <td>{{ number_format((int) $row['total_added']) }}</td>
                            <td>{{ number_format((int) $row['total_sold']) }}</td>
                        @else
                            @foreach ($payload['months'] as $month)
                                <td>{{ number_format((int) ($row['months'][$month['key']]['qty'] ?? 0)) }}</td>
                                <td>{{ number_format((float) ($row['months'][$month['key']]['value'] ?? 0), 2) }}</td>
                            @endforeach
                            <td>{{ number_format((int) $row['total_qty']) }}</td>
                            <td>{{ number_format((float) $row['total_value'], 2) }}</td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    @elseif ($payload['type'] === 'orders')
        <table>
            <thead>
                <tr>
                    <th>Invoice</th>
                    <th>Description</th>
                    <th>Customer</th>
                    <th>Date</th>
                    <th>Value</th>
                    <th>Profit</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($payload['rows'] as $row)
                    <tr>
                        <td>{{ $row['invoice_number'] }}</td>
                        <td>{{ $row['description'] }}</td>
                        <td>{{ $row['customer_name'] }}</td>
                        <td>{{ $row['issued_on'] }}</td>
                        <td>{{ number_format((float) $row['value'], 2) }}</td>
                        <td>{{ number_format((float) $row['profit'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="4">TOTAL</th>
                    <th>{{ number_format((float) $payload['totals']['value'], 2) }}</th>
                    <th>{{ number_format((float) $payload['totals']['profit'], 2) }}</th>
                </tr>
            </tfoot>
        </table>
    @elseif ($payload['type'] === 'team')
        <div class="section-title">Comparison Table</div>
        <table>
            <thead>
                <tr>
                    <th rowspan="2">User</th>
                    @foreach ($payload['months'] as $month)
                        <th colspan="2">{{ $month['label'] }}</th>
                    @endforeach
                    <th colspan="2">Total</th>
                </tr>
                <tr>
                    @foreach ($payload['months'] as $month)
                        <th>Qty</th>
                        <th>Value</th>
                    @endforeach
                    <th>Qty</th>
                    <th>Value</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($payload['rows'] as $row)
                    <tr>
                        <td>{{ $row['label'] }}</td>
                        @foreach ($payload['months'] as $month)
                            <td>{{ number_format((int) ($row['months'][$month['key']]['qty'] ?? 0)) }}</td>
                            <td>{{ number_format((float) ($row['months'][$month['key']]['value'] ?? 0), 2) }}</td>
                        @endforeach
                        <td>{{ number_format((int) $row['total_qty']) }}</td>
                        <td>{{ number_format((float) $row['total_value'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="section-title">{{ $payload['selectedUserName'] ? $payload['selectedUserName'] . ' Detail' : 'User Detail' }}</div>
        <table>
            <thead>
                <tr>
                    <th>Invoice</th>
                    <th>Description</th>
                    <th>Customer</th>
                    <th>Date</th>
                    <th>Value</th>
                    <th>Profit</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($payload['detailRows'] as $row)
                    <tr>
                        <td>{{ $row['invoice_number'] }}</td>
                        <td>{{ $row['description'] }}</td>
                        <td>{{ $row['customer_name'] }}</td>
                        <td>{{ $row['issued_on'] }}</td>
                        <td>{{ number_format((float) $row['value'], 2) }}</td>
                        <td>{{ number_format((float) $row['profit'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="4">TOTAL</th>
                    <th>{{ number_format((float) $payload['detailTotals']['value'], 2) }}</th>
                    <th>{{ number_format((float) $payload['detailTotals']['profit'], 2) }}</th>
                </tr>
            </tfoot>
        </table>
    @endif
</body>
</html>