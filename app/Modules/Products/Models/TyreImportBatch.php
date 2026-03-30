<?php

namespace App\Modules\Products\Models;

use App\Models\User;
use App\Modules\Accounts\Models\Account;
use App\Modules\Products\Enums\TyreImportBatchStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TyreImportBatch extends Model
{
    protected $fillable = [
        'account_id',
        'uploaded_by_user_id',
        'category',
        'source_format',
        'source_file_name',
        'source_file_size_bytes',
        'source_file_hash',
        'sheet_name',
        'contract_version',
        'mapping_version',
        'status',
        'total_rows',
        'staged_rows',
        'valid_rows',
        'invalid_rows',
        'duplicate_rows',
        'source_headers',
        'normalized_headers',
        'validation_summary',
        'meta',
    ];

    protected $casts = [
        'status' => TyreImportBatchStatus::class,
        'source_file_size_bytes' => 'integer',
        'contract_version' => 'integer',
        'total_rows' => 'integer',
        'staged_rows' => 'integer',
        'valid_rows' => 'integer',
        'invalid_rows' => 'integer',
        'duplicate_rows' => 'integer',
        'source_headers' => 'array',
        'normalized_headers' => 'array',
        'validation_summary' => 'array',
        'meta' => 'array',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(TyreImportRow::class, 'batch_id');
    }
}
