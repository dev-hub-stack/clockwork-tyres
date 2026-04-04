<?php

namespace App\Modules\Inventory\Models;

use App\Models\User;
use App\Modules\Products\Models\TyreAccountOffer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryLog extends Model
{
    use HasFactory;

    // Only use created_at timestamp (logs are immutable)
    public const UPDATED_AT = null;

    protected $fillable = [
        'warehouse_id',
        'product_id',
        'product_variant_id',
        'tyre_account_offer_id',
        'add_on_id',
        'action',
        'quantity_before',
        'quantity_after',
        'quantity_change',
        'eta_before',
        'eta_after',
        'eta_qty_before',
        'eta_qty_after',
        'reference_type',
        'reference_id',
        'notes',
        'user_id',
    ];

    protected $casts = [
        'quantity_before' => 'integer',
        'quantity_after' => 'integer',
        'quantity_change' => 'integer',
        'eta_qty_before' => 'integer',
        'eta_qty_after' => 'integer',
    ];

    /**
     * Available action types
     */
    public const ACTION_ADJUSTMENT = 'adjustment';
    public const ACTION_TRANSFER_IN = 'transfer_in';
    public const ACTION_TRANSFER_OUT = 'transfer_out';
    public const ACTION_SALE = 'sale';
    public const ACTION_RETURN = 'return';
    public const ACTION_IMPORT = 'import';

    /**
     * Get the warehouse for this log entry
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Get the inventory record for this log entry
     */
    public function productInventory(): BelongsTo
    {
        return $this->belongsTo(ProductInventory::class);
    }

    /**
     * Get the tyre account offer for this log entry
     */
    public function tyreAccountOffer(): BelongsTo
    {
        return $this->belongsTo(TyreAccountOffer::class);
    }

    /**
     * Get the user who made this change
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getInventoryTypeAttribute(): string
    {
        if ($this->tyre_account_offer_id) {
            return 'tyres';
        }

        if ($this->add_on_id) {
            return 'addons';
        }

        return 'products';
    }

    /**
     * Scope to filter by warehouse
     */
    public function scopeForWarehouse($query, int $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    /**
     * Scope to filter by action type
     */
    public function scopeByAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope to filter by reference
     */
    public function scopeByReference($query, string $type, int $id)
    {
        return $query->where('reference_type', $type)
                    ->where('reference_id', $id);
    }

    /**
     * Scope to filter by user
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Get formatted action name
     */
    public function getActionNameAttribute(): string
    {
        return match($this->action) {
            self::ACTION_ADJUSTMENT => 'Manual Adjustment',
            self::ACTION_TRANSFER_IN => 'Transfer In',
            self::ACTION_TRANSFER_OUT => 'Transfer Out',
            self::ACTION_SALE => 'Sale',
            self::ACTION_RETURN => 'Return',
            self::ACTION_IMPORT => 'Excel Import',
            default => ucfirst(str_replace('_', ' ', $this->action)),
        };
    }

    /**
     * Check if this was a quantity increase
     */
    public function getIsIncreaseAttribute(): bool
    {
        return $this->quantity_change > 0;
    }

    /**
     * Check if this was a quantity decrease
     */
    public function getIsDecreaseAttribute(): bool
    {
        return $this->quantity_change < 0;
    }

    /**
     * Check if ETA changed
     */
    public function getEtaChangedAttribute(): bool
    {
        return $this->eta_before !== $this->eta_after;
    }

    /**
     * Check if ETA quantity changed
     */
    public function getEtaQtyChangedAttribute(): bool
    {
        return $this->eta_qty_before !== $this->eta_qty_after;
    }
}
