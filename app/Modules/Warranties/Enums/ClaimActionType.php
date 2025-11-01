<?php

namespace App\Modules\Warranties\Enums;

enum ClaimActionType: string
{
    case CREATED = 'created';
    case NOTE_ADDED = 'note_added';
    case VIDEO_LINK_ADDED = 'video_link_added';
    case STATUS_CHANGED = 'status_changed';
    case FILE_ATTACHED = 'file_attached';
    case EMAIL_SENT = 'email_sent';
    case SUBMITTED = 'submitted';
    case ITEM_REPLACED = 'item_replaced';
    case ITEM_CLAIMED = 'item_claimed';
    case ITEM_RETURNED = 'item_returned';
    case RESOLVED = 'resolved';
    case VOIDED = 'voided';
    
    public function getLabel(): string
    {
        return match($this) {
            self::CREATED => 'Claim Created',
            self::NOTE_ADDED => 'Note Added',
            self::VIDEO_LINK_ADDED => 'Video Link Added',
            self::STATUS_CHANGED => 'Status Changed',
            self::FILE_ATTACHED => 'File Attached',
            self::EMAIL_SENT => 'Email Sent',
            self::SUBMITTED => 'Claim Submitted',
            self::ITEM_REPLACED => 'Item Replaced',
            self::ITEM_CLAIMED => 'Item Claimed',
            self::ITEM_RETURNED => 'Item Returned',
            self::RESOLVED => 'Claim Resolved',
            self::VOIDED => 'Claim Voided',
        };
    }
    
    public function getIcon(): string
    {
        return match($this) {
            self::CREATED => 'heroicon-o-plus-circle',
            self::NOTE_ADDED => 'heroicon-o-pencil',
            self::VIDEO_LINK_ADDED => 'heroicon-o-video-camera',
            self::STATUS_CHANGED => 'heroicon-o-arrow-path',
            self::FILE_ATTACHED => 'heroicon-o-paper-clip',
            self::EMAIL_SENT => 'heroicon-o-envelope',
            self::SUBMITTED => 'heroicon-o-paper-airplane',
            self::ITEM_REPLACED => 'heroicon-o-arrow-path-rounded-square',
            self::ITEM_CLAIMED => 'heroicon-o-check-circle',
            self::ITEM_RETURNED => 'heroicon-o-arrow-uturn-left',
            self::RESOLVED => 'heroicon-o-check-badge',
            self::VOIDED => 'heroicon-o-x-mark',
        };
    }
    
    public function getColor(): string
    {
        return match($this) {
            self::CREATED => 'gray',
            self::NOTE_ADDED => 'gray',
            self::VIDEO_LINK_ADDED => 'blue',
            self::STATUS_CHANGED => 'green',
            self::FILE_ATTACHED => 'purple',
            self::EMAIL_SENT => 'orange',
            self::SUBMITTED => 'info',
            self::ITEM_REPLACED => 'warning',
            self::ITEM_CLAIMED => 'success',
            self::ITEM_RETURNED => 'info',
            self::RESOLVED => 'success',
            self::VOIDED => 'danger',
        };
    }
}
