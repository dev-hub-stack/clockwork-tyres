<?php

namespace App\Modules\Orders\Models;

use App\Modules\AddOns\Models\Addon;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        
        // Product references
        'product_id',
        'product_variant_id',
        'add_on_id',
        
        // JSONB Snapshots
        'product_snapshot',
        'variant_snapshot',
        'addon_snapshot',
        
        // Denormalized fields
        'sku',
        'product_name',
        'product_description',
        'brand_name',
        'model_name',
        
        // Pricing
        'quantity',
        'unit_price',
        'tax_inclusive',
        'discount',
        'tax_amount',
        'line_total',
        
        // Fulfillment
        'allocated_quantity',
        'shipped_quantity',
    ];

    protected $casts = [
        'product_snapshot' => 'array',
        'variant_snapshot' => 'array',
        'addon_snapshot' => 'array',
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'tax_inclusive' => 'boolean',
        'discount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'line_total' => 'decimal:2',
        'allocated_quantity' => 'integer',
        'shipped_quantity' => 'integer',
    ];

    protected $attributes = [
        'quantity' => 1,
        'unit_price' => 0,
        'tax_inclusive' => true,
        'discount' => 0,
        'tax_amount' => 0,
        'line_total' => 0,
        'allocated_quantity' => 0,
        'shipped_quantity' => 0,
    ];

    /**
     * Relationships
     */

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function addon(): BelongsTo
    {
        return $this->belongsTo(Addon::class, 'add_on_id');
    }

    public function quantities(): HasMany
    {
        return $this->hasMany(OrderItemQuantity::class);
    }

    /**
     * Accessors for snapshots
     */

    public function getProductSnapshotDataAttribute(): ?array
    {
        return $this->product_snapshot;
    }

    public function getVariantSnapshotDataAttribute(): ?array
    {
        return $this->variant_snapshot;
    }

    public function getAddonSnapshotDataAttribute(): ?array
    {
        return $this->addon_snapshot;
    }

    /**
     * Get snapshot value by key
     */
    public function getProductSnapshotValue(string $key, $default = null)
    {
        return data_get($this->product_snapshot, $key, $default);
    }

    public function getVariantSnapshotValue(string $key, $default = null)
    {
        return data_get($this->variant_snapshot, $key, $default);
    }

    public function getAddonSnapshotValue(string $key, $default = null)
    {
        return data_get($this->addon_snapshot, $key, $default);
    }

    /**
     * Calculate line total based on quantity, unit price, discount, and tax
     */
    public function calculateLineTotal(): float
    {
        $subtotal = ($this->unit_price * $this->quantity) - $this->discount;
        
        if ($this->tax_inclusive) {
            // Tax is already included in unit_price
            return round($subtotal, 2);
        }
        
        // Tax needs to be added
        return round($subtotal + $this->tax_amount, 2);
    }

    /**
     * Update line total and save
     */
    public function updateLineTotal(): void
    {
        $this->line_total = $this->calculateLineTotal();
        $this->save();
    }

    /**
     * Check if item is fully allocated
     */
    public function isFullyAllocated(): bool
    {
        return $this->allocated_quantity >= $this->quantity;
    }

    /**
     * Check if item is fully shipped
     */
    public function isFullyShipped(): bool
    {
        return $this->shipped_quantity >= $this->quantity;
    }

    /**
     * Get remaining quantity to allocate
     */
    public function getRemainingToAllocateAttribute(): int
    {
        return max(0, $this->quantity - $this->allocated_quantity);
    }

    /**
     * Get remaining quantity to ship
     */
    public function getRemainingToShipAttribute(): int
    {
        return max(0, $this->allocated_quantity - $this->shipped_quantity);
    }

    /**
     * Check if this is a product item
     */
    public function isProduct(): bool
    {
        return !is_null($this->product_id);
    }

    /**
     * Check if this is an addon item
     */
    public function isAddon(): bool
    {
        return !is_null($this->add_on_id);
    }

    /**
     * Get item type label
     */
    public function getItemTypeLabelAttribute(): string
    {
        if ($this->isAddon()) {
            return 'Add-On';
        }
        
        if ($this->product_variant_id) {
            return 'Product Variant';
        }
        
        return 'Product';
    }

    /**
     * Get display name (from snapshot or denormalized field)
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->product_name ?? 
               $this->getProductSnapshotValue('name') ?? 
               $this->getAddonSnapshotValue('name') ?? 
               'Unknown Item';
    }

    /**
     * Get display SKU (from denormalized field or snapshot)
     */
    public function getDisplaySkuAttribute(): ?string
    {
        return $this->sku ?? 
               $this->getVariantSnapshotValue('sku') ?? 
               $this->getProductSnapshotValue('sku');
    }
}
