<?php

namespace App\Modules\Products\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AddOn extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'addons';

    protected $fillable = [
        'addon_category_id',
        'title',
        'part_number',
        'description',
        'price',
        'wholesale_price',
        'tax_inclusive',
        'image',  // Maps to image_1
        'stock_status',
        'total_quantity',
        // Category-specific fields
        'bolt_pattern',
        'width',
        'thread_size',
        'thread_length',
        'ext_center_bore',
        'center_bore',
        'color',
        'lug_nut_length',
        'lug_nut_diameter',
        'lug_bolt_diameter',
        // External tracking
        'external_addon_id',
        'external_source',
        // Additional fields for compatibility
        'size',
        'unit',
        'vehicle',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'wholesale_price' => 'decimal:2',
        'tax_inclusive' => 'boolean',
        'notify_restock' => 'array',
    ];

    /**
     * Get the category for this addon
     */
    public function category()
    {
        return $this->belongsTo(AddOnCategory::class, 'addon_category_id');
    }

    /**
     * Accessor for image (maps to image_1)
     */
    public function getImageAttribute($value)
    {
        return $this->attributes['image_1'] ?? null;
    }

    /**
     * Mutator for image (maps to image_1)
     */
    public function setImageAttribute($value)
    {
        $this->attributes['image_1'] = $value;
    }

    /**
     * Get stock status as string
     */
    public function getStockStatusTextAttribute()
    {
        return match($this->stock_status) {
            1 => 'in_stock',
            0 => 'out_of_stock',
            2 => 'pre_order',
            default => 'in_stock'
        };
    }

    /**
     * Set stock status from string
     */
    public function setStockStatusAttribute($value)
    {
        if (is_string($value)) {
            $this->attributes['stock_status'] = match($value) {
                'in_stock' => 1,
                'out_of_stock' => 0,
                'pre_order' => 2,
                default => 1
            };
        } else {
            $this->attributes['stock_status'] = $value;
        }
    }
}
