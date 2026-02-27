<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Consignments\Models\Consignment;
use App\Modules\Products\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DamagedInventory extends Model
{
    use HasFactory;

    protected $table = 'damaged_inventories';

    protected $fillable = [
        'product_variant_id',
        'warehouse_id',
        'quantity',
        'condition',
        'notes',
        'consignment_id',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'product_variant_id' => 'integer',
        'warehouse_id' => 'integer',
        'consignment_id' => 'integer',
    ];

    /**
     * Get the product variant
     */
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /**
     * Get the warehouse
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Get the consignment
     */
    public function consignment(): BelongsTo
    {
        return $this->belongsTo(Consignment::class);
    }
}
