<?php

namespace App\Modules\Customers\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerAddonCategoryPricing extends Model
{
    use HasFactory;

    protected $table = 'customer_addon_category_pricing';

    protected $fillable = [
        'customer_id',
        'add_on_category_id',
        'discount_type',
        'discount_percentage',
        'discount_value',
    ];

    protected $casts = [
        'discount_percentage' => 'decimal:2',
        'discount_value' => 'decimal:2',
    ];

    /**
     * Calculate discount amount
     */
    public function calculateDiscount(float $amount): float
    {
        if ($this->discount_type === 'percentage') {
            return $amount * ($this->discount_percentage / 100);
        }
        
        return min($this->discount_value, $amount);
    }

    /**
     * Apply discount to amount
     */
    public function applyDiscount(float $amount): float
    {
        return $amount - $this->calculateDiscount($amount);
    }

    /**
     * Relationship: Customer
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Relationship: Addon Category (will be defined when AddOns module is created)
     */
    // public function addonCategory(): BelongsTo
    // {
    //     return $this->belongsTo(\App\Modules\AddOns\Models\AddonCategory::class, 'add_on_category_id');
    // }
}
