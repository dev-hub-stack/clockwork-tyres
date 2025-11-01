<?php

namespace App\Modules\Warranties\Models;

use App\Models\User;
use App\Modules\Warranties\Enums\ClaimActionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarrantyClaimHistory extends Model
{
    protected $table = 'warranty_claim_history';
    
    protected $fillable = [
        'warranty_claim_id',
        'user_id',
        'action_type',
        'description',
        'metadata',
    ];

    protected $casts = [
        'action_type' => ClaimActionType::class,
        'metadata' => 'array',
    ];

    // ========== RELATIONSHIPS ==========

    public function warrantyClaim(): BelongsTo
    {
        return $this->belongsTo(WarrantyClaim::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ========== ACCESSORS ==========

    /**
     * Get formatted description with user name
     */
    public function getFormattedDescriptionAttribute(): string
    {
        $userName = $this->user->name ?? 'Unknown User';
        $date = $this->created_at->format('M d, Y');
        $time = $this->created_at->format('g:i A');
        
        return "{$date} at {$time} - {$userName}: {$this->description}";
    }

    /**
     * Get relative time (e.g., "2 hours ago")
     */
    public function getRelativeTimeAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }

    /**
     * Check if this history entry has a video link
     */
    public function hasVideoLink(): bool
    {
        return $this->action_type === ClaimActionType::VIDEO_LINK_ADDED && 
               isset($this->metadata['url']);
    }

    /**
     * Get video URL from metadata
     */
    public function getVideoUrlAttribute(): ?string
    {
        return $this->metadata['url'] ?? null;
    }

    /**
     * Check if this history entry has file attachments
     */
    public function hasFileAttachment(): bool
    {
        return $this->action_type === ClaimActionType::FILE_ATTACHED && 
               isset($this->metadata['file_path']);
    }

    /**
     * Get file path from metadata
     */
    public function getFilePathAttribute(): ?string
    {
        return $this->metadata['file_path'] ?? null;
    }
}
