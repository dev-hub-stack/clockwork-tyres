<?php

namespace App\Modules\Accounts\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountOnboarding extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'owner_user_id',
        'account_mode',
        'plan_preference',
        'country',
        'supporting_document_path',
        'supporting_document_name',
        'registration_source',
        'status',
        'accepts_terms',
        'accepts_privacy',
        'meta',
    ];

    protected $casts = [
        'accepts_terms' => 'boolean',
        'accepts_privacy' => 'boolean',
        'meta' => 'array',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }
}
