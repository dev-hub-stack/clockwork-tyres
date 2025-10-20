<?php

namespace App\Modules\Products\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'sku',
        'name',
        'brand_id',
        'model_id',
        'finish_id',
        'description',
        'base_price',
        'retail_price',
        'dealer_price',
        'cost',
        'weight',
        'dimensions',
        'specifications',
        'features',
        'warranty',
        'status',
        'is_featured',
        'sort_order',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'external_id',
        'external_source',
        'sync_status',
        'synced_at',
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'retail_price' => 'decimal:2',
        'dealer_price' => 'decimal:2',
        'cost' => 'decimal:2',
        'weight' => 'decimal:2',
        'status' => 'integer',
        'is_featured' => 'boolean',
        'sort_order' => 'integer',
        'specifications' => 'array',
        'features' => 'array',
        'synced_at' => 'datetime',
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
     * Scope: Active products
     */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
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
