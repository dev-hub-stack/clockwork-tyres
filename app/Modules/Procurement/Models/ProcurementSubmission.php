<?php

namespace App\Modules\Procurement\Models;

use App\Models\User;
use App\Modules\Accounts\Models\Account;
use App\Modules\Procurement\Enums\ProcurementWorkflowStage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProcurementSubmission extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'submission_number',
        'retailer_account_id',
        'submitted_by_user_id',
        'status',
        'supplier_count',
        'request_count',
        'line_item_count',
        'quantity_total',
        'subtotal',
        'currency',
        'source',
        'meta',
        'submitted_at',
    ];

    protected $casts = [
        'status' => ProcurementWorkflowStage::class,
        'supplier_count' => 'integer',
        'request_count' => 'integer',
        'line_item_count' => 'integer',
        'quantity_total' => 'integer',
        'subtotal' => 'decimal:2',
        'meta' => 'array',
        'submitted_at' => 'datetime',
    ];

    public function retailerAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'retailer_account_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(ProcurementRequest::class);
    }
}
