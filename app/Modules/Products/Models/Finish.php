<?php

namespace App\Modules\Products\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Finish extends Model
{
    use HasFactory;

    protected $fillable = [
        'finish',
    ];

    protected $casts = [
        'status' => 'integer',
    ];

    /**
     * Relationship: Products
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Relationship: Product Variants
     */
    public function productVariants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * Relationship: Product Images
     */
    public function productImages(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    /**
     * Scope: Active finishes
     */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    /**
     * Scope: Ordered by name
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('name');
    }

    /**
     * Accessor: Get color with fallback
     */
    public function getColorAttribute(): string
    {
        return $this->hex_color ?? '#000000';
    }
}
