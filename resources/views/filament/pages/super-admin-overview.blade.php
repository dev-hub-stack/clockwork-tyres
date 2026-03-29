<x-filament-panels::page>
    <style>
        .super-admin-shell {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }
        .super-admin-hero {
            border: 1px solid #dbe4ea;
            border-radius: 24px;
            background: linear-gradient(135deg, #f8fafc 0%, #ffffff 48%, #eef2ff 100%);
            padding: 24px 28px;
            box-shadow: 0 14px 32px rgba(15, 23, 42, 0.06);
        }
        .super-admin-kicker {
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: #1d4ed8;
        }
        .super-admin-title {
            margin: 10px 0 0;
            font-size: 36px;
            line-height: 1.05;
            font-weight: 800;
            color: #0f172a;
        }
        .super-admin-copy {
            margin-top: 10px;
            max-width: 900px;
            color: #475569;
            line-height: 1.7;
            font-size: 15px;
        }
        .metric-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
        }
        .metric-card {
            border-radius: 20px;
            border: 1px solid #e2e8f0;
            background: #ffffff;
            padding: 18px 20px;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.04);
        }
        .metric-card h3 {
            margin: 0;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: #64748b;
        }
        .metric-card .metric-value {
            margin-top: 10px;
            font-size: 34px;
            font-weight: 800;
            color: #0f172a;
        }
        .metric-card p {
            margin: 8px 0 0;
            color: #64748b;
            font-size: 14px;
            line-height: 1.6;
        }
        .section-panel {
            border-radius: 20px;
            border: 1px solid #e2e8f0;
            background: #ffffff;
            padding: 20px;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.04);
        }
        .section-panel h2 {
            margin: 0;
            font-size: 20px;
            font-weight: 700;
            color: #0f172a;
        }
        .section-panel .section-copy {
            margin-top: 8px;
            color: #64748b;
            font-size: 14px;
            line-height: 1.6;
        }
        .two-col-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 16px;
            margin-top: 16px;
        }
        .mini-card {
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            padding: 16px;
        }
        .mini-card h3 {
            margin: 0;
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
        }
        .mini-card .mini-value {
            margin-top: 8px;
            font-size: 30px;
            font-weight: 800;
            color: #1d4ed8;
        }
        .mini-card p {
            margin: 8px 0 0;
            color: #64748b;
            line-height: 1.6;
            font-size: 14px;
        }
        .placeholder-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 16px;
            margin-top: 16px;
        }
        .placeholder-item {
            border-radius: 16px;
            border: 1px dashed #cbd5e1;
            background: #f8fafc;
            padding: 16px;
        }
        .placeholder-item h3 {
            margin: 0;
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
        }
        .placeholder-item p {
            margin: 8px 0 0;
            color: #64748b;
            line-height: 1.6;
            font-size: 14px;
        }
        .read-only-note {
            border-left: 4px solid #1d4ed8;
            background: #eff6ff;
            color: #1e3a8a;
            border-radius: 14px;
            padding: 14px 16px;
            font-size: 14px;
            line-height: 1.6;
        }
    </style>

    <section class="super-admin-shell">
        <div class="super-admin-hero">
            <p class="super-admin-kicker">Administration / Governance</p>
            <h1 class="super-admin-title">Super Admin Overview</h1>
            <p class="super-admin-copy">
                This is the governance surface George asked for: a clean overview of accounts, subscriptions,
                reports add-ons, and network health. It is intentionally read-only and does not expose product
                editing or inventory operations.
            </p>
        </div>

        <div class="read-only-note">
            Read-only surface: accounts, subscriptions, reports add-ons, and analytics placeholders only.
            No product creation, no inventory editing, and no catalog maintenance actions.
        </div>

        <section class="metric-grid">
            @foreach ($governanceCards as $card)
                <article class="metric-card">
                    <h3>{{ $card['label'] }}</h3>
                    <div class="metric-value">{{ $card['value'] }}</div>
                    <p>{{ $card['note'] }}</p>
                </article>
            @endforeach
        </section>

        <section class="section-panel">
            <h2>Account governance</h2>
            <p class="section-copy">
                Overview of the account types the platform is now built to support.
            </p>

            <div class="two-col-grid">
                @foreach ($accountBreakdown as $item)
                    <article class="mini-card">
                        <h3>{{ $item['label'] }}</h3>
                        <div class="mini-value">{{ $item['value'] }}</div>
                        <p>{{ $item['note'] }}</p>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="section-panel">
            <h2>Subscription oversight</h2>
            <p class="section-copy">
                Platform subscription counts and the reports add-on are tracked here for super admin review.
            </p>

            <div class="two-col-grid">
                @foreach ($subscriptionBreakdown as $item)
                    <article class="mini-card">
                        <h3>{{ $item['label'] }}</h3>
                        <div class="mini-value">{{ $item['value'] }}</div>
                        <p>{{ $item['note'] }}</p>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="section-panel">
            <h2>Supplier network</h2>
            <p class="section-copy">
                Connection visibility for the retailer-to-supplier network, without any procurement editing tools on this surface.
            </p>

            <div class="two-col-grid">
                @foreach ($connectionSummary as $item)
                    <article class="mini-card">
                        <h3>{{ $item['label'] }}</h3>
                        <div class="mini-value">{{ $item['value'] }}</div>
                        <p>{{ $item['note'] }}</p>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="section-panel">
            <h2>Analytics placeholders</h2>
            <p class="section-copy">
                These spaces are reserved for the real governance charts once George confirms the reporting visuals and KPI rules.
            </p>

            <div class="placeholder-list">
                @foreach ($analyticsPlaceholders as $item)
                    <article class="placeholder-item">
                        <h3>{{ $item['title'] }}</h3>
                        <p>{{ $item['copy'] }}</p>
                    </article>
                @endforeach
            </div>
        </section>
    </section>
</x-filament-panels::page>
