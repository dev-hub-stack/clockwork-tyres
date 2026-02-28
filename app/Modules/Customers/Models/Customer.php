<?php

namespace App\Modules\Customers\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'customer_type',
        'first_name',
        'last_name',
        'business_name',
        'phone',
        'email',
        'address',
        'city',
        'state',
        'country_id',
        'website',
        'trade_license_number',
        'expiry',
        'instagram',
        'representative_id',
        'trn',
        'license_no',
        'external_source',
        'external_customer_id',
        'status'
    ];

    protected $casts = [
        'expiry' => 'date',
    ];

    /**
     * CRITICAL: Check if customer is a dealer (activates pricing discounts)
     */
    public function isDealer(): bool
    {
        return in_array($this->customer_type, ['dealer', 'wholesale']);
    }

    /**
     * Check if customer is retail
     */
    public function isRetail(): bool
    {
        return $this->customer_type === 'retail';
    }

    /**
     * Get customer name (business name or full name)
     */
    public function getNameAttribute(): string
    {
        // For business customers, prefer business name
        if ($this->business_name) {
            return $this->business_name;
        }
        
        // For individual customers, combine first and last name
        $name = trim($this->first_name . ' ' . $this->last_name);
        return !empty($name) ? $name : 'Unknown Customer';
    }

    /**
     * Get full name (always returns first + last name)
     */
    public function getFullNameAttribute(): string
    {
        $name = trim($this->first_name . ' ' . $this->last_name);
        if (empty($name) && $this->business_name) {
            return $this->business_name;
        }
        return !empty($name) ? $name : 'Unknown Customer';
    }

    /**
     * Get primary phone
     */
    public function getPrimaryPhoneAttribute(): ?string
    {
        // Return customer phone if available
        if (!empty($this->phone)) {
            return $this->phone;
        }
        
        // Fallback to primary address phone
        $primaryAddress = $this->addresses()->orderBy('address_type', 'asc')->first();
        if ($primaryAddress && !empty($primaryAddress->phone_no)) {
            return $primaryAddress->phone_no;
        }
        
        return null;
    }

    /**
     * Get primary address
     */
    public function getPrimaryAddressAttribute(): ?AddressBook
    {
        // Get primary address (billing first, then shipping)
        $billingAddress = $this->addresses()->where('address_type', 1)->first();
        if ($billingAddress) {
            return $billingAddress;
        }
        
        $shippingAddress = $this->addresses()->where('address_type', 2)->first();
        if ($shippingAddress) {
            return $shippingAddress;
        }
        
        return null;
    }

    /**
     * Relationship: Address Books
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(AddressBook::class);
    }

    /**
     * Relationship: Brand Pricing Rules
     */
    public function brandPricingRules(): HasMany
    {
        return $this->hasMany(CustomerBrandPricing::class);
    }

    /**
     * Relationship: Model Pricing Rules (HIGHEST PRIORITY)
     */
    public function modelPricingRules(): HasMany
    {
        return $this->hasMany(CustomerModelPricing::class);
    }

    /**
     * Relationship: Addon Category Pricing Rules
     */
    public function addonCategoryPricingRules(): HasMany
    {
        return $this->hasMany(CustomerAddonCategoryPricing::class);
    }

    /**
     * Relationship: Orders / Quotes / Invoices
     */
    public function orders(): HasMany
    {
        return $this->hasMany(\App\Modules\Orders\Models\Order::class);
    }

    /**
     * Relationship: Country
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Relationship: Sales Representative
     */
    public function representative(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'representative_id');
    }
}
