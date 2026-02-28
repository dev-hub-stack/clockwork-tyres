<?php

namespace App\Modules\Wholesale\Reviews\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\Customers\Models\Customer;
use App\Modules\Products\Models\Product;

class ProductReview extends Model
{
    protected $table = 'wholesale_product_reviews';

    protected $fillable = [
        'dealer_id',
        'product_id',
        'rating',
        'review',
        'is_approved',
    ];

    protected $casts = [
        'is_approved' => 'boolean',
    ];

    public function dealer()
    {
        return $this->belongsTo(Customer::class, 'dealer_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
