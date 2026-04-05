<?php

namespace App\Modules\Accounts\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPlanCatalog extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_mode',
        'plan_code',
        'display_name',
        'billing_mode',
        'amount_minor',
        'currency',
        'billing_interval',
        'trial_days',
        'is_self_serve',
        'is_manual',
        'stripe_product_id',
        'stripe_price_id',
        'features',
        'meta',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'trial_days' => 'integer',
        'is_self_serve' => 'boolean',
        'is_manual' => 'boolean',
        'features' => 'array',
        'meta' => 'array',
    ];

    public function requiresStripeCheckout(): bool
    {
        return $this->billing_mode === 'stripe_subscription' && $this->is_self_serve;
    }
}
