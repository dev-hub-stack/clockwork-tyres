<?php

namespace App\Modules\Settings\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CurrencySetting extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'currency_code',
        'currency_name',
        'currency_symbol',
        'symbol_position',
        'exchange_rate',
        'is_base_currency',
        'is_active',
        'decimal_places',
        'thousands_separator',
        'decimal_separator',
    ];

    protected $casts = [
        'exchange_rate' => 'decimal:4',
        'is_base_currency' => 'boolean',
        'is_active' => 'boolean',
        'decimal_places' => 'integer',
    ];

    /**
     * Boot method to ensure only one base currency
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($currency) {
            if ($currency->is_base_currency) {
                // Remove base currency flag from all other currencies
                static::where('id', '!=', $currency->id)
                    ->update(['is_base_currency' => false]);
                    
                // Base currency always has exchange rate of 1
                $currency->exchange_rate = 1.0000;
            }
        });
    }

    /**
     * Scope to get active currencies
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get base currency
     */
    public function scopeBase($query)
    {
        return $query->where('is_base_currency', true);
    }

    /**
     * Get the base currency
     */
    public static function getBase()
    {
        return static::base()->active()->first();
    }

    /**
     * Format amount with currency symbol
     */
    public function format($amount): string
    {
        $formattedAmount = number_format(
            $amount,
            $this->decimal_places,
            $this->decimal_separator,
            $this->thousands_separator
        );

        return $this->symbol_position === 'before'
            ? $this->currency_symbol . $formattedAmount
            : $formattedAmount . $this->currency_symbol;
    }

    /**
     * Convert amount from base currency to this currency
     */
    public function convertFromBase($amount): float
    {
        return round($amount * $this->exchange_rate, $this->decimal_places);
    }

    /**
     * Convert amount from this currency to base currency
     */
    public function convertToBase($amount): float
    {
        return round($amount / $this->exchange_rate, $this->decimal_places);
    }
}
