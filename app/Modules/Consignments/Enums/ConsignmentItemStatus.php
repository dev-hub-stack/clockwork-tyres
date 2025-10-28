<?php

namespace App\Modules\Consignments\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ConsignmentItemStatus: string implements HasLabel, HasColor, HasIcon
{
    case SENT = 'sent';
    case SOLD = 'sold';
    case RETURNED = 'returned';
    case CANCELLED = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::SENT => 'Sent',
            self::SOLD => 'Sold',
            self::RETURNED => 'Returned',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::SENT => 'info',
            self::SOLD => 'success',
            self::RETURNED => 'warning',
            self::CANCELLED => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::SENT => 'heroicon-o-paper-airplane',
            self::SOLD => 'heroicon-o-check-circle',
            self::RETURNED => 'heroicon-o-arrow-uturn-left',
            self::CANCELLED => 'heroicon-o-x-circle',
        };
    }

    /**
     * Check if item can be sold
     */
    public function canBeSold(): bool
    {
        return $this === self::SENT;
    }

    /**
     * Check if item can be returned
     */
    public function canBeReturned(): bool
    {
        return in_array($this, [self::SENT, self::SOLD]);
    }

    /**
     * Check if item is in final state
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::SOLD, self::RETURNED, self::CANCELLED]);
    }
}
