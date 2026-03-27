<x-filament-panels::page>
    <style>
        .report-page {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }
        .report-card {
            border: 1px solid #e2e8f0;
            border-radius: 24px;
            background: #fff;
            padding: 24px 28px;
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.06);
        }
        .report-card h1 {
            margin: 10px 0 0;
            font-size: 42px;
            line-height: 1;
            font-weight: 800;
            color: #0f172a;
        }
        .report-kicker {
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: #db2777;
        }
        .report-copy {
            margin-top: 10px;
            max-width: 900px;
            font-size: 15px;
            line-height: 1.7;
            color: #475569;
        }
        .profit-order-table-wrap {
            overflow-x: auto;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
        }
        .profit-order-table {
            width: 100%;
            min-width: 860px;
            border-collapse: collapse;
            font-size: 14px;
            color: #1e293b;
        }
        .profit-order-table th,
        .profit-order-table td {
            border: 1px solid #dbe4f0;
            padding: 12px 14px;
        }
        .profit-order-table thead {
            background: #dce8ff;
        }
        .profit-order-table tbody tr:nth-child(even) {
            background: #fafcff;
        }
        .profit-order-table tfoot {
            background: #f8fafc;
            font-weight: 700;
        }
    </style>

    <div class="report-page">
        <section class="report-card">
            <p class="report-kicker">Reports / Profit Reports</p>
            <h1>Profit by Order</h1>
            <p class="report-copy">This view lists invoice-level totals and recorded gross profit for the selected month range.</p>
        </section>

        <x-report-toolbar
            :start-month="$toolbar['startMonth']"
            :end-month="$toolbar['endMonth']"
            :sort="$toolbar['sort']"
            :channel="$toolbar['channel']"
            :dealer-id="$toolbar['dealerId']"
            :user-id="$toolbar['userId']"
            :dealers="$toolbar['dealers']"
            :users="$toolbar['users']"
            :show-dealer-filter="$toolbar['showDealerFilter']"
            :show-user-filter="$toolbar['showUserFilter']"
            :show-channel-filter="$toolbar['showChannelFilter']"
            :sort-options="$toolbar['sortOptions']"
        />

        <div class="profit-order-table-wrap">
            <table class="profit-order-table">
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
                    @forelse ($rows as $row)
                        <tr>
                            <td>{{ $row['invoice_number'] }}</td>
                            <td>{{ $row['description'] }}</td>
                            <td>{{ $row['customer_name'] }}</td>
                            <td>{{ $row['issued_on'] }}</td>
                            <td>{{ number_format($row['value'], 2) }}</td>
                            <td>{{ number_format($row['profit'], 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="padding: 32px; text-align: center; color: #64748b;">No invoices matched the selected filters.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($rows->isNotEmpty())
                    <tfoot>
                        <tr>
                            <td colspan="4">TOTAL</td>
                            <td>{{ number_format($totals['value'], 2) }}</td>
                            <td>{{ number_format($totals['profit'], 2) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</x-filament-panels::page>