<x-filament-panels::page>
    @php
        $formatValue = static function (float $value): string {
            $decimals = abs($value - round($value)) < 0.00001 ? 0 : 2;
            return number_format($value, $decimals);
        };
        $comparisonQty = $rows->sum('total_qty');
        $comparisonValue = $rows->sum('total_value');
    @endphp

    <style>
        .team-report-page {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }
        .team-report-card {
            border: 1px solid #e2e8f0;
            border-radius: 24px;
            background: #fff;
            padding: 24px 28px;
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.06);
        }
        .team-report-hero-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }
        .team-report-kicker {
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: #db2777;
        }
        .team-report-title {
            margin: 10px 0 0;
            font-size: 42px;
            line-height: 1;
            font-weight: 800;
            color: #0f172a;
        }
        .team-report-copy {
            margin-top: 10px;
            max-width: 920px;
            font-size: 15px;
            line-height: 1.7;
            color: #475569;
        }
        .team-report-back {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            border: 1px solid #cbd5e1;
            padding: 10px 16px;
            font-size: 14px;
            font-weight: 700;
            color: #475569;
            text-decoration: none;
            background: #fff;
        }
        .team-report-table-wrap {
            overflow-x: auto;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
        }
        .team-report-table {
            width: 100%;
            min-width: 980px;
            border-collapse: collapse;
            font-size: 14px;
            color: #1e293b;
        }
        .team-report-table th,
        .team-report-table td {
            border: 1px solid #dbe4f0;
            padding: 12px 14px;
        }
        .team-report-table thead tr:first-child {
            background: #dce8ff;
            color: #0f172a;
        }
        .team-report-table thead tr:last-child {
            background: #edf4ff;
            color: #334155;
        }
        .team-report-table tbody tr:nth-child(even) {
            background: #fafcff;
        }
        .team-report-table tfoot tr,
        .team-detail-table tfoot tr {
            background: #f8fafc;
            font-weight: 700;
        }
        .team-report-table th,
        .team-detail-table th {
            font-weight: 700;
            text-align: center;
        }
        .team-report-table td:first-child,
        .team-report-table th:first-child,
        .team-detail-table td:first-child,
        .team-detail-table th:first-child {
            text-align: left;
        }
        .team-report-user-link {
            color: #2563eb;
            font-weight: 700;
            text-decoration: underline;
        }
        .team-report-user-current {
            color: #0f172a;
            font-weight: 700;
            text-decoration: none;
        }
        .team-detail-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }
        .team-detail-title {
            margin: 0;
            font-size: 28px;
            font-weight: 800;
            color: #0f172a;
        }
        .team-detail-copy {
            margin: 6px 0 0;
            font-size: 14px;
            color: #64748b;
        }
        .team-detail-table-wrap {
            overflow-x: auto;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
        }
        .team-detail-table {
            width: 100%;
            min-width: 860px;
            border-collapse: collapse;
            font-size: 14px;
            color: #1e293b;
        }
        .team-detail-table th,
        .team-detail-table td {
            border: 1px solid #dbe4f0;
            padding: 12px 14px;
        }
        .team-detail-table thead {
            background: #dce8ff;
        }
        .team-detail-table tbody tr:nth-child(even) {
            background: #fafcff;
        }
        .team-empty {
            padding: 32px;
            text-align: center;
            color: #64748b;
        }
        @media (max-width: 768px) {
            .team-report-title {
                font-size: 32px;
            }
        }
    </style>

    <div class="team-report-page">
        <section class="team-report-card">
            <div class="team-report-hero-row">
                <div>
                    <p class="team-report-kicker">Reports / Team Reports</p>
                    <h1 class="team-report-title">{{ $titleText }}</h1>
                    <p class="team-report-copy">{{ $description }}</p>
                </div>

                <a href="{{ \App\Filament\Pages\Reports\ReportsIndex::getUrl() }}" class="team-report-back">
                    Back to Reports
                </a>
            </div>
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

        <section class="team-report-card">
            <div class="team-detail-header">
                <div>
                    <h2 class="team-detail-title">Comparison Table</h2>
                    <p class="team-detail-copy">Click a user name to load invoice-level detail below.</p>
                </div>
            </div>

            <div class="team-report-table-wrap">
                <table class="team-report-table">
                    <thead>
                        <tr>
                            <th rowspan="2">User</th>
                            @foreach ($months as $month)
                                <th colspan="2">{{ $month['label'] }}</th>
                            @endforeach
                            <th colspan="2">Total</th>
                        </tr>
                        <tr>
                            @foreach ($months as $month)
                                <th>Qty</th>
                                <th>Value</th>
                            @endforeach
                            <th>Qty</th>
                            <th>Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                <td>
                                    @if ($row['user_id'] !== null)
                                        <a
                                            href="{{ request()->fullUrlWithQuery(['selected_user_id' => $row['user_id']]) }}"
                                            class="{{ $selectedUserId === $row['user_id'] ? 'team-report-user-current' : 'team-report-user-link' }}"
                                        >{{ $row['label'] }}</a>
                                    @else
                                        {{ $row['label'] }}
                                    @endif
                                </td>
                                @foreach ($months as $month)
                                    @php($monthData = $row['months'][$month['key']] ?? ['qty' => 0, 'value' => 0])
                                    <td style="text-align:center;">{{ number_format($monthData['qty']) }}</td>
                                    <td style="text-align:center;">{{ $formatValue((float) $monthData['value']) }}</td>
                                @endforeach
                                <td style="text-align:center;font-weight:700;">{{ number_format($row['total_qty']) }}</td>
                                <td style="text-align:center;font-weight:700;">{{ $formatValue((float) $row['total_value']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ ($months->count() * 2) + 3 }}" class="team-empty">No invoice data matched the selected filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($rows->isNotEmpty())
                        <tfoot>
                            <tr>
                                <th>TOTAL</th>
                                @foreach ($months as $month)
                                    @php
                                        $monthQty = $rows->sum(fn (array $row) => $row['months'][$month['key']]['qty'] ?? 0);
                                        $monthValue = $rows->sum(fn (array $row) => $row['months'][$month['key']]['value'] ?? 0);
                                    @endphp
                                    <th>{{ number_format($monthQty) }}</th>
                                    <th>{{ $formatValue((float) $monthValue) }}</th>
                                @endforeach
                                <th>{{ number_format($comparisonQty) }}</th>
                                <th>{{ $formatValue((float) $comparisonValue) }}</th>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </section>

        <section class="team-report-card">
            <div class="team-detail-header">
                <div>
                    <h2 class="team-detail-title">{{ $selectedUserName ? $selectedUserName . ' Detail' : 'User Detail' }}</h2>
                    <p class="team-detail-copy">Invoice-level totals and recorded gross profit for the selected representative.</p>
                </div>
            </div>

            <div class="team-detail-table-wrap">
                <table class="team-detail-table">
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
                        @forelse ($detailRows as $row)
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
                                <td colspan="6" class="team-empty">{{ $selectedUserName ? 'No invoices matched the selected filters for this user.' : 'Select a user from the comparison table to view invoice detail.' }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($detailRows->isNotEmpty())
                        <tfoot>
                            <tr>
                                <td colspan="4">TOTAL</td>
                                <td>{{ number_format($detailTotals['value'], 2) }}</td>
                                <td>{{ number_format($detailTotals['profit'], 2) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </section>
    </div>
</x-filament-panels::page>