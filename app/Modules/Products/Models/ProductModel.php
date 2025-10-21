<?php

namespace App\Modules\Products\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductModel extends Model
{
    use HasFactory;

    protected $table = 'models';

    protected $fillable = [
        'name',
        'image',
    ];

    protected $casts = [
        'status' => 'integer',
    ];

    /**
     * Relationship: Brand
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * Relationship: Products
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'model_id');
    }

    /**
     * Relationship: Product Images
     */
    public function productImages(): HasMany
    {
        return $this->hasMany(ProductImage::class, 'model_id');
    }

    /**
     * Scope: Active models
     */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    /**
     * Scope: For a specific brand
     */
    public function scopeForBrand($query, $brandId)
    {
        return $query->where('brand_id', $brandId);
    }
}
