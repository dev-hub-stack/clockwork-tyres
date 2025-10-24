<?php

namespace App\Modules\Orders\Models;

use App\Models\User;
use App\Modules\Customers\Models\Customer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Expense extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_id',
        'customer_id',
        'recorded_by',
        'expense_number',
        'expense_type',
        'amount',
        'expense_date',
        'currency',
        'vendor_name',
        'vendor_reference',
        'payment_status',
        'paid_date',
        'payment_method',
        'receipt_path',
        'description',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expense_date' => 'date',
        'paid_date' => 'date',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Expense type constants
     */
    const TYPE_SHIPPING = 'shipping';
    const TYPE_CUSTOMS = 'customs';
    const TYPE_PACKAGING = 'packaging';
    const TYPE_INSURANCE = 'insurance';
    const TYPE_HANDLING = 'handling';
    const TYPE_OTHER = 'other';

    /**
     * Get expense types for dropdown
     */
    public static function getExpenseTypes(): array
    {
        return [
            self::TYPE_SHIPPING => 'Shipping',
            self::TYPE_CUSTOMS => 'Customs/Duties',
            self::TYPE_PACKAGING => 'Packaging',
            self::TYPE_INSURANCE => 'Insurance',
            self::TYPE_HANDLING => 'Handling',
            self::TYPE_OTHER => 'Other',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($expense) {
            if (empty($expense->expense_number)) {
                $expense->expense_number = static::generateExpenseNumber();
            }
        });
    }

    /**
     * Generate unique expense number
     */
    public static function generateExpenseNumber(): string
    {
        $date = now()->format('Ymd');
        $count = static::whereDate('created_at', now())->count() + 1;
        return sprintf('EXP-%s-%04d', $date, $count);
    }

    /**
     * Get the order that owns the expense.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the customer related to the expense.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the user who recorded the expense.
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Get the receipt URL.
     */
    public function getReceiptUrlAttribute(): ?string
    {
        if (!$this->receipt_path) {
            return null;
        }

        return Storage::url($this->receipt_path);
    }

    /**
     * Scope to filter by expense type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('expense_type', $type);
    }

    /**
     * Scope to filter by payment status.
     */
    public function scopePaid($query)
    {
        return $query->where('payment_status', 'paid');
    }

    /**
     * Scope to filter by payment status.
     */
    public function scopeUnpaid($query)
    {
        return $query->where('payment_status', 'unpaid');
    }

    /**
     * Check if expense is paid.
     */
    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    /**
     * Check if expense is unpaid.
     */
    public function isUnpaid(): bool
    {
        return $this->payment_status === 'unpaid';
    }

    /**
     * Get formatted expense type label.
     */
    public function getExpenseTypeLabel(): string
    {
        $types = self::getExpenseTypes();
        return $types[$this->expense_type] ?? $this->expense_type;
    }
}
