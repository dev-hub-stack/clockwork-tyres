<style>
    .payment-header {
        background: #f8fafc;
        border-left: 4px solid #3b82f6;
        padding: 16px;
        border-radius: 8px;
        margin-bottom: 16px;
    }
    
    .payment-header h3 {
        font-size: 18px;
        font-weight: 600;
        color: #1f2937;
        margin: 0 0 4px 0;
    }
    
    .payment-header p {
        color: #6b7280;
        margin: 0;
        font-size: 14px;
    }
    
    .summary-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
        margin-bottom: 20px;
    }
    
    .summary-card {
        background: white;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 16px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }
    
    .summary-card h4 {
        font-size: 14px;
        color: #6b7280;
        margin: 0 0 8px 0;
        font-weight: 500;
    }
    
    .summary-card .amount {
        font-size: 24px;
        font-weight: 700;
        margin: 0;
    }
    
    .total-paid { color: #059669; }
    .payment-count { color: #2563eb; }
    .last-payment { color: #1f2937; }
    
    .payment-table {
        background: white;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }
    
    .payment-table table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .payment-table th {
        background: #f8fafc;
        padding: 12px 16px;
        text-align: left;
        font-size: 12px;
        font-weight: 600;
        color: #374151;
        border-bottom: 1px solid #e5e7eb;
    }
    
    .payment-table td {
        padding: 12px 16px;
        border-bottom: 1px solid #f3f4f6;
        font-size: 14px;
    }
    
    .payment-table tr:hover {
        background-color: #f9fafb;
    }
    
    .method-badge {
        background: #f3f4f6;
        color: #374151;
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 500;
    }
    
    .amount-green {
        color: #059669;
        font-weight: 600;
    }
    
    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: #6b7280;
    }
    
    .empty-state svg {
        width: 48px;
        height: 48px;
        margin-bottom: 16px;
        opacity: 0.5;
    }
</style>

<div class="payment-header">
    <h3>📄 Payment History</h3>
    <p>Complete record of all payments for this invoice</p>
</div>

@if($payments && $payments->count() > 0)
    <div class="summary-cards">
        <div class="summary-card">
            <h4>Total Paid</h4>
            <p class="amount total-paid">{{ number_format($payments->sum('amount'), 2) }}</p>
        </div>
        
        <div class="summary-card">
            <h4>Payments Count</h4>
            <p class="amount payment-count">{{ $payments->count() }}</p>
        </div>
        
        <div class="summary-card">
            <h4>Last Payment</h4>
            <p class="amount last-payment">
                @if($payments->first() && $payments->first()->payment_date)
                    {{ $payments->sortByDesc('payment_date')->first()->payment_date->format('M d, Y') }}
                @else
                    N/A
                @endif
            </p>
        </div>
    </div>
@endif

<div class="payment-table">
    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Bank Name</th>
                    <th>Reference</th>
                    <th>Recorded By</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                @forelse($payments as $payment)
                    <tr>
                        <td>{{ $payment->payment_date ? $payment->payment_date->format('M d, Y') : '-' }}</td>
                        <td><span class="amount-green">{{ number_format($payment->amount, 2) }}</span></td>
                        <td>
                            <span class="method-badge">
                                {{ ucfirst(str_replace('_', ' ', $payment->payment_method->value ?? $payment->payment_method)) }}
                            </span>
                        </td>
                        <td><strong>{{ $payment->bank_name ?? '-' }}</strong></td>
                        <td>{{ $payment->reference_number ?? '-' }}</td>
                        <td>{{ $payment->recordedBy->name ?? 'System' }}</td>
                        <td>{{ $payment->notes ?? '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">
                            <div class="empty-state">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                <p><strong>No payments recorded</strong></p>
                                <p>Payment history will appear here once payments are added</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
