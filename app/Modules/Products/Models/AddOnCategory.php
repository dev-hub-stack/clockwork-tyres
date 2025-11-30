<?php

namespace App\Modules\Products\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AddOnCategory extends Model
{
    use HasFactory;

    protected $table = 'addon_categories';

    protected $fillable = [
        'name',
        'slug',
        'display_name',
        'image',
        'order',
        'order_sort',
        'is_active',
        'external_id',
        'external_source',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get all addons for this category
     */
    public function addons()
    {
        return $this->hasMany(AddOn::class, 'addon_category_id');
    }

    /**
     * Scope to get only active categories
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to order by sort order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order_sort')->orderBy('order');
    }
}
