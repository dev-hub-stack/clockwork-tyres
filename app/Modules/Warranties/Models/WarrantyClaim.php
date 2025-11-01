<?php

namespace App\Modules\Warranties\Models;

use App\Models\User;
use App\Modules\Customers\Models\Customer;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Orders\Models\Order;
use App\Modules\Warranties\Enums\ClaimActionType;
use App\Modules\Warranties\Enums\WarrantyClaimStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class WarrantyClaim extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'claim_number',
        'customer_id',
        'warehouse_id',
        'representative_id',
        'invoice_id', // OPTIONAL - Cannot be changed after creation
        'status',
        'issue_date',
        'claim_date',
        'resolution_date',
        'notes',
        'internal_notes',
        'created_by',
        'resolved_by',
    ];

    protected $casts = [
        'status' => WarrantyClaimStatus::class,
        'issue_date' => 'date',
        'claim_date' => 'date',
        'resolution_date' => 'date',
    ];

    protected $attributes = [
        'status' => 'draft',
    ];

    // ========== LIFECYCLE HOOKS ==========

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($claim) {
            // Auto-generate claim number if not provided
            if (empty($claim->claim_number)) {
                $claim->claim_number = self::generateClaimNumber();
            }

            // Set issue_date to claim_date if not provided
            if (empty($claim->issue_date) && !empty($claim->claim_date)) {
                $claim->issue_date = $claim->claim_date;
            }

            // Set claim_date to today if not provided
            if (empty($claim->claim_date)) {
                $claim->claim_date = now();
            }

            // Set created_by if not provided
            if (empty($claim->created_by) && auth()->check()) {
                $claim->created_by = auth()->id();
            }
        });
    }

    // ========== RELATIONSHIPS ==========

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function representative(): BelongsTo
    {
        return $this->belongsTo(User::class, 'representative_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'invoice_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(WarrantyClaimItem::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(WarrantyClaimHistory::class)->latest();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    // ========== SCOPES ==========

    public function scopeRecent(Builder $query): Builder
    {
        return $query->latest('claim_date');
    }

    public function scopeByStatus(Builder $query, WarrantyClaimStatus $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', WarrantyClaimStatus::PENDING);
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', WarrantyClaimStatus::DRAFT);
    }

    public function scopeResolved(Builder $query): Builder
    {
        return $query->whereIn('status', [
            WarrantyClaimStatus::REPLACED,
            WarrantyClaimStatus::CLAIMED,
            WarrantyClaimStatus::RETURNED,
        ]);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            WarrantyClaimStatus::VOID,
        ]);
    }

    // ========== HELPER METHODS ==========

    /**
     * Add a history entry to the claim
     */
    public function addHistory(ClaimActionType $type, string $description, ?array $metadata = null): void
    {
        $this->histories()->create([
            'user_id' => auth()->id() ?? $this->created_by ?? 1, // Fallback to claim creator or system user
            'action_type' => $type,
            'description' => $description,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Add a note to the claim
     */
    public function addNote(string $note): void
    {
        $this->addHistory(
            ClaimActionType::NOTE_ADDED,
            $note
        );
    }

    /**
     * Add a video link to the claim
     */
    public function addVideoLink(string $url, ?string $description = null): void
    {
        $this->addHistory(
            ClaimActionType::VIDEO_LINK_ADDED,
            $description ?? "Video link added: {$url}",
            ['url' => $url]
        );
    }

    /**
     * Mark claim as replaced
     */
    public function markAsReplaced(?string $notes = null): void
    {
        $this->update([
            'status' => WarrantyClaimStatus::REPLACED,
            'resolution_date' => now(),
            'resolved_by' => auth()->id(),
        ]);

        $this->addHistory(
            ClaimActionType::STATUS_CHANGED,
            "Status changed to: Replaced" . ($notes ? " - {$notes}" : '')
        );
    }

    /**
     * Mark claim as claimed/approved
     */
    public function markAsClaimed(?string $notes = null): void
    {
        $this->update([
            'status' => WarrantyClaimStatus::CLAIMED,
            'resolution_date' => now(),
            'resolved_by' => auth()->id(),
        ]);

        $this->addHistory(
            ClaimActionType::STATUS_CHANGED,
            "Status changed to: Claimed" . ($notes ? " - {$notes}" : '')
        );
    }

    /**
     * Void the claim
     */
    public function void(string $reason): void
    {
        $this->update([
            'status' => WarrantyClaimStatus::VOID,
        ]);

        $this->addHistory(
            ClaimActionType::VOIDED,
            "Claim voided: {$reason}"
        );
    }

    /**
     * Change status with history logging
     */
    public function changeStatus(WarrantyClaimStatus $newStatus, ?string $notes = null): void
    {
        $oldStatus = $this->status;
        
        $this->update(['status' => $newStatus]);

        $description = "Status changed from {$oldStatus->getLabel()} to {$newStatus->getLabel()}";
        if ($notes) {
            $description .= " - {$notes}";
        }

        $this->addHistory(
            ClaimActionType::STATUS_CHANGED,
            $description
        );
    }

    /**
     * Get total quantity of items claimed
     */
    public function getTotalQuantityAttribute(): int
    {
        return $this->items()->sum('quantity');
    }

    /**
     * Check if claim is locked (invoice cannot be changed)
     */
    public function isLocked(): bool
    {
        return $this->invoice_id !== null && $this->exists;
    }

    /**
     * Check if claim can be edited
     */
    public function canBeEdited(): bool
    {
        return $this->status === WarrantyClaimStatus::DRAFT;
    }

    /**
     * Check if claim is resolved
     */
    public function isResolved(): bool
    {
        return in_array($this->status, [
            WarrantyClaimStatus::REPLACED,
            WarrantyClaimStatus::CLAIMED,
            WarrantyClaimStatus::RETURNED,
        ]);
    }

    /**
     * Generate a unique claim number
     * Format: WXXXYYYY where XXX is current year, YYYY is sequential number
     */
    public static function generateClaimNumber(): string
    {
        $year = now()->format('y'); // 2-digit year (e.g., 25 for 2025)
        
        // Get the last claim number for this year
        $lastClaim = self::whereYear('created_at', now()->year)
            ->orderBy('claim_number', 'desc')
            ->first();
        
        if ($lastClaim && $lastClaim->claim_number) {
            // Extract the numeric part and increment
            $lastNumber = (int) substr($lastClaim->claim_number, 3); // Skip 'WXX' prefix
            $nextNumber = $lastNumber + 1;
        } else {
            // Start from 1 for this year
            $nextNumber = 1;
        }
        
        // Format: WXX (W + 2-digit year) + 4-digit sequential number
        return sprintf('W%s%04d', $year, $nextNumber);
    }
}
