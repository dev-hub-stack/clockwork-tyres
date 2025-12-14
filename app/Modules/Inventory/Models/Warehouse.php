<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    use HasFactory;

    protected $fillable = [
        'warehouse_name',
        'code',
        'address',
        'city',
        'state',
        'country',
        'postal_code',
        'lat',
        'lng',
        'phone',
        'email',
        'status',
        'is_primary',
        'is_system',
    ];

    protected $casts = [
        'lat' => 'decimal:8',
        'lng' => 'decimal:8',
        'status' => 'integer',
        'is_primary' => 'boolean',
        'is_system' => 'boolean',
    ];

    /**
     * Get all inventory records for this warehouse
     */
    public function inventories(): HasMany
    {
        return $this->hasMany(ProductInventory::class);
    }

    /**
     * Get all inventory logs for this warehouse
     */
    public function inventoryLogs(): HasMany
    {
        return $this->hasMany(InventoryLog::class);
    }

    /**
     * Scope to get only active warehouses
     */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    /**
     * Scope to get the primary warehouse
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    /**
     * Accessor for 'name' attribute (maps to warehouse_name)
     * This allows Filament relationships to work with ->relationship('warehouse', 'name')
     */
    public function getNameAttribute(): string
    {
        return $this->warehouse_name ?? '';
    }

    /**
     * Calculate distance between this warehouse and given coordinates using Haversine formula
     * 
     * @param float $latitude
     * @param float $longitude
     * @param string $unit 'K' = kilometers, 'M' = miles, 'N' = nautical miles
     * @return float|null Distance in specified unit, or null if warehouse has no coordinates
     */
    public function distanceTo(float $latitude, float $longitude, string $unit = 'M'): ?float
    {
        if (!$this->lat || !$this->lng) {
            return null;
        }

        $theta = $this->lng - $longitude;
        $dist = sin(deg2rad($this->lat)) * sin(deg2rad($latitude)) 
              + cos(deg2rad($this->lat)) * cos(deg2rad($latitude)) * cos(deg2rad($theta));
        
        $dist = acos($dist);
        $dist = rad2deg($dist);
        $miles = $dist * 60 * 1.1515;

        return match($unit) {
            'K' => $miles * 1.609344,      // Convert to kilometers
            'N' => $miles * 0.8684,        // Convert to nautical miles
            default => $miles,              // Miles (default)
        };
    }

    /**
     * Get inventory for a specific inventoriable item (Product, ProductVariant, or AddOn)
     * 
     * @param string $type 'product', 'variant', or 'addon'
     * @param int $id
     * @return ProductInventory|null
     */
    public function getInventoryFor(string $type, int $id): ?ProductInventory
    {
        $column = match($type) {
            'product' => 'product_id',
            'variant' => 'product_variant_id',
            'addon' => 'add_on_id',
            default => null,
        };

        if (!$column) {
            return null;
        }

        return $this->inventories()->where($column, $id)->first();
    }

    /**
     * Get total quantity in stock for a specific item
     * 
     * @param string $type 'product', 'variant', or 'addon'
     * @param int $id
     * @return int Current quantity in this warehouse
     */
    public function getQuantityFor(string $type, int $id): int
    {
        $inventory = $this->getInventoryFor($type, $id);
        return $inventory ? $inventory->quantity : 0;
    }

    /**
     * Get total available quantity (current + inbound) for a specific item
     * 
     * @param string $type 'product', 'variant', or 'addon'
     * @param int $id
     * @return int Total available (current + eta_qty)
     */
    public function getTotalAvailableFor(string $type, int $id): int
    {
        $inventory = $this->getInventoryFor($type, $id);
        return $inventory ? ($inventory->quantity + $inventory->eta_qty) : 0;
    }
}
