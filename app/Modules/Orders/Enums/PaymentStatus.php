<?php

namespace App\Modules\Orders\Enums;

enum PaymentStatus: string
{
    case PENDING = 'pending';
    case PARTIAL = 'partial';
    case PAID = 'paid';
    case REFUNDED = 'refunded';
    case FAILED = 'failed';

    /**
     * Get label for display
     */
    public function label(): string
    {
        return match($this) {
            self::PENDING => 'Pending',
            self::PARTIAL => 'Partially Paid',
            self::PAID => 'Paid',
            self::REFUNDED => 'Refunded',
            self::FAILED => 'Failed',
        };
    }

    /**
     * Get color for badges
     */
    public function color(): string
    {
        return match($this) {
            self::PAID     => 'success',  // green
            self::PARTIAL  => 'warning',  // yellow
            self::PENDING  => 'danger',   // red
            self::FAILED   => 'danger',   // red
            self::REFUNDED => 'gray',
        };
    }

    /**
     * Get icon for display
     */
    public function icon(): string
    {
        return match($this) {
            self::PENDING => 'heroicon-o-clock',
            self::PARTIAL => 'heroicon-o-banknotes',
            self::PAID => 'heroicon-o-check-circle',
            self::REFUNDED => 'heroicon-o-arrow-uturn-left',
            self::FAILED => 'heroicon-o-x-circle',
        };
    }

    /**
     * Check if payment is complete
     */
    public function isPaid(): bool
    {
        return $this === self::PAID;
    }

    /**
     * Check if payment requires action
     */
    public function requiresAction(): bool
    {
        return in_array($this, [self::PENDING, self::FAILED]);
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
