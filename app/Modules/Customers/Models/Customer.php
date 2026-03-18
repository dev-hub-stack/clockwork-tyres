<?php

namespace App\Modules\Customers\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Modules\Customers\Models\AddressBook;
use App\Modules\Customers\Models\CustomerBrandPricing;
use App\Modules\Customers\Models\CustomerModelPricing;
use App\Modules\Customers\Models\CustomerAddonCategoryPricing;
use App\Modules\Customers\Models\Country;

// NOTE: Extends Authenticatable (not Model) to enable Sanctum token auth for dealers.
// This is a minimal, non-breaking change — all existing Filament admin functionality
// continues to work because Authenticatable itself extends Model.
class Customer extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'customer_type',
        'first_name',
        'last_name',
        'business_name',
        'phone',
        'email',
        'password',
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
        'status',
        'email_verified_at',
        'profile_image',
        'trade_license_path',
        'vat_certificate_path',
        'wholesale_invite_token',
        'wholesale_invite_expires_at',
        'wholesale_invited_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     * Prevents password/token from leaking into API responses.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'expiry'                       => 'date',
        'email_verified_at'            => 'datetime',
        'password'                     => 'hashed',
        'wholesale_invite_expires_at'  => 'datetime',
        'wholesale_invited_at'         => 'datetime',
    ];

    /**
     * Boot the model to handle automatic actions.
     */
    protected static function booted(): void
    {
        static::created(function (Customer $customer) {
            // Automatically add an Address Book entry if address details are provided
            if ($customer->address || $customer->city || $customer->country_id) {
                // Determine country name if possible
                $countryName = null;
                if ($customer->country_id) {
                    $country = \App\Modules\Customers\Models\Country::find($customer->country_id);
                    $countryName = $country ? $country->name : null;
                }

                $customer->addresses()->create([
                    'address_type' => 1, // 1 = Billing Address
                    'nickname' => 'Default Billing Address',
                    'first_name' => '', // Left empty since we use customer name
                    'last_name' => '',
                    'address' => $customer->address,
                    'city' => $customer->city,
                    'state' => $customer->state,
                    'country' => $countryName,
                    'phone_no' => $customer->phone,
                    'email' => $customer->email,
                ]);
            }
        });
    }

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
