<?php

namespace App\Modules\Customers\Enums;

enum CustomerType: string
{
    case RETAIL = 'retail';
    case DEALER = 'dealer';

    /**
     * Get all values
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get labels for dropdown
     */
    public static function labels(): array
    {
        return [
            self::RETAIL->value => 'Retail Customer',
            self::DEALER->value => 'Dealer (Wholesaler - Activates Pricing)',
        ];
    }

    /**
     * Get label
     */
    public function label(): string
    {
        return match($this) {
            self::RETAIL => 'Retail Customer',
            self::DEALER => 'Dealer (Wholesaler)',
        };
    }

    /**
     * Check if dealer pricing should activate
     */
    public function activatesDealerPricing(): bool
    {
        return $this === self::DEALER;
    }
}
