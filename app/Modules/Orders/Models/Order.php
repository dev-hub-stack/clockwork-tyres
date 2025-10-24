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
        'tax_inclusive',
        
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
        'shipping_carrier',
        
        // Notes
        'order_notes',
        'internal_notes',
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
        'issue_date' => 'date',
        'valid_until' => 'date',
        'sent_at' => 'datetime',
        'approved_at' => 'datetime',
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
            && $this->quote_status === QuoteStatus::APPROVED 
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
}
