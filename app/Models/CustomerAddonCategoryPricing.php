<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
     * Get the customer that owns this pricing
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the addon category this pricing applies to
     */
    public function addonCategory()
    {
        return $this->belongsTo(AddonCategory::class, 'add_on_category_id');
    }

    /**
     * Calculate discount amount for a given price
     * 
     * @param float $price
     * @return float
     */
    public function calculateDiscount($price)
    {
        if ($this->discount_type === 'percentage' || $this->discount_type === 'percent') {
            // Use discount_value for backward compatibility, or discount_percentage
            $percentage = $this->discount_value ?? $this->discount_percentage ?? 0;
            return ($price * $percentage) / 100;
        }

        // Fixed discount
        return $this->discount_value ?? 0;
    }

    /**
     * Calculate final price after discount
     * 
     * @param float $price
     * @return float
     */
    public function calculateFinalPrice($price)
    {
        $discount = $this->calculateDiscount($price);
        return max(0, $price - $discount);
    }
}
