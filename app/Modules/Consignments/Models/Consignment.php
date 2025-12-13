<?php

namespace App\Modules\Consignments\Models;

use App\Models\User;
use App\Modules\Customers\Models\Customer;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Consignments\Enums\ConsignmentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Consignment extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Default attribute values
     */
    protected $attributes = [
        'subtotal' => 0,
        'tax' => 0,
        'discount' => 0,
        'shipping_cost' => 0,
        'total' => 0,
        'items_sent_count' => 0,
        'items_sold_count' => 0,
        'items_returned_count' => 0,
    ];

    protected $fillable = [
        // Core fields
        'consignment_number',
        'tracking_number',
        
        // Relationships
        'customer_id',
        'representative_id',
        'warehouse_id',
        'created_by',
        
        // Financial
        'subtotal',
        'tax',
        'discount',
        'shipping_cost',
        'total',
        'total_value',
        'invoiced_value',
        'returned_value',
        'balance_value',
        
        // Status & Tracking
        'status',
        'items_sent_count',
        'items_sold_count',
        'items_returned_count',
        
        // Dates
        'issue_date',
        'expected_return_date',
        'sent_at',
        'delivered_at',
        
        // Vehicle Information (database columns)
        'year',
        'make',
        'model',
        'sub_model',
        
        // Notes
        'notes',
        'terms_conditions',
        
        // Conversion
        'converted_invoice_id',
    ];

    protected $casts = [
        'status' => ConsignmentStatus::class,
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'discount' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'total' => 'decimal:2',
        'total_value' => 'decimal:2',
        'invoiced_value' => 'decimal:2',
        'returned_value' => 'decimal:2',
        'balance_value' => 'decimal:2',
        'items_sent_count' => 'integer',
        'items_sold_count' => 'integer',
        'items_returned_count' => 'integer',
        'issue_date' => 'date',
        'expected_return_date' => 'date',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    /**
     * Get the customer that owns this consignment
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the warehouse for this consignment
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Get the sales representative for this consignment
     */
    public function representative(): BelongsTo
    {
        return $this->belongsTo(User::class, 'representative_id');
    }

    /**
     * Get the user who created this consignment
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get all items for this consignment
     */
    public function items(): HasMany
    {
        return $this->hasMany(ConsignmentItem::class);
    }

    /**
     * Get all history entries for this consignment
     */
    public function histories(): HasMany
    {
        return $this->hasMany(ConsignmentHistory::class);
    }

    /**
     * Get the invoice this consignment was converted to (if applicable)
     */
    public function convertedInvoice(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Orders\Models\Order::class, 'converted_invoice_id');
    }

    /**
     * Scope: Get recent consignments
     */
    public function scopeRecent($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Scope: Get consignments by status
     */
    public function scopeByStatus($query, ConsignmentStatus $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: Get active consignments (not cancelled or returned)
     */
    public function scopeActive($query)
    {
        return $query->whereNotIn('status', [
            ConsignmentStatus::CANCELLED,
            ConsignmentStatus::RETURNED,
        ]);
    }

    /**
     * Calculate and update totals based on items
     */
    public function calculateTotals(): void
    {
        $this->subtotal = $this->items->sum(function ($item) {
            return $item->quantity_sent * $item->price;
        });

        // Apply tax from organization settings
        $this->tax = $this->subtotal * ($this->tax_rate / 100);
        
        // Calculate total
        $this->total = $this->subtotal + $this->tax - $this->discount + $this->shipping_cost;
        
        $this->save();
    }

    /**
     * Update item counts based on items
     */
    public function updateItemCounts(): void
    {
        $this->items_sent_count = $this->items->sum('quantity_sent');
        $this->items_sold_count = $this->items->sum('quantity_sold');
        $this->items_returned_count = $this->items->sum('quantity_returned');
        $this->save();
    }

    /**
     * Update status based on items
     */
    public function updateStatusBasedOnItems(): void
    {
        // If all sent items are returned
        if ($this->items_returned_count >= $this->items_sent_count && $this->items_sent_count > 0) {
            $this->status = ConsignmentStatus::RETURNED;
        }
        // If some items are returned (but not all)
        elseif ($this->items_returned_count > 0 && $this->items_returned_count < $this->items_sent_count) {
            $this->status = ConsignmentStatus::PARTIALLY_RETURNED;
        }
        // If all sent items are sold
        elseif ($this->items_sold_count >= $this->items_sent_count && $this->items_sent_count > 0) {
            $this->status = ConsignmentStatus::INVOICED_IN_FULL;
        }
        // If some items are sold (but not all)
        elseif ($this->items_sold_count > 0) {
            $this->status = ConsignmentStatus::PARTIALLY_SOLD;
        }
        
        $this->save();
    }

    /**
     * Check if consignment can record sale
     */
    public function canRecordSale(): bool
    {
        return $this->status->canRecordSale() && ($this->items_sold_count < $this->items_sent_count);
    }

    /**
     * Check if consignment can record return
     */
    public function canRecordReturn(): bool
    {
        // Can return if status allows it AND there are items that can be returned
        // Items can be returned if: quantity_sent - quantity_returned > 0
        if (!$this->status->canRecordReturn()) {
            return false;
        }
        
        // Check if there are any items with returnable quantity
        $hasReturnableItems = $this->items()
            ->get()
            ->some(function ($item) {
                $returnable = $item->quantity_sent - ($item->quantity_returned ?? 0);
                return $returnable > 0;
            });
        
        return $hasReturnableItems;
    }

    /**
     * Check if consignment is fully sold
     */
    public function isFullySold(): bool
    {
        return $this->items_sold_count >= $this->items_sent_count && $this->items_sent_count > 0;
    }

    /**
     * Check if consignment is partially returned
     */
    public function isPartiallyReturned(): bool
    {
        return $this->items_returned_count > 0 && $this->items_returned_count < $this->items_sent_count;
    }

    /**
     * Generate consignment number
     */
    public static function generateConsignmentNumber(): string
    {
        $prefix = 'CNS';
        $year = date('Y');
        
        $lastConsignment = self::whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();
        
        $number = $lastConsignment ? intval(substr($lastConsignment->consignment_number, -4)) + 1 : 1;
        
        return $prefix . '-' . $year . '-' . str_pad($number, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Get formatted vehicle information
     */
    public function getVehicleInfo(): string
    {
        $parts = array_filter([
            $this->year,
            $this->make,
            $this->model,
            $this->sub_model,
        ]);
        
        return implode(' ', $parts) ?: 'No vehicle info';
    }

    /**
     * Format currency amount
     */
    public function formatCurrency(float $amount): string
    {
        $currency = \App\Modules\Settings\Models\CurrencySetting::getBase()?->currency_code ?? 'AED';
        return $currency . ' ' . number_format($amount, 2);
    }

    // ============================================
    // Attribute Accessors (Backward Compatibility)
    // ============================================
    
    /**
     * Get vehicle_year attribute (maps to 'year' column)
     */
    public function getVehicleYearAttribute()
    {
        return $this->year;
    }

    /**
     * Set vehicle_year attribute (maps to 'year' column)
     */
    public function setVehicleYearAttribute($value)
    {
        $this->attributes['year'] = $value;
    }

    /**
     * Get vehicle_make attribute (maps to 'make' column)
     */
    public function getVehicleMakeAttribute()
    {
        return $this->make;
    }

    /**
     * Set vehicle_make attribute (maps to 'make' column)
     */
    public function setVehicleMakeAttribute($value)
    {
        $this->attributes['make'] = $value;
    }

    /**
     * Get vehicle_model attribute (maps to 'model' column)
     */
    public function getVehicleModelAttribute()
    {
        return $this->model;
    }

    /**
     * Set vehicle_model attribute (maps to 'model' column)
     */
    public function setVehicleModelAttribute($value)
    {
        $this->attributes['model'] = $value;
    }

    /**
     * Get vehicle_sub_model attribute (maps to 'sub_model' column)
     */
    public function getVehicleSubModelAttribute()
    {
        return $this->sub_model;
    }

    /**
     * Set vehicle_sub_model attribute (maps to 'sub_model' column)
     */
    public function setVehicleSubModelAttribute($value)
    {
        $this->attributes['sub_model'] = $value;
    }
}
