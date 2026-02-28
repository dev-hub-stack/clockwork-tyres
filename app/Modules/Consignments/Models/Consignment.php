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
        'is_zero_rated',
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
        'internal_notes',
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
        'is_zero_rated' => 'boolean',
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
        $taxSetting = \App\Modules\Settings\Models\TaxSetting::getDefault();
        $taxRate    = ($this->is_zero_rated) ? 0 : ($taxSetting ? floatval($taxSetting->rate) : 5);
        $multiplier = $taxRate > 0 ? (1 + ($taxRate / 100)) : 1;


        // Reload items to ensure fresh data
        $this->load('items');
        
        \Illuminate\Support\Facades\Log::debug('Consignment::calculateTotals - Items loaded', [
            'consignment_id' => $this->id,
            'items_count' => $this->items->count(),
            'items_raw' => $this->items->toArray(),
        ]);

        $inclGross = 0.0;
        $exclNet   = 0.0;
        $itemsRawValue = 0.0;

        foreach ($this->items as $item) {
            $qty          = $item->quantity_sent ?? 0;
            $price        = $item->price ?? 0;
            $taxInclusive = $item->tax_inclusive ?? true;
            $lineTotal    = $qty * $price;
            
            \Illuminate\Support\Facades\Log::debug('Consignment::calculateTotals - Processing item', [
                'item_id' => $item->id,
                'qty' => $qty,
                'price' => $price,
                'tax_inclusive' => $taxInclusive,
                'line_total' => $lineTotal,
            ]);

            // Track the raw item value (quantity × price)
            $itemsRawValue += $lineTotal;

            if ($taxInclusive) {
                $inclGross += $lineTotal;
            } else {
                $exclNet += $lineTotal;
            }
        }

        $discount = floatval($this->discount ?? 0);
        $shipping = floatval($this->shipping_cost ?? 0);

        // Apply order-level discount proportionally
        $itemsRaw = $inclGross + $exclNet;
        if ($itemsRaw > 0 && $discount > 0) {
            $ratio     = max(0, 1 - ($discount / $itemsRaw));
            $inclGross = $inclGross * $ratio;
            $exclNet   = $exclNet   * $ratio;
        }

        // Inclusive: extract tax
        $inclTax = $inclGross - ($inclGross / $multiplier);
        $inclNet = $inclGross / $multiplier;

        // Exclusive + shipping: add tax on top
        $exclBase = $exclNet + $shipping;
        $exclTax  = $exclBase * ($taxRate / 100);

        $subTotal     = round($inclNet  + $exclBase, 2);
        $totalTax     = round($inclTax  + $exclTax,  2);
        $runningTotal = round($inclGross + $exclBase + $exclTax, 2);

        $this->subtotal = $subTotal;
        $this->tax      = $totalTax;
        $this->total    = $runningTotal;

        // total_value tracks the raw item value (before adjustments)
        // Use the actual calculated raw value from items
        $this->total_value = round($itemsRawValue, 2);
        
        // Re-calculate invoiced and returned values from items for consistency
        $this->invoiced_value = $this->items->sum(function ($item) {
            return ($item->quantity_sold ?? 0) * ($item->price ?? 0);
        });
        
        $this->returned_value = $this->items->sum(function ($item) {
            return ($item->quantity_returned ?? 0) * ($item->price ?? 0);
        });

        // Re-calculate balance manually based on total_value and existing returned/invoiced totals
        $this->balance_value = $this->total_value - $this->invoiced_value - $this->returned_value;
        
        $this->save();
    }

    /**
     * Update item counts based on items
     */
    public function updateItemCounts(): void
    {
        // Reload items relationship to get fresh data from database
        $this->load('items');
        
        $itemsSent = $this->items->sum('quantity_sent');
        $itemsSold = $this->items->sum('quantity_sold');
        $itemsReturned = $this->items->sum('quantity_returned');
        
        \Log::debug('Consignment::updateItemCounts', [
            'consignment_id' => $this->id,
            'consignment_number' => $this->consignment_number,
            'items_sent_raw' => $this->items->pluck('quantity_sent', 'id'),
            'items_sold_raw' => $this->items->pluck('quantity_sold', 'id'),
            'items_returned_raw' => $this->items->pluck('quantity_returned', 'id'),
            'items_sent_sum' => $itemsSent,
            'items_sold_sum' => $itemsSold,
            'items_returned_sum' => $itemsReturned,
        ]);
        
        $this->items_sent_count = $itemsSent;
        $this->items_sold_count = $itemsSold;
        $this->items_returned_count = $itemsReturned;
        $this->save();
        
        \Log::debug('Consignment::updateItemCounts - After save', [
            'consignment_id' => $this->id,
            'items_sent_count' => $this->items_sent_count,
            'items_sold_count' => $this->items_sold_count,
            'items_returned_count' => $this->items_returned_count,
        ]);
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

        $maxNum = self::withTrashed()
            ->where('consignment_number', 'like', $prefix . '-' . $year . '-%')
            ->selectRaw('MAX(CAST(SUBSTRING_INDEX(consignment_number, \'-\', -1) AS UNSIGNED)) as max_num')
            ->value('max_num');

        $number = ($maxNum ?? 0) + 1;

        return $prefix . '-' . $year . '-' . str_pad((string) $number, 4, '0', STR_PAD_LEFT);
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
