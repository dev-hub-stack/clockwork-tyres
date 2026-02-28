<?php

namespace App\Modules\Wholesale\Cart\Models;

use App\Modules\Products\Models\AddOn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartAddon extends Model
{
    protected $table = 'wholesale_cart_addons';

    protected $fillable = [
        'cart_id',
        'addon_id',
        'quantity',
        'unit_price',
        'total_price',
    ];

    protected $casts = [
        'unit_price'  => 'decimal:2',
        'total_price' => 'decimal:2',
        'quantity'    => 'integer',
    ];

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function addon(): BelongsTo
    {
        return $this->belongsTo(AddOn::class, 'addon_id');
    }
}
