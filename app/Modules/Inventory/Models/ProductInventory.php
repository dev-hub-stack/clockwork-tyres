<?php

namespace App\Modules\Inventory\Models;

use App\Models\Addon;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductInventory extends Model
{
    use HasFactory;

    protected $fillable = [
        'warehouse_id',
        'product_id',
        'product_variant_id',
        'add_on_id',
        'quantity',
        'eta',
        'eta_qty',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'eta_qty' => 'integer',
    ];

    /**
     * Get the warehouse that owns this inventory
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Get the product (if this is product inventory)
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the product variant (if this is variant inventory)
     */
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /**
     * Get the addon (if this is addon inventory)
     */
    public function addon(): BelongsTo
    {
        return $this->belongsTo(Addon::class, 'add_on_id');
    }

    /**
     * Get all inventory logs for this inventory record
     */
    public function inventoryLogs(): HasMany
    {
        return $this->hasMany(InventoryLog::class);
    }

    /**
     * Get the inventoriable item (polymorphic helper)
     * Returns the Product, ProductVariant, or AddOn
     */
    public function getInventoriableAttribute()
    {
        if ($this->product_id) {
            return $this->product;
        }
        
        if ($this->product_variant_id) {
            return $this->productVariant;
        }
        
        if ($this->add_on_id) {
            return $this->addon;
        }
        
        return null;
    }

    /**
     * Get the inventoriable type
     * 
     * @return string 'product', 'variant', 'addon', or 'unknown'
     */
    public function getInventoriableTypeAttribute(): string
    {
        if ($this->product_id) {
            return 'product';
        }
        
        if ($this->product_variant_id) {
            return 'variant';
        }
        
        if ($this->add_on_id) {
            return 'addon';
        }
        
        return 'unknown';
    }

    /**
     * Get total available quantity (current + inbound)
     */
    public function getTotalAvailableAttribute(): int
    {
        return $this->quantity + $this->eta_qty;
    }

    /**
     * Check if item is in stock
     */
    public function getInStockAttribute(): bool
    {
        return $this->quantity > 0;
    }

    /**
     * Check if item has inbound stock expected
     */
    public function getHasInboundAttribute(): bool
    {
        return $this->eta_qty > 0;
    }

    /**
     * Get stock status color for UI
     * Green: > 50, Yellow: 1-50, Red: 0, Blue: 0 but has inbound
     */
    public function getStockStatusColorAttribute(): string
    {
        if ($this->quantity > 50) {
            return 'green';
        }
        
        if ($this->quantity > 0) {
            return 'yellow';
        }
        
        if ($this->eta_qty > 0) {
            return 'blue'; // Out of stock but has inbound
        }
        
        return 'red'; // Out of stock, no inbound
    }

    /**
     * Scope to filter by warehouse
     */
    public function scopeForWarehouse($query, int $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    /**
     * Scope to filter by product
     */
    public function scopeForProduct($query, int $productId)
    {
        return $query->where('product_id', $productId);
    }

    /**
     * Scope to filter by variant
     */
    public function scopeForVariant($query, int $variantId)
    {
        return $query->where('product_variant_id', $variantId);
    }

    /**
     * Scope to filter by addon
     */
    public function scopeForAddon($query, int $addonId)
    {
        return $query->where('add_on_id', $addonId);
    }

    /**
     * Scope to get items in stock
     */
    public function scopeInStock($query)
    {
        return $query->where('quantity', '>', 0);
    }

    /**
     * Scope to get items out of stock
     */
    public function scopeOutOfStock($query)
    {
        return $query->where('quantity', 0);
    }

    /**
     * Scope to get items with inbound stock
     */
    public function scopeWithInbound($query)
    {
        return $query->where('eta_qty', '>', 0);
    }
}
