@props([
    'startMonth',
    'endMonth',
    'sort' => 'alpha',
    'channel' => 'all',
    'dealerId' => null,
    'userId' => null,
    'dealers' => [],
    'users' => [],
    'showDealerFilter' => false,
    'showUserFilter' => false,
    'showChannelFilter' => true,
    'sortOptions' => [
        'alpha' => 'Alphabetical A-Z',
        'qty_desc' => 'Quantity High to Low',
        'value_desc' => 'Value High to Low',
    ],
])

<form method="GET" action="{{ request()->url() }}" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
    <style>
        .report-toolbar {
            border: 1px solid #e2e8f0;
            background: #fff;
            border-radius: 16px;
            padding: 18px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
        }
        .report-toolbar-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 14px;
        }
        .report-toolbar label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #334155;
        }
        .report-toolbar input,
        .report-toolbar select {
            margin-top: 6px;
            width: 100%;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            padding: 10px 12px;
            font-size: 14px;
            color: #0f172a;
            background: #fff;
        }
        .report-toolbar input:focus,
        .report-toolbar select:focus {
            outline: none;
            border-color: #db2777;
            box-shadow: 0 0 0 3px rgba(219, 39, 119, 0.12);
        }
        .report-toolbar-actions {
            margin-top: 14px;
            display: flex;
            gap: 12px;
            align-items: center;
        }
        .report-toolbar-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            padding: 10px 16px;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }
        .report-toolbar-button-primary {
            background: #db2777;
            color: #fff;
            border: 1px solid #db2777;
        }
        .report-toolbar-button-secondary {
            background: #fff;
            color: #475569;
            border: 1px solid #cbd5e1;
        }
    </style>

<form method="GET" action="{{ request()->url() }}" class="report-toolbar">
    <div class="report-toolbar-grid">
        <label>
            Start Month
            <input type="month" name="start_month" value="{{ $startMonth }}">
        </label>

        <label>
            End Month
            <input type="month" name="end_month" value="{{ $endMonth }}">
        </label>

        <label>
            Sort
            <select name="sort">
                @foreach ($sortOptions as $optionValue => $optionLabel)
                    <option value="{{ $optionValue }}" @selected($sort === $optionValue)>{{ $optionLabel }}</option>
                @endforeach
            </select>
        </label>

        @if ($showChannelFilter)
            <label>
                Channel
                <select name="channel">
                    <option value="all" @selected($channel === 'all')>All</option>
                    <option value="retail" @selected($channel === 'retail')>Retail</option>
                    <option value="wholesale" @selected($channel === 'wholesale')>Wholesale</option>
                </select>
            </label>
        @endif

        @if ($showDealerFilter)
            <label>
                Dealer
                <select name="dealer_id">
                    <option value="">All Dealers</option>
                    @foreach ($dealers as $id => $name)
                        <option value="{{ $id }}" @selected((string) $dealerId === (string) $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </label>
        @endif

        @if ($showUserFilter)
            <label>
                User
                <select name="user_id">
                    <option value="">All Users</option>
                    @foreach ($users as $id => $name)
                        <option value="{{ $id }}" @selected((string) $userId === (string) $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </label>
        @endif
    </div>

    <div class="report-toolbar-actions">
        <button type="submit" class="report-toolbar-button report-toolbar-button-primary">
            Apply Filters
        </button>

        <a href="{{ request()->url() }}" class="report-toolbar-button report-toolbar-button-secondary">
            Reset
        </a>
    </div>
</form>