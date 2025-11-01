<?php

namespace App\Modules\Warranties\Enums;

enum WarrantyClaimStatus: string
{
    case DRAFT = 'draft';
    case PENDING = 'pending';
    case REPLACED = 'replaced';
    case CLAIMED = 'claimed';
    case RETURNED = 'returned';
    case VOID = 'void';
    
    public function getLabel(): string
    {
        return match($this) {
            self::DRAFT => 'Draft',
            self::PENDING => 'Pending',
            self::REPLACED => 'Replaced',
            self::CLAIMED => 'Claimed',
            self::RETURNED => 'Returned',
            self::VOID => 'Void',
        };
    }
    
    public function getColor(): string
    {
        return match($this) {
            self::DRAFT => 'gray',
            self::PENDING => 'warning',
            self::REPLACED => 'warning',
            self::CLAIMED => 'success',
            self::RETURNED => 'info',
            self::VOID => 'danger',
        };
    }
    
    public function getIcon(): string
    {
        return match($this) {
            self::DRAFT => 'heroicon-o-document',
            self::PENDING => 'heroicon-o-clock',
            self::REPLACED => 'heroicon-o-arrow-path',
            self::CLAIMED => 'heroicon-o-check-circle',
            self::RETURNED => 'heroicon-o-arrow-uturn-left',
            self::VOID => 'heroicon-o-x-circle',
        };
    }
}
