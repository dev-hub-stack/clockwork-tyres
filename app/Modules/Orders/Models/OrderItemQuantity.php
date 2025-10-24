<?php

namespace App\Modules\Orders\Models;

use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItemQuantity extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_item_id',
        'warehouse_id',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    protected $attributes = [
        'quantity' => 0,
    ];

    /**
     * Relationships
     */

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Get the order through the order item
     */
    public function order()
    {
        return $this->orderItem->order();
    }

    /**
     * Validation: Quantity must be positive
     */
    protected static function booted()
    {
        static::saving(function ($orderItemQuantity) {
            if ($orderItemQuantity->quantity < 0) {
                throw new \Exception('Order item quantity cannot be negative');
            }
        });
    }

    /**
     * Check if this allocation is from the primary warehouse
     */
    public function isPrimaryWarehouse(): bool
    {
        return $this->warehouse_id === $this->orderItem->order->warehouse_id;
    }

    /**
     * Get warehouse name
     */
    public function getWarehouseNameAttribute(): string
    {
        return $this->warehouse->name ?? 'Unknown Warehouse';
    }
}
