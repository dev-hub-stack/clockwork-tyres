<?php

namespace App\Modules\Accounts\Models;

use App\Models\User;
use App\Modules\Accounts\Enums\AccountConnectionStatus;
use App\Modules\Accounts\Enums\AccountMembershipRole;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountSubscriptionPlan;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Customers\Models\Customer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Account extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'account_type',
        'retail_enabled',
        'wholesale_enabled',
        'status',
        'base_subscription_plan',
        'reports_subscription_enabled',
        'reports_customer_limit',
        'created_by_user_id',
    ];

    protected $casts = [
        'account_type' => AccountType::class,
        'retail_enabled' => 'boolean',
        'wholesale_enabled' => 'boolean',
        'status' => AccountStatus::class,
        'base_subscription_plan' => AccountSubscriptionPlan::class,
        'reports_subscription_enabled' => 'boolean',
        'reports_customer_limit' => 'integer',
        'created_by_user_id' => 'integer',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'account_user')
            ->using(AccountMembership::class)
            ->withPivot(['id', 'role', 'is_default'])
            ->withTimestamps();
    }

    public function members(): BelongsToMany
    {
        return $this->users();
    }

    public function connectionsAsRetailer(): HasMany
    {
        return $this->hasMany(AccountConnection::class, 'retailer_account_id');
    }

    public function connectionsAsSupplier(): HasMany
    {
        return $this->hasMany(AccountConnection::class, 'supplier_account_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(AccountSubscription::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function approvedSupplierConnections(): HasMany
    {
        return $this->connectionsAsRetailer()
            ->where('status', AccountConnectionStatus::Approved->value);
    }

    public function approvedRetailerConnections(): HasMany
    {
        return $this->connectionsAsSupplier()
            ->where('status', AccountConnectionStatus::Approved->value);
    }

    public function scopeActive($query)
    {
        return $query->where('status', AccountStatus::ACTIVE->value);
    }

    public function scopeRetailEnabled($query)
    {
        return $query->where('retail_enabled', true);
    }

    public function scopeWholesaleEnabled($query)
    {
        return $query->where('wholesale_enabled', true);
    }

    public function hasRole(User $user, AccountMembershipRole $role): bool
    {
        return $this->users()
            ->where('users.id', $user->id)
            ->wherePivot('role', $role->value)
            ->exists();
    }

    public function isWholesalerEnabled(): bool
    {
        return $this->wholesale_enabled || $this->account_type === AccountType::Both;
    }

    public function isRetailEnabled(): bool
    {
        return $this->retail_enabled || $this->account_type === AccountType::Both;
    }

    public function isRetailer(): bool
    {
        return in_array($this->account_type, [AccountType::Retailer, AccountType::Both], true);
    }

    public function isSupplier(): bool
    {
        return in_array($this->account_type, [AccountType::Supplier, AccountType::Both], true);
    }

    public function supportsRetailStorefront(): bool
    {
        return $this->retail_enabled && $this->isRetailer();
    }

    public function supportsWholesalePortal(): bool
    {
        return $this->wholesale_enabled && $this->isSupplier();
    }

    public function hasPremiumSubscription(): bool
    {
        return $this->base_subscription_plan === AccountSubscriptionPlan::Premium;
    }

    public function hasReportsSubscription(): bool
    {
        return $this->reports_subscription_enabled;
    }
}
