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
            'user_id' => auth()->id(),
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
}
