<?php

namespace App\Modules\Orders\Models;

use App\Modules\Customers\Models\Customer;
use App\Models\User;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Orders\Enums\DocumentType;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Enums\QuoteStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        // Document type
        'document_type',
        
        // Quote fields
        'quote_number',
        'quote_status',
        
        // Order/Invoice fields
        'order_number',
        'order_status',
        'payment_status',
        'payment_method',
        'payment_gateway',
        
        // Relationships
        'customer_id',
        'warehouse_id',
        'representative_id',
        
        // External tracking
        'external_order_id',
        'external_source',
        
        // Financial
        'sub_total',
        'tax',
        'vat',
        'shipping',
        'discount',
        'total',
        'currency',
        'tax_type',
        'tax_inclusive',
        'channel',
        
        // Vehicle
        'vehicle_year',
        'vehicle_make',
        'vehicle_model',
        'vehicle_sub_model',
        
        // Conversion tracking
        'is_quote_converted',
        'converted_to_invoice_id',
        
        // Dates
        'issue_date',
        'valid_until',
        'sent_at',
        'approved_at',
        
        // Shipping
        'tracking_number',
        'tracking_url',
        'shipping_carrier',
        'shipped_at',
        
        // Payment tracking
        'paid_amount',
        'outstanding_amount',
        
        // Notes
        'order_notes',
        'internal_notes',
        'delivery_note',
        
        // Workflow
        'order_workflow_status',
        
        // Expense fields (matching old Reporting system)
        'cost_of_goods',
        'shipping_cost',
        'duty_amount',
        'delivery_fee',
        'installation_cost',
        'bank_fee',
        'credit_card_fee',
        'total_expenses',
        'gross_profit',
        'profit_margin',
        'expenses_recorded_at',
        'expenses_recorded_by',
    ];

    protected $casts = [
        'document_type' => DocumentType::class,
        'quote_status' => QuoteStatus::class,
        'order_status' => OrderStatus::class,
        'payment_status' => PaymentStatus::class,
        'tax_inclusive' => 'boolean',
        'is_quote_converted' => 'boolean',
        'sub_total' => 'decimal:2',
        'tax' => 'decimal:2',
        'vat' => 'decimal:2',
        'shipping' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'outstanding_amount' => 'decimal:2',
        'issue_date' => 'date',
        'valid_until' => 'date',
        'sent_at' => 'datetime',
        'approved_at' => 'datetime',
        'shipped_at' => 'datetime',
        
        // Expense fields
        'cost_of_goods' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'duty_amount' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'installation_cost' => 'decimal:2',
        'bank_fee' => 'decimal:2',
        'credit_card_fee' => 'decimal:2',
        'total_expenses' => 'decimal:2',
        'gross_profit' => 'decimal:2',
        'profit_margin' => 'decimal:2',
        'expenses_recorded_at' => 'datetime',
    ];

    /**
     * Get the customer that owns this order
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the warehouse for this order
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Get the sales representative for this order
     */
    public function representative(): BelongsTo
    {
        return $this->belongsTo(User::class, 'representative_id');
    }

    /**
     * Get all items for this order
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get all payments for this order
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get all item quantities through items
     */
    public function itemQuantities(): HasManyThrough
    {
        return $this->hasManyThrough(
            OrderItemQuantity::class,
            OrderItem::class,
            'order_id',
            'order_item_id'
        );
    }


    /**
     * Get the invoice this quote was converted to (if applicable)
     */
    public function convertedInvoice(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'converted_to_invoice_id');
    }

    /**
     * Scope: Get only quotes
     */
    public function scopeQuotes($query)
    {
        return $query->where('document_type', DocumentType::QUOTE);
    }

    /**
     * Scope: Get only invoices
     */
    public function scopeInvoices($query)
    {
        return $query->where('document_type', DocumentType::INVOICE);
    }

    /**
     * Scope: Get only orders
     */
    public function scopeOrders($query)
    {
        return $query->where('document_type', DocumentType::ORDER);
    }

    /**
     * Scope: Get pending orders
     */
    public function scopePending($query)
    {
        return $query->where('order_status', OrderStatus::PENDING);
    }

    /**
     * Scope: Get orders by external source
     */
    public function scopeFromSource($query, string $source)
    {
        return $query->where('external_source', $source);
    }

    /**
     * Check if this is a quote
     */
    public function isQuote(): bool
    {
        return $this->document_type === DocumentType::QUOTE;
    }

    /**
     * Check if this is an invoice
     */
    public function isInvoice(): bool
    {
        return $this->document_type === DocumentType::INVOICE;
    }

    /**
     * Check if this is an order
     */
    public function isOrder(): bool
    {
        return $this->document_type === DocumentType::ORDER;
    }

    /**
     * Check if quote can be converted to invoice
     */
    public function canConvertToInvoice(): bool
    {
        return $this->isQuote()
            && $this->quote_status === QuoteStatus::SENT
            && !$this->is_quote_converted;
    }

    /**
     * Check if order can be edited
     */
    public function canEdit(): bool
    {
        if ($this->isQuote()) {
            return $this->quote_status?->canEdit() ?? false;
        }
        
        return $this->order_status?->canEdit() ?? false;
    }

    /**
     * Check if order can be cancelled
     */
    public function canCancel(): bool
    {
        return $this->order_status?->canCancel() ?? false;
    }

    /**
     * Get display number based on document type
     */
    public function getDisplayNumberAttribute(): string
    {
        return $this->isQuote() && $this->quote_number 
            ? $this->quote_number 
            : $this->order_number;
    }

    /**
     * Get formatted total
     */
    public function getFormattedTotalAttribute(): string
    {
        return $this->currency . ' ' . number_format($this->total, 2);
    }

    /**
     * Recalculate payment status based on payments
     */
    public function recalculatePaymentStatus(): void
    {
        $totalPaid = $this->payments()->completed()->sum('amount');
        $this->paid_amount = $totalPaid;
        $this->outstanding_amount = max(0, $this->total - $totalPaid);

        // Update payment status
        if ($totalPaid >= $this->total) {
            $this->payment_status = PaymentStatus::PAID;
        } elseif ($totalPaid > 0) {
            $this->payment_status = PaymentStatus::PARTIAL;
        } else {
            $this->payment_status = PaymentStatus::PENDING;
        }

        $this->saveQuietly(); // Save without triggering events
    }

    /**
     * Get balance (outstanding amount)
     */
    public function getBalanceAttribute(): float
    {
        return (float) $this->outstanding_amount;
    }

    /**
     * Check if fully paid
     */
    public function isFullyPaid(): bool
    {
        return $this->payment_status === PaymentStatus::PAID;
    }

    /**
     * Check if partially paid
     */
    public function isPartiallyPaid(): bool
    {
        return $this->payment_status === PaymentStatus::PARTIAL;
    }

    /**
     * Check if payment is pending
     */
    public function isPaymentPending(): bool
    {
        return $this->payment_status === PaymentStatus::PENDING;
    }

    /**
     * Check if order is shipped
     */
    public function isShipped(): bool
    {
        return $this->order_status === OrderStatus::SHIPPED || $this->order_status === OrderStatus::COMPLETED;
    }

    /**
     * Check if order is completed
     */
    public function isCompleted(): bool
    {
        return $this->order_status === OrderStatus::COMPLETED;
    }

    /**
     * Mark as shipped
     */
    public function markAsShipped(string $trackingNumber, string $carrier, ?string $trackingUrl = null): void
    {
        $this->tracking_number = $trackingNumber;
        $this->shipping_carrier = $carrier;
        $this->tracking_url = $trackingUrl;
        $this->shipped_at = now();
        $this->order_status = OrderStatus::SHIPPED;
        $this->save();
    }

    /**
     * Mark as completed
     */
    public function markAsCompleted(): void
    {
        $this->order_status = OrderStatus::COMPLETED;
        $this->save();
    }
    
    /**
     * Record expenses and calculate profit (matching old Reporting system)
     * 
     * @param array $expenseData Array with expense field values
     * @return bool
     */
    public function recordExpenses(array $expenseData): bool
    {
        $this->cost_of_goods = $expenseData['cost_of_goods'] ?? 0;
        $this->shipping_cost = $expenseData['shipping_cost'] ?? 0;
        $this->duty_amount = $expenseData['duty_amount'] ?? 0;
        $this->delivery_fee = $expenseData['delivery_fee'] ?? 0;
        $this->installation_cost = $expenseData['installation_cost'] ?? 0;
        $this->bank_fee = $expenseData['bank_fee'] ?? 0;
        $this->credit_card_fee = $expenseData['credit_card_fee'] ?? 0;
        $this->expenses_recorded_at = now();
        $this->expenses_recorded_by = auth()->id();
        
        $this->calculateProfit();
        $this->save();
        
        return true;
    }

    /**
     * Calculate profit metrics (matching old Reporting system)
     * 
     * @return void
     */
    public function calculateProfit(): void
    {
        $this->total_expenses = 
            ($this->cost_of_goods ?? 0) +
            ($this->shipping_cost ?? 0) +
            ($this->duty_amount ?? 0) +
            ($this->delivery_fee ?? 0) +
            ($this->installation_cost ?? 0) +
            ($this->bank_fee ?? 0) +
            ($this->credit_card_fee ?? 0);

        $this->gross_profit = ($this->sub_total ?? 0) - $this->total_expenses;
        
        if ($this->sub_total > 0) {
            $this->profit_margin = ($this->gross_profit / $this->sub_total) * 100;
        } else {
            $this->profit_margin = 0;
        }
    }

    /**
     * Get formatted profit data (matching old Reporting system)
     * 
     * @return array
     */
    public function getProfitData(): array
    {
        return [
            'revenue' => $this->sub_total,
            'total_expenses' => $this->total_expenses,
            'gross_profit' => $this->gross_profit,
            'profit_margin' => round($this->profit_margin, 2),
            'expense_breakdown' => [
                'cost_of_goods' => $this->cost_of_goods,
                'shipping_cost' => $this->shipping_cost,
                'duty_amount' => $this->duty_amount,
                'delivery_fee' => $this->delivery_fee,
                'installation_cost' => $this->installation_cost,
                'bank_fee' => $this->bank_fee,
                'credit_card_fee' => $this->credit_card_fee,
            ],
            'has_expenses_recorded' => !is_null($this->expenses_recorded_at),
            'recorded_at' => $this->expenses_recorded_at,
            'recorded_by' => $this->expenseRecordedBy?->name,
        ];
    }

    /**
     * Check if expenses have been recorded
     * 
     * @return bool
     */
    public function hasExpensesRecorded(): bool
    {
        return !is_null($this->expenses_recorded_at);
    }

    /**
     * Relationship to user who recorded expenses
     * 
     * @return BelongsTo
     */
    public function expenseRecordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'expenses_recorded_by');
    }

    /**
     * Calculate totals based on items
     */
    public function calculateTotals(): void
    {
        $taxSetting = \App\Modules\Settings\Models\TaxSetting::getDefault();
        $taxRate    = $taxSetting ? floatval($taxSetting->rate) : 5;
        $multiplier = 1 + ($taxRate / 100);

        $inclGross = 0.0;
        $exclNet   = 0.0;

        foreach ($this->items as $item) {
            $qty          = $item->quantity ?? 0;
            $price        = $item->unit_price ?? 0;
            $lineDiscount = $item->discount ?? 0;
            $taxInclusive = $item->tax_inclusive ?? $this->tax_inclusive ?? true;
            $lineTotal    = ($qty * $price) - $lineDiscount;

            if ($taxInclusive) {
                $inclGross += $lineTotal;
            } else {
                $exclNet += $lineTotal;
            }
        }

        $shipping = floatval($this->shipping ?? 0);

        // Inclusive: extract tax
        $inclTax = $inclGross - ($inclGross / $multiplier);
        $inclNet = $inclGross / $multiplier;

        // Exclusive + shipping: add tax on top
        $exclBase = $exclNet + $shipping;
        $exclTax  = $exclBase * ($taxRate / 100);

        $this->sub_total = round($inclNet  + $exclBase, 2);
        $this->vat       = round($inclTax  + $exclTax,  2);
        $this->tax       = $this->vat;
        $this->total     = round($inclGross + $exclBase + $exclTax, 2);

        $this->saveQuietly();
    }
}

