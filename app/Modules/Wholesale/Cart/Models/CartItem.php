<?php

namespace App\Modules\Wholesale\Cart\Models;

use App\Modules\Products\Models\ProductVariant;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    protected $table = 'wholesale_cart_items';

    protected $fillable = [
        'cart_id',
        'product_variant_id',
        'warehouse_id',
        'quantity',
        'unit_price',
        'total_price',
        'type',
        'eta',
    ];

    protected $casts = [
        'unit_price'  => 'decimal:2',
        'total_price' => 'decimal:2',
        'eta'         => 'boolean',
        'quantity'    => 'integer',
    ];

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Inventory\Models\Warehouse::class);
    }
}
