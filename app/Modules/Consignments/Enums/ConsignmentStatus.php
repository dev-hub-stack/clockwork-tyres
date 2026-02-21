<?php

namespace App\Modules\Consignments\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ConsignmentStatus: string implements HasLabel, HasColor, HasIcon
{
    case DRAFT = 'draft';
    case SENT = 'sent';
    case DELIVERED = 'delivered';
    case PARTIALLY_SOLD = 'partially_sold';
    case PARTIALLY_RETURNED = 'partially_returned';
    case INVOICED_IN_FULL = 'invoiced_in_full';
    case RETURNED = 'returned';
    case CANCELLED = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::SENT => 'Sent',
            self::DELIVERED => 'Delivered',
            self::PARTIALLY_SOLD => 'Partially Sold',
            self::PARTIALLY_RETURNED => 'Partially Returned',
            self::INVOICED_IN_FULL => 'All Items Sold',
            self::RETURNED => 'Returned',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::SENT => 'info',
            self::DELIVERED => 'primary',
            self::PARTIALLY_SOLD => 'warning',
            self::PARTIALLY_RETURNED => 'warning',
            self::INVOICED_IN_FULL => 'success',
            self::RETURNED => 'danger',
            self::CANCELLED => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::DRAFT => 'heroicon-o-document',
            self::SENT => 'heroicon-o-paper-airplane',
            self::DELIVERED => 'heroicon-o-truck',
            self::PARTIALLY_SOLD => 'heroicon-o-clock',
            self::PARTIALLY_RETURNED => 'heroicon-o-arrow-path',
            self::INVOICED_IN_FULL => 'heroicon-o-check-circle',
            self::RETURNED => 'heroicon-o-arrow-uturn-left',
            self::CANCELLED => 'heroicon-o-x-circle',
        };
    }

    /**
     * Get allowed status transitions from current status
     */
    public function getAllowedTransitions(): array
    {
        return match ($this) {
            self::DRAFT => [self::SENT, self::CANCELLED],
            self::SENT => [self::DELIVERED, self::PARTIALLY_SOLD, self::CANCELLED],
            self::DELIVERED => [self::PARTIALLY_SOLD, self::INVOICED_IN_FULL, self::PARTIALLY_RETURNED, self::RETURNED, self::CANCELLED],
            self::PARTIALLY_SOLD => [self::INVOICED_IN_FULL, self::PARTIALLY_RETURNED, self::RETURNED],
            self::PARTIALLY_RETURNED => [self::RETURNED, self::PARTIALLY_SOLD],
            self::INVOICED_IN_FULL => [self::PARTIALLY_RETURNED, self::RETURNED],
            self::RETURNED => [],
            self::CANCELLED => [],
        };
    }

    /**
     * Check if can transition to given status
     */
    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->getAllowedTransitions());
    }

    /**
     * Check if status is final (no more transitions allowed)
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::RETURNED, self::CANCELLED, self::INVOICED_IN_FULL]);
    }

    /**
     * Check if status allows recording sales
     */
    public function canRecordSale(): bool
    {
        return in_array($this, [self::SENT, self::DELIVERED, self::PARTIALLY_SOLD, self::PARTIALLY_RETURNED]);
    }

    /**
     * Check if status allows recording returns
     */
    public function canRecordReturn(): bool
    {
        return in_array($this, [self::SENT, self::DELIVERED, self::PARTIALLY_SOLD, self::PARTIALLY_RETURNED, self::INVOICED_IN_FULL]);
    }

    /**
     * Check if status allows editing
     */
    public function canEdit(): bool
    {
        return $this === self::DRAFT;
    }

    /**
     * Check if status allows cancellation
     */
    public function canCancel(): bool
    {
        return !in_array($this, [self::INVOICED_IN_FULL, self::RETURNED, self::CANCELLED]);
    }
}
