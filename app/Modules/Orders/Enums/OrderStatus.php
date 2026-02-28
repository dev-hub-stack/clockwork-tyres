<?php

namespace App\Modules\Orders\Enums;

enum OrderStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case SHIPPED = 'shipped';
    case DELIVERED = 'delivered';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    /**
     * Get label for display
     */
    public function label(): string
    {
        return match($this) {
            self::PENDING => 'Pending',
            self::PROCESSING => 'Processing',
            self::SHIPPED => 'Shipped',
            self::DELIVERED => 'Delivered',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
        };
    }

    /**
     * Get color for badges
     */
    public function color(): string
    {
        return match($this) {
            self::COMPLETED  => 'success',  // green
            self::DELIVERED  => 'success',  // green
            self::SHIPPED    => 'success',  // green
            self::PROCESSING => 'warning',  // yellow
            self::PENDING    => 'danger',   // red
            self::CANCELLED  => 'danger',   // red
        };
    }

    /**
     * Get icon for display
     */
    public function icon(): string
    {
        return match($this) {
            self::PENDING => 'heroicon-o-clock',
            self::PROCESSING => 'heroicon-o-cog',
            self::SHIPPED => 'heroicon-o-truck',
            self::DELIVERED => 'heroicon-o-check-circle',
            self::COMPLETED => 'heroicon-o-check-badge',
            self::CANCELLED => 'heroicon-o-x-circle',
        };
    }

    /**
     * Check if order can be edited
     */
    public function canEdit(): bool
    {
        return in_array($this, [self::PENDING]);
    }

    /**
     * Check if order can be cancelled
     */
    public function canCancel(): bool
    {
        return in_array($this, [self::PENDING, self::PROCESSING]);
    }

    /**
     * Check if inventory should be allocated
     */
    public function shouldAllocateInventory(): bool
    {
        return $this === self::PROCESSING;
    }

    /**
     * Check if inventory should be released
     */
    public function shouldReleaseInventory(): bool
    {
        return $this === self::CANCELLED;
    }

    /**
     * Get next valid statuses
     */
    public function nextStatuses(): array
    {
        return match($this) {
            self::PENDING => [self::PROCESSING, self::CANCELLED],
            self::PROCESSING => [self::SHIPPED, self::CANCELLED],
            self::SHIPPED => [self::DELIVERED, self::CANCELLED],
            self::DELIVERED => [self::COMPLETED],
            self::COMPLETED => [],
            self::CANCELLED => [],
        };
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
