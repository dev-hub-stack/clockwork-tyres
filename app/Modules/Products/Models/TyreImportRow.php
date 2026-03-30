<?php

namespace App\Modules\Products\Models;

use App\Modules\Accounts\Models\Account;
use App\Modules\Products\Enums\TyreImportRowStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TyreImportRow extends Model
{
    protected $fillable = [
        'batch_id',
        'account_id',
        'source_row_number',
        'status',
        'source_sku',
        'normalized_brand',
        'normalized_model',
        'normalized_full_size',
        'normalized_dot_year',
        'storefront_merge_key',
        'source_row_hash',
        'normalized_row_hash',
        'duplicate_of_row_id',
        'raw_payload',
        'normalized_payload',
        'validation_errors',
        'validation_warnings',
        'dedupe_signals',
    ];

    protected $casts = [
        'source_row_number' => 'integer',
        'status' => TyreImportRowStatus::class,
        'raw_payload' => 'array',
        'normalized_payload' => 'array',
        'validation_errors' => 'array',
        'validation_warnings' => 'array',
        'dedupe_signals' => 'array',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(TyreImportBatch::class, 'batch_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duplicate_of_row_id');
    }
}
