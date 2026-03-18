<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ProductVariant extends Model
{
    use SoftDeletes;
    
    protected $table = 'product_variants';
    
    protected $fillable = [
        'product_id',
        'sku',
        'finish_id',
        'finish',
        'construction',
        'rim_width',
        'rim_diameter',
        'size',
        'bolt_pattern',
        'hub_bore',
        'offset',
        'backspacing',
        'max_wheel_load',
        'weight',
        'lipsize',
        'us_retail_price',
        'uae_retail_price',
        'sale_price',
        'images', // CRITICAL: Must be fillable for bulk image upload to work
        'clearance_corner',
        'cost',
        'notify_restock',
    ];
    
    protected $casts = [
        'clearance_corner' => 'boolean',
        'us_retail_price' => 'decimal:2',
        'uae_retail_price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'rim_width' => 'float',
        'rim_diameter' => 'float',
        'hub_bore' => 'float',
        'weight' => 'float',
        'notify_restock' => 'array',
    ];
    
    // Relationships
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
    
    public function finish()
    {
        return $this->belongsTo(Finish::class);
    }
    
    public function inventories()
    {
        return $this->hasMany(ProductInventory::class, 'product_variant_id');
    }
    
    // Accessors
    public function getBrandAttribute()
    {
        return $this->product && $this->product->brand ? $this->product->brand->name : null;
    }
    
    public function getModelAttribute()
    {
        return $this->product && $this->product->model ? $this->product->model->name : null;
    }
    
    // Get total quantity across all warehouses
    public function getTotalQuantityAttribute()
    {
        return $this->inventories()->sum('quantity');
    }
    
    // Get images as array
    public function getImagesArrayAttribute()
    {
        if (!$this->images) {
            return [];
        }
        return explode(',', $this->images);
    }
    
    // Get first image URL
    public function getFirstImageAttribute()
    {
        $images = $this->images_array;
        return $images[0] ?? null;
    }
}
