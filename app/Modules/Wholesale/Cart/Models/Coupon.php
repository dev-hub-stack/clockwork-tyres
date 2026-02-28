<?php

namespace App\Modules\Wholesale\Cart\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $table = 'wholesale_coupons';

    protected $fillable = [
        'code',
        'description',
        'discount_type',
        'amount',
        'min_spent',
        'max_usage_limit',
        'used_count',
        'free_shipping',
        'start_date',
        'expiry_date',
        'status',
        'brand_ids',
        'model_ids',
    ];

    protected $casts = [
        'amount'        => 'decimal:2',
        'min_spent'     => 'decimal:2',
        'free_shipping' => 'boolean',
        'status'        => 'boolean',
        'brand_ids'     => 'array',
        'model_ids'     => 'array',
        'start_date'    => 'date',
        'expiry_date'   => 'date',
    ];

    /**
     * Check if coupon is currently valid (status, dates, usage limit).
     */
    public function isValid(float $cartSubtotal = 0): bool
    {
        if (! $this->status) {
            return false;
        }
        if ($this->start_date && $this->start_date->isFuture()) {
            return false;
        }
        if ($this->expiry_date && $this->expiry_date->isPast()) {
            return false;
        }
        if ($this->max_usage_limit && $this->used_count >= $this->max_usage_limit) {
            return false;
        }
        if ($cartSubtotal < $this->min_spent) {
            return false;
        }
        return true;
    }

    /**
     * Calculate the discount amount for a given subtotal.
     */
    public function calculateDiscount(float $subtotal): float
    {
        if ($this->discount_type === 'percentage') {
            return round($subtotal * ($this->amount / 100), 2);
        }
        // Fixed — cannot exceed subtotal
        return min((float) $this->amount, $subtotal);
    }

    /**
     * Increment usage counter when coupon is applied.
     */
    public function incrementUsage(): void
    {
        $this->increment('used_count');
    }
}
