<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Addon extends Model
{
    use HasFactory, SoftDeletes;

    const FOLDER = 'addons';

    protected $table = 'addons';

    protected $fillable = [
        'addon_category_id',
        'title',
        'part_number',
        'description',
        'price',
        'wholesale_price',
        'tax_inclusive',
        'image_1',
        'image_2',
        'stock_status',
        'total_quantity',
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
        'notify_restock',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'wholesale_price' => 'decimal:2',
        'tax_inclusive' => 'boolean',
        'stock_status' => 'integer',
        'total_quantity' => 'integer',
        'notify_restock' => 'array',
    ];

    protected $appends = ['image_1_url', 'image_2_url'];

    /**
     * Get the category this addon belongs to
     */
    public function category()
    {
        return $this->belongsTo(AddonCategory::class, 'addon_category_id');
    }

    /**
     * Get the inventory records for this addon
     */
    public function inventories()
    {
        return $this->hasMany(\App\Modules\Inventory\Models\ProductInventory::class, 'add_on_id');
    }

    /**
     * Scope to filter by category
     */
    public function scopeCategory($query, $catId)
    {
        return $query->where('addon_category_id', $catId);
    }

    /**
     * Scope to get only active addons (in stock)
     */
    public function scopeActive($query)
    {
        return $query->where('stock_status', 1);
    }

    /**
     * Scope to filter by stock status
     */
    public function scopeInStock($query)
    {
        return $query->where('total_quantity', '>', 0);
    }

    /**
     * Get full URL for image_1
     */
    public function getImage1UrlAttribute()
    {
        $raw = $this->getRawOriginal('image_1');
        if (!$raw) {
            return null;
        }

        // If already a full URL, return it
        if (str_starts_with($raw, 'http')) {
            return $raw;
        }

        // Build from CloudFront/S3 config key (use config, not env() which breaks with cache)
        $cdnUrl = config('filesystems.disks.s3.url');
        if ($cdnUrl) {
            return rtrim($cdnUrl, '/') . '/' . ltrim($raw, '/');
        }

        return null;
    }

    /**
     * Get full URL for image_2
     */
    public function getImage2UrlAttribute()
    {
        $raw = $this->getRawOriginal('image_2');
        if (!$raw) {
            return null;
        }

        // If already a full URL, return it
        if (str_starts_with($raw, 'http')) {
            return $raw;
        }

        // Build from CloudFront/S3 config key (use config, not env() which breaks with cache)
        $cdnUrl = config('filesystems.disks.s3.url');
        if ($cdnUrl) {
            return rtrim($cdnUrl, '/') . '/' . ltrim($raw, '/');
        }

        return null;
    }

    /**
     * Calculate customer-specific price based on category pricing
     * 
     * @param Customer $customer
     * @return float
     */
    public function getCustomerPrice($customer)
    {
        // If wholesale price is set and customer is a dealer, use it
        if ($this->wholesale_price && $this->wholesale_price > 0 && $customer->customer_type === 'dealer') {
            return $this->wholesale_price;
        }

        // Check for category-specific pricing
        $categoryPricing = CustomerAddonCategoryPricing::where('customer_id', $customer->id)
            ->where('add_on_category_id', $this->addon_category_id)
            ->first();

        if ($categoryPricing) {
            if ($categoryPricing->discount_type === 'percent' || $categoryPricing->discount_type === 'percentage') {
                $discount = ($this->price * $categoryPricing->discount_value) / 100;
                return max(0, $this->price - $discount);
            } else {
                // Fixed discount
                return max(0, $this->price - $categoryPricing->discount_value);
            }
        }

        // Return regular price
        return $this->price;
    }

    /**
     * Get display price for customer (formatted)
     * 
     * @param Customer|null $customer
     * @return string
     */
    public function getDisplayPrice($customer = null)
    {
        if ($customer) {
            $price = $this->getCustomerPrice($customer);
        } else {
            $price = $this->price;
        }

        return number_format($price, 2);
    }

    /**
     * Check if addon is available
     */
    public function isAvailable()
    {
        return $this->stock_status == 1 && $this->total_quantity > 0;
    }

    /**
     * Update total quantity from inventory
     */
    public function updateTotalQuantity()
    {
        $this->total_quantity = $this->inventory()->sum('quantity');
        $this->save();
    }

    /**
     * Static method to import addon from CSV data
     * 
     * @param array $data
     * @param AddonCategory $category
     * @return Addon
     */
    public static function importFromCsv($data, AddonCategory $category)
    {
        $fieldsData = [
            'price' => $data['us retail price'] ?? 0,
            'title' => $data['product full name'] ?? '',
            'wholesale_price' => $data['wholesale price'] ?? null,
            'bolt_pattern' => $data['bolt pattern'] ?? null,
            'width' => $data['width'] ?? null,
            'thread_size' => $data['thread size'] ?? null,
            'center_bore' => $data['center bore'] ?? null,
            'color' => $data['color'] ?? null,
            'lug_nut_length' => $data['lug nut length'] ?? null,
            'lug_nut_diameter' => $data['lug nut diameter'] ?? null,
            'thread_length' => $data['thread length'] ?? null,
            'lug_bolt_diameter' => $data['lug bolt diameter'] ?? null,
            'ext_center_bore' => $data['ext. center bore'] ?? null,
            'description' => $data['description'] ?? '',
            'image_1' => isset($data['image 1']) && $data['image 1'] ? (self::FOLDER . '/' . $data['image 1']) : null,
            'image_2' => isset($data['image 2']) && $data['image 2'] ? (self::FOLDER . '/' . $data['image 2']) : null,
            'stock_status' => 1,
        ];

        $addon = Addon::updateOrCreate([
            'addon_category_id' => $category->id,
            'part_number' => $data['part number'] ?? '',
        ], $fieldsData);

        return $addon;
    }
}
