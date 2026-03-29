<?php

namespace App\Modules\Accounts\Models;

use App\Modules\Accounts\Enums\AccountConnectionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountConnection extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'retailer_account_id',
        'supplier_account_id',
        'status',
        'approved_at',
        'notes',
    ];

    protected $casts = [
        'status' => AccountConnectionStatus::class,
        'approved_at' => 'datetime',
    ];

    public function retailerAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'retailer_account_id');
    }

    public function supplierAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'supplier_account_id');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', AccountConnectionStatus::Approved);
    }

    public function approve(?string $notes = null): void
    {
        $this->forceFill([
            'status' => AccountConnectionStatus::Approved,
            'approved_at' => now(),
            'notes' => $notes ?? $this->notes,
        ])->save();
    }

    public function isApproved(): bool
    {
        return $this->status === AccountConnectionStatus::Approved;
    }
}
