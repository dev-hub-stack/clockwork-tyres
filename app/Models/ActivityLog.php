<?php

namespace App\Models;

use App\Modules\Customers\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ActivityLog extends Model
{
    public const ACTION_LABELS = [
        'quote_created' => 'Quote Created',
        'invoice_created' => 'Invoice Created',
        'quote_converted_to_invoice' => 'Quote Converted',
        'payment_recorded' => 'Payment Recorded',
        'payment_refunded' => 'Payment Refunded',
        'product_added' => 'Product Added',
        'product_updated' => 'Product Updated',
        'inventory_stock_in' => 'Inventory Added',
        'inventory_adjusted' => 'Inventory Adjusted',
        'user_login' => 'User Login',
        'user_logout' => 'User Logout',
        'dealer_login' => 'Logged In',
        'dealer_added_to_cart' => 'Added to Cart',
        'dealer_removed_from_cart' => 'Removed from Cart',
        'dealer_added_to_wishlist' => 'Added to Wishlist',
        'dealer_removed_from_wishlist' => 'Removed from Wishlist',
        'dealer_viewed_product' => 'Viewed Product',
        'dealer_checkout_started' => 'Checkout Started',
        'dealer_placed_order' => 'Placed Order',
        'dealer_payment_submitted' => 'Payment Submitted',
        'dealer_payment_failed' => 'Payment Failed',
    ];

    public const DEALER_ACTION_LABELS = [
        'dealer_login' => 'Logged In',
        'dealer_added_to_cart' => 'Added to Cart',
        'dealer_removed_from_cart' => 'Removed from Cart',
        'dealer_added_to_wishlist' => 'Added to Wishlist',
        'dealer_removed_from_wishlist' => 'Removed from Wishlist',
        'dealer_viewed_product' => 'Viewed Product',
        'dealer_checkout_started' => 'Checkout Started',
        'dealer_placed_order' => 'Placed Order',
        'dealer_payment_submitted' => 'Payment Submitted',
        'dealer_payment_failed' => 'Payment Failed',
    ];

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'customer_id',
        'action',
        'model_type',
        'model_id',
        'description',
        'ip_address',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function getActionLabelAttribute(): string
    {
        return self::ACTION_LABELS[$this->action] ?? Str::headline(str_replace('_', ' ', $this->action));
    }

    public function getModelLabelAttribute(): string
    {
        return $this->model_type ? Str::headline(class_basename($this->model_type)) : '-';
    }
}