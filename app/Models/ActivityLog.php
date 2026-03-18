<?php

namespace App\Models;

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
    ];

    public $timestamps = false;

    protected $fillable = [
        'user_id',
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

    public function getActionLabelAttribute(): string
    {
        return self::ACTION_LABELS[$this->action] ?? Str::headline(str_replace('_', ' ', $this->action));
    }

    public function getModelLabelAttribute(): string
    {
        return $this->model_type ? Str::headline(class_basename($this->model_type)) : '-';
    }
}