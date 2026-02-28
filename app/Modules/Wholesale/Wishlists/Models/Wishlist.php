<?php

namespace App\Modules\Wholesale\Wishlists\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\Customers\Models\Customer;
use App\Modules\Products\Models\ProductVariant;

class Wishlist extends Model
{
    protected $table = 'wholesale_wishlists';

    protected $fillable = [
        'dealer_id',
        'product_variant_id',
    ];

    public function dealer()
    {
        return $this->belongsTo(Customer::class, 'dealer_id');
    }

    public function productVariant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
