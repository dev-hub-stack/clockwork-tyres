<?php

namespace App\Modules\Products\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'sku',
        'finish_id',
        'size',
        'bolt_pattern',
        'hub_bore',
        'offset',
        'weight',
        'backspacing',
        'lipsize',
        'finish',
        'max_wheel_load',
        'rim_diameter',
        'rim_width',
        'cost',
        'price',
        'us_retail_price',
        'uae_retail_price',
        'sale_price',
        'clearance_corner',
        'image', // Tunerstop uses 'image' (singular) not 'images'
        'supplier_stock',
        'notify_restock',
        'external_variant_id',
        'external_source',
        'track_inventory',
    ];

    protected $casts = [
        'us_retail_price' => 'decimal:2',
        'uae_retail_price' => 'decimal:2',
        'clearance_corner' => 'boolean',
        'supplier_stock' => 'integer',
        'notify_restock' => 'array',
        'track_inventory' => 'boolean',
    ];

    protected $attributes = [
        'track_inventory' => false,
    ];

    /**
     * Relationship: Product
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Relationship: Finish (via finish_id)
     * Note: Named 'finishRelation' to avoid conflict with 'finish' column
     */
    public function finishRelation(): BelongsTo
    {
        return $this->belongsTo(Finish::class, 'finish_id');
    }

    /**
     * Relationship: Finish (deprecated - use finishRelation)
     * Kept for backward compatibility
     */
    public function finish(): BelongsTo
    {
        return $this->belongsTo(Finish::class);
    }

    /**
     * Relationship: Inventory records across all warehouses
     */
    public function inventories()
    {
        return $this->hasMany(\App\Modules\Inventory\Models\ProductInventory::class);
    }

    /**
     * Relationship: Consignment items for this variant
     */
    public function consignmentItems()
    {
        return $this->hasMany(\App\Modules\Consignments\Models\ConsignmentItem::class);
    }

    /**
     * Scope: Active variants
     */
    public function scopeActive($query)
    {
        return $query->whereHas('product', fn($q) => $q->where('status', 1));
    }

    /**
     * Scope: In stock
     */
    public function scopeInStock($query)
    {
        return $query->where('stock_quantity', '>', 0);
    }

    /**
     * Scope: Low stock
     */
    public function scopeLowStock($query)
    {
        return $query->whereColumn('stock_quantity', '<=', 'low_stock_threshold');
    }

    /**
     * Check if variant is in stock
     */
    public function isInStock(): bool
    {
        return $this->stock_quantity > 0;
    }

    /**
     * Check if variant is low on stock
     */
    public function isLowStock(): bool
    {
        return $this->stock_quantity <= $this->low_stock_threshold;
    }

    /**
     * Accessor: Full variant name
     */
    public function getFullNameAttribute(): string
    {
        $specs = [];
        
        if ($this->rim_diameter) {
            $specs[] = $this->rim_diameter . '"';
        }
        
        if ($this->rim_width) {
            $specs[] = $this->rim_width . 'W';
        }
        
        if ($this->bolt_pattern) {
            $specs[] = $this->bolt_pattern;
        }
        
        if ($this->offset) {
            $specs[] = 'ET' . $this->offset;
        }
        
        $name = $this->name ?: $this->product?->name;
        
        if (!empty($specs)) {
            return $name . ' (' . implode(' ', $specs) . ')';
        }
        
        return $name;
    }

    /**
     * Calculate price for a specific customer
     */
    public function getPriceForCustomer($customer): float
    {
        if (!$customer || !$customer->isDealer()) {
            return $this->retail_price;
        }

        return $this->dealer_price ?? $this->retail_price;
    }
}
