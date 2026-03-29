<?php

namespace App\Modules\Procurement\Models;

use App\Models\User;
use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Models\AccountConnection;
use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Models\Order;
use App\Modules\Procurement\Enums\ProcurementWorkflowStage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProcurementRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'request_number',
        'procurement_submission_id',
        'retailer_account_id',
        'supplier_account_id',
        'account_connection_id',
        'customer_id',
        'submitted_by_user_id',
        'source_order_id',
        'quote_order_id',
        'invoice_order_id',
        'current_stage',
        'line_item_count',
        'quantity_total',
        'subtotal',
        'currency',
        'notes',
        'submitted_at',
        'supplier_reviewed_at',
        'quoted_at',
        'approved_at',
        'invoiced_at',
        'fulfilled_at',
        'cancelled_at',
        'meta',
    ];

    protected $casts = [
        'current_stage' => ProcurementWorkflowStage::class,
        'line_item_count' => 'integer',
        'quantity_total' => 'integer',
        'subtotal' => 'decimal:2',
        'submitted_at' => 'datetime',
        'supplier_reviewed_at' => 'datetime',
        'quoted_at' => 'datetime',
        'approved_at' => 'datetime',
        'invoiced_at' => 'datetime',
        'fulfilled_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'meta' => 'array',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(ProcurementSubmission::class, 'procurement_submission_id');
    }

    public function retailerAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'retailer_account_id');
    }

    public function supplierAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'supplier_account_id');
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(AccountConnection::class, 'account_connection_id');
    }

    public function accountConnection(): BelongsTo
    {
        return $this->connection();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function sourceCustomer(): BelongsTo
    {
        return $this->customer();
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function sourceOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'source_order_id');
    }

    public function quoteOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'quote_order_id');
    }

    public function invoiceOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'invoice_order_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProcurementRequestItem::class);
    }

    public function scopeForRetailer($query, Account $account)
    {
        return $query->where('retailer_account_id', $account->id);
    }

    public function scopeForRetailerAccount($query, int|Account $accountId)
    {
        return $query->where('retailer_account_id', $accountId instanceof Account ? $accountId->id : $accountId);
    }

    public function scopeForSupplier($query, Account $account)
    {
        return $query->where('supplier_account_id', $account->id);
    }

    public function scopeForSupplierAccount($query, int|Account $accountId)
    {
        return $query->where('supplier_account_id', $accountId instanceof Account ? $accountId->id : $accountId);
    }

    public function getWorkflowStageAttribute(): ?ProcurementWorkflowStage
    {
        return $this->current_stage;
    }

    public function setWorkflowStageAttribute(ProcurementWorkflowStage|string|null $value): void
    {
        $this->current_stage = $value;
    }
}
