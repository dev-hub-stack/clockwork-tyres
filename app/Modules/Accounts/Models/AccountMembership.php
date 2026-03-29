<?php

namespace App\Modules\Accounts\Models;

use App\Models\User;
use App\Modules\Accounts\Enums\AccountRole;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class AccountMembership extends Pivot
{
    protected $table = 'account_user';

    public $incrementing = true;

    protected $fillable = [
        'account_id',
        'user_id',
        'role',
        'is_default',
    ];

    protected $casts = [
        'role' => AccountRole::class,
        'is_default' => 'boolean',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
