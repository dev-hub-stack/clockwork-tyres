<?php

namespace App\Modules\Products\Models;

use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Services\DealerPricingService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AddOn extends Model
{
    use HasFactory, SoftDeletes;

    public const STOCK_IN_STOCK = 1;
    public const STOCK_OUT_OF_STOCK = 2;
    public const STOCK_BACKORDER = 3;
    public const STOCK_DISCONTINUED = 4;

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
        // Warehouse stock
        'wh2_california',
        'wh1_chicago',
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
        // Inventory tracking
        'track_inventory',
        'notify_restock',
        // Additional fields for compatibility
        'size',
        'unit',
        'vehicle',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'wholesale_price' => 'decimal:2',
        'tax_inclusive' => 'boolean',
        'stock_status' => 'integer',
        'total_quantity' => 'integer',
        'track_inventory' => 'boolean',
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
     * Get inventory records for this addon
     */
    public function inventories()
    {
        return $this->hasMany(\App\Modules\Inventory\Models\ProductInventory::class, 'add_on_id');
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
        return match($this->normalized_stock_status) {
            self::STOCK_IN_STOCK => 'in_stock',
            self::STOCK_OUT_OF_STOCK => 'out_of_stock',
            self::STOCK_BACKORDER => 'backorder',
            self::STOCK_DISCONTINUED => 'discontinued',
            default => 'in_stock'
        };
    }

    public function getNormalizedStockStatusAttribute(): int
    {
        return match ((int) $this->stock_status) {
            0 => self::STOCK_OUT_OF_STOCK,
            self::STOCK_IN_STOCK,
            self::STOCK_OUT_OF_STOCK,
            self::STOCK_BACKORDER,
            self::STOCK_DISCONTINUED => (int) $this->stock_status,
            default => self::STOCK_IN_STOCK,
        };
    }

    public function getAvailabilityStatusAttribute(): string
    {
        $status = $this->normalized_stock_status;

        if ($status === self::STOCK_DISCONTINUED) {
            return 'discontinued';
        }

        if (!($this->track_inventory ?? false)) {
            return match ($status) {
                self::STOCK_IN_STOCK => 'in_stock',
                self::STOCK_BACKORDER => 'backorder',
                default => 'out_of_stock',
            };
        }

        if (($this->total_quantity ?? 0) > 0) {
            return 'in_stock';
        }

        return $status === self::STOCK_BACKORDER ? 'backorder' : 'out_of_stock';
    }

    public function getAvailabilityLabelAttribute(): string
    {
        return match ($this->availability_status) {
            'in_stock' => 'In Stock',
            'backorder' => 'Backorder',
            'discontinued' => 'Discontinued',
            default => 'Out Of Stock',
        };
    }

    public function getIsOrderableAttribute(): bool
    {
        return $this->availability_status === 'in_stock';
    }

    /**
     * Set stock status from string
     */
    public function setStockStatusAttribute($value)
    {
        if (is_string($value)) {
            $this->attributes['stock_status'] = match($value) {
                'in_stock' => self::STOCK_IN_STOCK,
                'out_of_stock' => self::STOCK_OUT_OF_STOCK,
                'pre_order', 'backorder' => self::STOCK_BACKORDER,
                'discontinued' => self::STOCK_DISCONTINUED,
                default => self::STOCK_IN_STOCK
            };
        } else {
            $this->attributes['stock_status'] = match ((int) $value) {
                0 => self::STOCK_OUT_OF_STOCK,
                self::STOCK_IN_STOCK,
                self::STOCK_OUT_OF_STOCK,
                self::STOCK_BACKORDER,
                self::STOCK_DISCONTINUED => (int) $value,
                default => self::STOCK_IN_STOCK,
            };
        }
    }
    /**
     * Calculate price for a specific customer
     * 
     * @param int|object|null $customer
     * @return float
     */
    public function getPriceForCustomer($customer): float
    {
        return $this->resolvePriceForCustomer($this->resolveCustomer($customer));
    }

    /**
     * Calculate discount for a specific customer
     * 
     * @param int|object|null $customer
     * @return float
     */
    public function getDiscountForCustomer($customer): float
    {
        $basePrice = (float) ($this->price ?? 0);
        $finalPrice = $this->getPriceForCustomer($customer);

        return max(0, $basePrice - $finalPrice);
    }

    public function resolvePriceForCustomer(?Customer $customer): float
    {
        $basePrice = (float) ($this->price ?? 0);

        if (!$customer || !$customer->isDealer()) {
            return $basePrice;
        }

        if ((float) ($this->wholesale_price ?? 0) > 0) {
            return (float) $this->wholesale_price;
        }

        $pricing = app(DealerPricingService::class)->calculateAddonPrice(
            $customer,
            $basePrice,
            $this->addon_category_id
        );

        return (float) ($pricing['final_price'] ?? $basePrice);
    }

    protected function resolveCustomer($customer): ?Customer
    {
        if ($customer instanceof Customer) {
            return $customer;
        }

        if (is_numeric($customer)) {
            return Customer::find((int) $customer);
        }

        return null;
    }
}
