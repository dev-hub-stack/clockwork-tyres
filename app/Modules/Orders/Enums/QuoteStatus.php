<?php

namespace App\Modules\Orders\Enums;

enum QuoteStatus: string
{
    case DRAFT = 'draft';
    case SENT = 'sent';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case CONVERTED = 'converted';

    /**
     * Get label for display
     */
    public function label(): string
    {
        return match($this) {
            self::DRAFT => 'Draft',
            self::SENT => 'Sent',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
            self::CONVERTED => 'Converted to Invoice',
        };
    }

    /**
     * Get color for badges
     */
    public function color(): string
    {
        return match($this) {
            self::DRAFT => 'gray',
            self::SENT => 'info',
            self::APPROVED => 'success',
            self::REJECTED => 'danger',
            self::CONVERTED => 'primary',
        };
    }

    /**
     * Get icon for display
     */
    public function icon(): string
    {
        return match($this) {
            self::DRAFT => 'heroicon-o-document',
            self::SENT => 'heroicon-o-paper-airplane',
            self::APPROVED => 'heroicon-o-check-circle',
            self::REJECTED => 'heroicon-o-x-circle',
            self::CONVERTED => 'heroicon-o-arrow-right-circle',
        };
    }

    /**
     * Check if quote can be converted
     */
    public function canConvert(): bool
    {
        return $this === self::SENT;
    }

    /**
     * Check if quote can be edited
     */
    public function canEdit(): bool
    {
        return in_array($this, [self::DRAFT]);
    }

    /**
     * Check if quote can be sent
     */
    public function canSend(): bool
    {
        return in_array($this, [self::DRAFT, self::SENT]);
    }

    /**
     * Get all values as array
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get all options for select dropdown
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(function ($case) {
            return [$case->value => $case->label()];
        })->toArray();
    }
}
