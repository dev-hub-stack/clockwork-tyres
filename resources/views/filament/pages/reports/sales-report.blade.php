<x-filament-panels::page>
    <style>
        .report-page {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }
        .report-page-hero {
            border: 1px solid #e2e8f0;
            border-radius: 24px;
            background: #fff;
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

    <div class="report-page">
        <section class="report-page-hero">
            <div class="report-page-hero-row">
                <div>
                    <p class="report-page-kicker">{{ $kicker }}</p>
                    <h1 class="report-page-title">{{ $titleText }}</h1>
                    <p class="report-page-copy">{{ $description }}</p>
                </div>

                <a href="{{ \App\Filament\Pages\Reports\ReportsIndex::getUrl() }}" class="report-page-back">
                    Back to Reports
                </a>
            </div>
        </section>

        <x-report-toolbar
            :start-month="$toolbar['startMonth']"
            :end-month="$toolbar['EndMonth'] ?? $toolbar['endMonth']"
            :sort="$toolbar['sort']"
            :channel="$toolbar['channel']"
            :dealer-id="$toolbar['dealerId']"
            :user-id="$toolbar['userId']"
            :dealers="$toolbar['dealers']"
            :users="$toolbar['users']"
            :show-dealer-filter="$toolbar['showDealerFilter']"
            :show-user-filter="$toolbar['showUserFilter']"
            :show-channel-filter="$toolbar['showChannelFilter']"
        />

        <x-report-table :label-header="$labelHeader" :months="$months" :rows="$rows" />
    </div>
</x-filament-panels::page>