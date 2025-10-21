<?php

namespace App\Modules\Products\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Brand extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'logo',
        'status',
        'external_id',
        'external_source',
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
     * Relationship: Product Images
     */
    public function productImages(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    /**
     * Scope: Active brands
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
}
