<?php

namespace App\Modules\Accounts\Models;

use App\Models\User;
use App\Modules\Accounts\Enums\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountSubscription extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'account_id',
        'plan_code',
        'status',
        'reports_enabled',
        'reports_customer_limit',
        'starts_at',
        'ends_at',
        'meta',
        'created_by_user_id',
    ];

    protected $casts = [
        'plan_code' => SubscriptionPlan::class,
        'reports_enabled' => 'boolean',
        'reports_customer_limit' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'meta' => 'array',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
