<?php

namespace App\Modules\Settings\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaxSetting extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'rate',
        'is_default',
        'tax_inclusive_default',
        'is_active',
        'description',
    ];

    protected $casts = [
        'rate' => 'decimal:2',
        'is_default' => 'boolean',
        'tax_inclusive_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Boot method to ensure only one default tax setting
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($taxSetting) {
            if ($taxSetting->is_default) {
                // Remove default from all other tax settings
                static::where('id', '!=', $taxSetting->id)
                    ->update(['is_default' => false]);
            }
        });
    }

    /**
     * Scope to get active tax settings
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get default tax setting
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Get the default tax setting
     */
    public static function getDefault()
    {
        return static::default()->active()->first();
    }

    /**
     * Get formatted tax rate with percentage
     */
    public function getFormattedRateAttribute(): string
    {
        return $this->rate . '%';
    }
}
