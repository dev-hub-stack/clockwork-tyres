<?php

namespace App\Modules\Products\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'sku',
        'price',
        'brand_id',
        'model_id',
        'finish_id',
        'images',
        'construction',
        'status',
        'available_on_wholesale',
        'track_inventory',
        'external_product_id',
        'external_source',
    ];

    protected $casts = [
        'price'                 => 'decimal:2',
        'status'                => 'boolean',
        'available_on_wholesale'=> 'boolean',
        'track_inventory'       => 'boolean',
        'images'                => 'collection',
    ];

    /**
     * Relationship: Brand
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * Relationship: Model
     */
    public function model(): BelongsTo
    {
        return $this->belongsTo(ProductModel::class, 'model_id');
    }

    /**
     * Relationship: Finish
     */
    public function finish(): BelongsTo
    {
        return $this->belongsTo(Finish::class);
    }

    /**
     * Relationship: Variants
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * Relationship: Inventory records across all warehouses
     */
    public function inventories(): HasMany
    {
        return $this->hasMany(\App\Modules\Inventory\Models\ProductInventory::class);
    }

    /**
     * Scope: Active products
     */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    /**
     * Scope: Products available on the wholesale storefront
     */
    public function scopeWholesale($query)
    {
        return $query->where('available_on_wholesale', true);
    }

    /**
     * Scope: Products with inventory tracking enabled
     */
    public function scopeInventoryTracked($query)
    {
        return $query->where('track_inventory', true);
    }

    /**
     * Scope: Featured products
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope: For a specific brand
     */
    public function scopeForBrand($query, $brandId)
    {
        return $query->where('brand_id', $brandId);
    }

    /**
     * Scope: For a specific model
     */
    public function scopeForModel($query, $modelId)
    {
        return $query->where('model_id', $modelId);
    }

    /**
     * Scope: Ordered
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Accessor: Full product name
     */
    public function getFullNameAttribute(): string
    {
        $parts = array_filter([
            $this->brand?->name,
            $this->model?->name,
            $this->finish?->name,
        ]);
        
        return implode(' - ', $parts) ?: $this->name;
    }

    /**
     * Calculate price for a specific customer
     */
    public function getPriceForCustomer($customer): float
    {
        if (!$customer || !$customer->isDealer()) {
            return $this->retail_price;
        }

        // This will be enhanced with dealer pricing service
        return $this->dealer_price ?? $this->retail_price;
    }
}
