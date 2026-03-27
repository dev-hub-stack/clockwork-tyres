<x-filament-panels::page>
    @php
        $pageDescription = $pageDescription ?? 'Browse the available reporting families and use the summary filters to refresh the dashboard window.';
        $startMonth = $startMonth ?? now()->format('Y-m');
        $endMonth = $endMonth ?? now()->format('Y-m');
        $cards = $cards ?? [];
        $reports = $reports ?? [];

        $formatCardValue = static function ($card): string {
            if ($card['type'] === 'currency') {
                return 'AED ' . number_format((float) $card['value'], abs((float) $card['value'] - round((float) $card['value'])) < 0.00001 ? 0 : 2);
            }

            if ($card['type'] === 'placeholder') {
                return 'Coming soon';
            }

            return number_format((int) $card['value']);
        };
    @endphp

    <style>
        .reports-page {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }
        .reports-hero {
            border: 1px solid #e2e8f0;
            border-radius: 24px;
            padding: 28px;
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 50%, #fdf2f8 100%);
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.06);
        }
        .reports-hero-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 24px;
            align-items: flex-end;
            justify-content: space-between;
        }
        .reports-kicker {
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: #db2777;
        }
        .reports-title {
            margin: 10px 0 0;
            font-size: 46px;
            line-height: 1;
            font-weight: 800;
            color: #0f172a;
        }
        .reports-copy {
            margin-top: 12px;
            max-width: 920px;
            font-size: 15px;
            line-height: 1.7;
            color: #475569;
        }
        .reports-refresh-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 12px;
            min-width: min(100%, 420px);
            border: 1px solid #f1f5f9;
            background: rgba(255, 255, 255, 0.92);
            padding: 16px;
            border-radius: 18px;
        }
        .reports-refresh-form label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #334155;
        }
        .reports-refresh-form input {
            margin-top: 6px;
            width: 100%;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            padding: 10px 12px;
        }
        .reports-form-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            grid-column: 1 / -1;
        }
        .reports-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            padding: 10px 16px;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
        }
        .reports-button-primary {
            background: #db2777;
            color: #fff;
            border: 1px solid #db2777;
        }
        .reports-button-link {
            color: #475569;
        }
        .reports-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
        }
        .reports-card {
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            background: #fff;
            padding: 20px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
        }
        .reports-card-label {
            font-size: 14px;
            font-weight: 600;
            color: #64748b;
        }
        .reports-card-value {
            margin-top: 12px;
            font-size: 34px;
            line-height: 1.1;
            font-weight: 800;
            color: #0f172a;
        }
        .reports-sections {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 16px;
        }
        .reports-empty-state {
            border: 1px dashed #cbd5e1;
            border-radius: 18px;
            padding: 24px;
            background: #fff;
            color: #64748b;
            text-align: center;
            font-size: 14px;
            line-height: 1.7;
        }
        .reports-section {
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            background: #fff;
            padding: 20px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
        }
        .reports-section h2 {
            margin: 0;
            font-size: 20px;
            font-weight: 700;
            color: #0f172a;
        }
        .reports-section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }
        .reports-section-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 34px;
            height: 34px;
            padding: 0 10px;
            border-radius: 999px;
            background: #fdf2f8;
            color: #be185d;
            font-size: 13px;
            font-weight: 800;
        }
        .reports-links {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 16px;
        }
        .reports-link {
            display: block;
            border-radius: 14px;
            padding: 12px 14px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
        }
        .reports-link-enabled {
            border: 1px solid #f9a8d4;
            background: #fdf2f8;
            color: #be185d;
        }
        .reports-link-disabled {
            border: 1px dashed #cbd5e1;
            color: #64748b;
            background: #fff;
        }
        @media (max-width: 768px) {
            .reports-title {
                font-size: 34px;
            }
        }
    </style>

    <div class="reports-page">
        <section class="reports-hero">
            <div class="reports-hero-grid">
                <div>
                    <p class="reports-kicker">Reporting Module</p>
                    <h1 class="reports-title">Reports</h1>
                    <p class="reports-copy">{{ $pageDescription }}</p>
                </div>

                <form method="GET" action="{{ request()->url() }}" class="reports-refresh-form">
                    <label>
                        Start Month
                        <input type="month" name="start_month" value="{{ $startMonth }}">
                    </label>
                    <label>
                        End Month
                        <input type="month" name="end_month" value="{{ $endMonth }}">
                    </label>
                    <div class="reports-form-actions">
                        <button type="submit" class="reports-button reports-button-primary">Refresh Summary</button>
                        <a href="{{ request()->url() }}" class="reports-button reports-button-link">Reset</a>
                    </div>
                </form>
            </div>
        </section>

        <section class="reports-cards">
            @forelse ($cards as $card)
                <article class="reports-card">
                    <p class="reports-card-label">{{ $card['label'] }}</p>
                    <p class="reports-card-value">{{ $formatCardValue($card) }}</p>
                </article>
            @empty
                <div class="reports-empty-state">Summary cards are not available for the current context yet.</div>
            @endforelse
        </section>

        <section class="reports-sections">
            @forelse ($reports as $section => $items)
                <article class="reports-section">
                    <div class="reports-section-header">
                        <h2>{{ $section }}</h2>
                        <span class="reports-section-count">{{ collect($items)->where('enabled', true)->count() }}</span>
                    </div>
                    <div class="reports-links">
                        @foreach ($items as $item)
                            @if ($item['enabled'])
                                <a href="{{ $item['url'] }}" class="reports-link reports-link-enabled">{{ $item['label'] }}</a>
                            @else
                                <div class="reports-link reports-link-disabled">{{ $item['label'] }}</div>
                            @endif
                        @endforeach
                    </div>
                </article>
            @empty
                <div class="reports-empty-state">No reporting sections are available for the current user or filter state.</div>
            @endforelse
        </section>
    </div>
</x-filament-panels::page>