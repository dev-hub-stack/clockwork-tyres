<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AddonCategory extends Model
{
    use HasFactory;

    protected $table = 'addon_categories';

    protected $fillable = [
        'name',
        'slug',
        'order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'order' => 'integer',
    ];

    /**
     * Global scope to order categories by the 'order' column
     */
    protected static function booted()
    {
        static::addGlobalScope('sorted', function (Builder $builder) {
            return $builder->orderBy('order');
        });
    }

    /**
     * Get all addons in this category
     */
    public function addons()
    {
        return $this->hasMany(Addon::class, 'addon_category_id');
    }

    /**
     * Get customer-specific pricing for this addon category
     */
    public function customerPricing()
    {
        return $this->hasMany(CustomerAddonCategoryPricing::class, 'add_on_category_id');
    }

    /**
     * Get CSV field names for import based on category
     * @return array
     */
    public function getCsvFieldsAttribute()
    {
        $commonAttributes = ['part number', 'product full name', 'us retail price', 'wholesale price', 'image 1', 'image 2'];

        $slugAttributes = [
            'wheel-accessories' => ['description'],
            'lug-nuts' => ['thread size', 'color', 'lug nut length', 'lug nut diameter'],
            'lug-bolts' => ['thread size', 'color', 'thread length', 'lug bolt diameter'],
            'hub-rings' => ['ext. center bore', 'center bore'],
            'spacers' => ['bolt pattern', 'width', 'thread size', 'center bore'],
            'tpms' => ['description'],
        ];

        if (isset($slugAttributes[$this->slug])) {
            return array_merge($commonAttributes, $slugAttributes[$this->slug]);
        }

        return $commonAttributes;
    }

    /**
     * Get allowed fields for this category (for forms/validation)
     * @return array
     */
    public function getAllowedFieldsAttribute()
    {
        $commonFields = ['part_number', 'title', 'price', 'wholesale_price', 'image_1', 'image_2', 'description', 'stock_status', 'tax_inclusive'];

        $slugFields = [
            'wheel-accessories' => ['description'],
            'lug-nuts' => ['thread_size', 'color', 'lug_nut_length', 'lug_nut_diameter'],
            'lug-bolts' => ['thread_size', 'color', 'thread_length', 'lug_bolt_diameter'],
            'hub-rings' => ['ext_center_bore', 'center_bore'],
            'spacers' => ['bolt_pattern', 'width', 'thread_size', 'center_bore'],
            'tpms' => ['description'],
        ];

        if (isset($slugFields[$this->slug])) {
            return array_merge($commonFields, $slugFields[$this->slug]);
        }

        return $commonFields;
    }

    /**
     * Get required fields for this category
     * @return array
     */
    public function getRequiredFieldsAttribute()
    {
        $commonFields = ['title', 'part_number', 'price'];

        $slugFields = [
            'wheel-accessories' => [],
            'lug-nuts' => ['thread_size', 'color'],
            'lug-bolts' => ['thread_size', 'color'],
            'hub-rings' => ['ext_center_bore', 'center_bore'],
            'spacers' => ['bolt_pattern', 'width', 'thread_size', 'center_bore'],
            'tpms' => [],
        ];

        if (isset($slugFields[$this->slug])) {
            return array_merge($commonFields, $slugFields[$this->slug]);
        }

        return $commonFields;
    }

    /**
     * Get filter fields for this category
     * @return array
     */
    public function getFiltersAttribute()
    {
        $slugFields = [
            'wheel-accessories' => [],
            'lug-nuts' => ['thread_size', 'color', 'lug_nut_length', 'lug_nut_diameter'],
            'lug-bolts' => ['thread_size', 'color', 'thread_length', 'lug_bolt_diameter'],
            'hub-rings' => ['ext_center_bore', 'center_bore'],
            'spacers' => ['bolt_pattern', 'width', 'thread_size', 'center_bore'],
            'tpms' => [],
        ];

        return $slugFields[$this->slug] ?? [];
    }

    /**
     * Scope to get only active categories
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
