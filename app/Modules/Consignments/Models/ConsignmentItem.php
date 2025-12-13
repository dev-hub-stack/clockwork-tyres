<?php

namespace App\Modules\Consignments\Models;

use App\Modules\AddOns\Models\Addon;
use App\Modules\Consignments\Enums\ConsignmentItemStatus;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConsignmentItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        // Relationships
        'consignment_id',
        'product_variant_id',
        'warehouse_id',
        
        // Snapshots (JSONB) - preserve product data at time of consignment
        'product_snapshot',
        
        // Denormalized fields (from snapshot for quick access)
        'product_name',
        'brand_name',
        'sku',
        'description',
        
        // Quantity tracking
        'quantity_sent',
        'quantity_sold',
        'quantity_returned',
        
        // Pricing
        'price',
        'actual_sale_price',
        
        // Status & Dates
        'status',
        'date_sold',
        'date_returned',
    ];

    protected $casts = [
        'status' => ConsignmentItemStatus::class,
        'product_snapshot' => 'array',
        'quantity_sent' => 'integer',
        'quantity_sold' => 'integer',
        'quantity_returned' => 'integer',
        'price' => 'decimal:2',
        'actual_sale_price' => 'decimal:2',
        'date_sold' => 'datetime',
        'date_returned' => 'datetime',
    ];

    /**
     * Get the consignment that owns this item
     */
    public function consignment(): BelongsTo
    {
        return $this->belongsTo(Consignment::class);
    }

    /**
     * Get the product variant for this item
     */
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /**
     * Get the warehouse for this item
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Inventory\Models\Warehouse::class);
    }

    /**
     * Get the product for this item (through variant)
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_variant_id');
    }

    /**
     * Accessors for snapshots
     */
    public function getProductSnapshotDataAttribute(): ?array
    {
        return $this->product_snapshot;
    }

    /**
     * Get snapshot value by key
     */
    public function getProductSnapshotValue(string $key, $default = null)
    {
        return data_get($this->product_snapshot, $key, $default);
    }

    /**
     * Calculate line total based on quantity sent and price
     */
    public function calculateLineTotal(): float
    {
        return $this->quantity_sent * $this->price;
    }

    /**
     * Get available quantity to sell
     */
    public function getAvailableToSell(): int
    {
        return $this->quantity_sent - $this->quantity_sold - $this->quantity_returned;
    }

    /**
     * Get available quantity to return (items still with customer, not yet sold or returned)
     */
    public function getAvailableToReturn(): int
    {
        // Return items that are with customer: sent - sold - already returned
        return $this->quantity_sent - $this->quantity_sold - $this->quantity_returned;
    }

    /**
     * Mark items as sold
     */
    public function markAsSold(int $quantity, ?float $salePrice = null): void
    {
        $this->quantity_sold += $quantity;
        
        if ($salePrice !== null) {
            $this->actual_sale_price = $salePrice;
        }
        
        $this->date_sold = now();
        
        // Update status if all sent items are sold
        if ($this->quantity_sold >= $this->quantity_sent) {
            $this->status = ConsignmentItemStatus::SOLD;
        }
        
        $this->save();
    }

    /**
     * Mark items as returned
     */
    public function markAsReturned(int $quantity): void
    {
        $this->quantity_returned += $quantity;
        $this->date_returned = now();
        
        // Update status if all items are returned
        if ($this->quantity_returned >= $this->quantity_sent) {
            $this->status = ConsignmentItemStatus::RETURNED;
        }
        
        $this->save();
    }

    /**
     * Check if item can be sold
     */
    public function canBeSold(): bool
    {
        return $this->status->canBeSold() && $this->getAvailableToSell() > 0;
    }

    /**
     * Check if item can be returned
     */
    public function canBeReturned(): bool
    {
        return $this->status->canBeReturned() && $this->getAvailableToReturn() > 0;
    }

    /**
     * Get the effective sale price (actual sale price or original price)
     */
    public function getEffectiveSalePrice(): float
    {
        return $this->actual_sale_price ?? $this->price;
    }
}
