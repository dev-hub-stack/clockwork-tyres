<?php

namespace App\Modules\Products\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_id',
        'sku',
        'name',
        'rim_diameter',
        'rim_width',
        'bolt_pattern',
        'offset',
        'bore_size',
        'load_rating',
        'finish_type',
        'color',
        'retail_price',
        'dealer_price',
        'cost',
        'weight',
        'stock_quantity',
        'low_stock_threshold',
        'status',
        'external_id',
        'external_source',
    ];

    protected $casts = [
        'rim_diameter' => 'decimal:2',
        'rim_width' => 'decimal:2',
        'offset' => 'decimal:2',
        'bore_size' => 'decimal:2',
        'retail_price' => 'decimal:2',
        'dealer_price' => 'decimal:2',
        'cost' => 'decimal:2',
        'weight' => 'decimal:2',
        'stock_quantity' => 'integer',
        'low_stock_threshold' => 'integer',
        'status' => 'integer',
    ];

    /**
     * Relationship: Product
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Scope: Active variants
     */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
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
