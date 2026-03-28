<x-filament-panels::page>
    @php
        $detailMap = [];
        foreach ($rows as $row) {
            foreach ($row['months'] as $monthKey => $monthData) {
                if (! empty($monthData['details'])) {
                    $detailMap[$row['label'] . '||' . $monthKey] = $monthData['details'];
                }
            }
        }
    @endphp

    @include('filament.pages.reports.partials.report-shell', [
        'kicker' => $kicker,
        'titleText' => $titleText,
        'description' => $description,
        'toolbar' => $toolbar,
        'months' => $months,
        'rows' => $rows,
        'labelHeader' => $labelHeader,
        'quantityHeader' => $quantityHeader ?? 'Qty',
        'mode' => 'sales',
    ])

    <style>
        .sales-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.46);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 90;
            padding: 24px;
        }
        .sales-modal-overlay.is-open {
            display: flex;
        }
        .sales-modal {
            width: min(920px, 100%);
            max-height: 80vh;
            overflow: auto;
            border-radius: 18px;
            background: #fff;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.24);
        }
        .sales-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            padding: 18px 22px;
            border-bottom: 1px solid #e2e8f0;
        }
        .sales-modal-close {
            border: 0;
            background: transparent;
            font-size: 28px;
            line-height: 1;
            cursor: pointer;
            color: #475569;
        }
        .sales-modal-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        .sales-modal-table th,
        .sales-modal-table td {
            padding: 12px 18px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
        }
        .sales-modal-table thead {
            background: #f8fafc;
        }
    </style>

    <div id="sales-qty-modal" class="sales-modal-overlay" aria-hidden="true">
        <div class="sales-modal">
            <div class="sales-modal-header">
                <h3 id="sales-qty-modal-title" style="margin: 0; font-size: 28px; font-weight: 800; color: #0f172a;">Sales details</h3>
                <button type="button" class="sales-modal-close" onclick="window.closeSalesQtyModal()">×</button>
            </div>
            <div style="padding: 8px 0 18px;">
                <table class="sales-modal-table">
                    <thead>
                        <tr>
                            <th>Invoice</th>
                            <th>Customer</th>
                            <th>Qty</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody id="sales-qty-modal-body"></tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        window.salesQtyDetails = @json($detailMap);

        window.openSalesQtyModal = function (dimensionLabel, monthKey) {
            const modal = document.getElementById('sales-qty-modal');
            const title = document.getElementById('sales-qty-modal-title');
            const body = document.getElementById('sales-qty-modal-body');
            const details = window.salesQtyDetails[`${dimensionLabel}||${monthKey}`] || [];
            const monthLabel = monthKey.split('-').reverse().join('/');

            title.textContent = `${dimensionLabel} · ${monthLabel}`;
            body.innerHTML = details.length
                ? details.map((detail) => `
                    <tr>
                        <td>${detail.invoice}</td>
                        <td>${detail.customer}</td>
                        <td>${detail.qty_sold}</td>
                        <td>${detail.date_sold}</td>
                    </tr>
                `).join('')
                : '<tr><td colspan="4" style="text-align:center;color:#64748b;padding:28px;">No sales details found.</td></tr>';

            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
        };

        window.closeSalesQtyModal = function () {
            const modal = document.getElementById('sales-qty-modal');
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
        };

        document.addEventListener('click', function (event) {
            const trigger = event.target.closest('.sales-qty-trigger');
            if (trigger) {
                window.openSalesQtyModal(trigger.dataset.dimension, trigger.dataset.month);
                return;
            }

            const overlay = event.target.closest('#sales-qty-modal');
            if (overlay && event.target === overlay) {
                window.closeSalesQtyModal();
            }
        });
    </script>
</x-filament-panels::page>