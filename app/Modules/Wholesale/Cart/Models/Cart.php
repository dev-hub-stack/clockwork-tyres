<?php

namespace App\Modules\Wholesale\Cart\Models;

use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Models\AddressBook;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Cart extends Model
{
    use SoftDeletes;

    protected $table = 'wholesale_carts';

    protected $fillable = [
        'session_id',
        'dealer_id',
        'order_number',
        'coupon_id',
        'discount',
        'sub_total',
        'shipping',
        'vat',
        'total',
        'checkout_address_id',
        'shipping_option',
        'notes',
    ];

    protected $appends = [
        'count',
        'eta',
    ];

    protected $casts = [
        'discount'  => 'decimal:2',
        'sub_total' => 'decimal:2',
        'shipping'  => 'decimal:2',
        'vat'       => 'decimal:2',
        'total'     => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (Cart $cart) {
            if (! $cart->order_number) {
                $cart->order_number = 'CART-' . strtoupper(Str::random(8));
            }
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function addons(): HasMany
    {
        return $this->hasMany(CartAddon::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function dealer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'dealer_id');
    }

    public function shippingAddress(): BelongsTo
    {
        return $this->belongsTo(AddressBook::class, 'checkout_address_id');
    }

    /**
     * Total item count (wheels + addons).
     * Matches Angular Cart.count field.
     */
    public function getCountAttribute(): int
    {
        return $this->items->sum('quantity') + $this->addons->sum('quantity');
    }

    /**
     * Whether any item in the cart is ETA (out of stock, inbound).
     * Matches Angular Cart.eta field.
     */
    public function getEtaAttribute(): bool
    {
        return $this->items->contains('eta', true);
    }
}
