<?php

namespace App\Modules\Customers\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AddressBook extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'user_id',
        'dealer_id',
        'address_type',
        'nickname',
        'first_name',
        'last_name',
        'address',
        'city',
        'state',
        'country',
        'zip',
        'zip_code',
        'phone_no',
        'email',
    ];

    /**
     * Check if this is a billing address
     */
    public function isBilling(): bool
    {
        return $this->address_type === 1;
    }

    /**
     * Check if this is a shipping address
     */
    public function isShipping(): bool
    {
        return $this->address_type === 2;
    }

    /**
     * Get formatted address
     */
    public function getFormattedAddressAttribute(): string
    {
        $parts = array_filter([
            $this->address,
            $this->city,
            $this->state,
            $this->country,
            $this->zip ?? $this->zip_code
        ]);
        
        return implode(', ', $parts);
    }

    /**
     * Get contact name
     */
    public function getContactNameAttribute(): string
    {
        $name = trim($this->first_name . ' ' . $this->last_name);
        return !empty($name) ? $name : 'No Contact Name';
    }

    /**
     * Relationship: Customer
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
