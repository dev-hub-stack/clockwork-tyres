<style>
    .report-page {
        display: flex;
        flex-direction: column;
        gap: 24px;
    }
    .report-page-hero {
        border: 1px solid #e2e8f0;
        border-radius: 24px;
        background: linear-gradient(135deg, #fff8fb 0%, #ffffff 48%, #f8fafc 100%);
        padding: 24px 28px;
        box-shadow: 0 12px 30px rgba(15, 23, 42, 0.06);
    }
    .report-page-hero-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        flex-wrap: wrap;
    }
    .report-page-kicker {
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.18em;
        text-transform: uppercase;
        color: #db2777;
    }
    .report-page-title {
        margin: 10px 0 0;
        font-size: 42px;
        line-height: 1;
        font-weight: 800;
        color: #0f172a;
    }
    .report-page-copy {
        margin-top: 10px;
        max-width: 900px;
        font-size: 15px;
        line-height: 1.7;
        color: #475569;
    }
    .report-page-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 18px;
    }
    .report-page-chip {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 9px 12px;
        border-radius: 999px;
        border: 1px solid #fbcfe8;
        background: rgba(255, 255, 255, 0.88);
        color: #9d174d;
        font-size: 13px;
        font-weight: 700;
    }
    .report-page-back {
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
    @media (max-width: 768px) {
        .report-page-title {
            font-size: 32px;
        }
    }
</style>

@php
    $kicker = $kicker ?? 'Reports';
    $titleText = $titleText ?? 'Report';
    $description = $description ?? 'Review the selected report output and adjust the filters as needed.';
    $toolbar = array_merge([
        'startMonth' => now()->format('Y-m'),
        'endMonth' => now()->format('Y-m'),
        'sort' => 'alpha',
        'channel' => 'all',
        'brand' => '',
        'category' => '',
        'search' => '',
        'dealerId' => null,
        'userId' => null,
        'brands' => [],
        'categories' => [],
        'dealers' => [],
        'users' => [],
        'showBrandFilter' => false,
        'showCategoryFilter' => false,
        'showSearchFilter' => false,
        'searchPlaceholder' => 'Search',
        'showDealerFilter' => false,
        'showUserFilter' => false,
        'showChannelFilter' => true,
        'sortOptions' => [
            'alpha' => 'Alphabetical A-Z',
            'qty_desc' => 'Quantity High to Low',
            'value_desc' => 'Value High to Low',
        ],
    ], $toolbar ?? []);
    $labelHeader = $labelHeader ?? 'Label';
    $quantityHeader = $quantityHeader ?? 'Qty';
    $months = $months ?? collect();
    $rows = $rows ?? collect();
@endphp

<div class="report-page">
    <section class="report-page-hero">
        <div class="report-page-hero-row">
            <div>
                <p class="report-page-kicker">{{ $kicker }}</p>
                <h1 class="report-page-title">{{ $titleText }}</h1>
                <p class="report-page-copy">{{ $description }}</p>
                <div class="report-page-meta">
                    <span class="report-page-chip">Range: {{ $toolbar['startMonth'] }} to {{ $toolbar['endMonth'] }}</span>
                    @if (($toolbar['showChannelFilter'] ?? false) && ($toolbar['channel'] ?? 'all') !== 'all')
                        <span class="report-page-chip">Channel: {{ ucfirst($toolbar['channel']) }}</span>
                    @endif
                    @if (($toolbar['showBrandFilter'] ?? false) && ($toolbar['brand'] ?? '') !== '')
                        <span class="report-page-chip">Brand: {{ $toolbar['brand'] }}</span>
                    @endif
                    @if (($toolbar['showCategoryFilter'] ?? false) && ($toolbar['category'] ?? '') !== '')
                        <span class="report-page-chip">Category: {{ $toolbar['category'] }}</span>
                    @endif
                    @if (($toolbar['showDealerFilter'] ?? false) && ! empty($toolbar['dealerId']) && isset($toolbar['dealers'][$toolbar['dealerId']]))
                        <span class="report-page-chip">Dealer: {{ $toolbar['dealers'][$toolbar['dealerId']] }}</span>
                    @endif
                    @if (($toolbar['showUserFilter'] ?? false) && ! empty($toolbar['userId']) && isset($toolbar['users'][$toolbar['userId']]))
                        <span class="report-page-chip">User: {{ $toolbar['users'][$toolbar['userId']] }}</span>
                    @endif
                    @if (($toolbar['showSearchFilter'] ?? false) && ($toolbar['search'] ?? '') !== '')
                        <span class="report-page-chip">Search: {{ $toolbar['search'] }}</span>
                    @endif
                </div>
            </div>

            <a href="{{ \App\Filament\Pages\Reports\ReportsIndex::getUrl() }}" class="report-page-back">
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
        :sort-options="$toolbar['sortOptions'] ?? ['alpha' => 'Alphabetical A-Z', 'qty_desc' => 'Quantity High to Low', 'value_desc' => 'Value High to Low']"
    />

    <x-report-table :label-header="$labelHeader" :months="$months" :rows="$rows" :mode="$mode ?? 'sales'" :quantity-header="$quantityHeader" />
</div>