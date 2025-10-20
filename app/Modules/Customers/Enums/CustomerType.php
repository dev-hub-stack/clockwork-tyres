<?php

namespace App\Modules\Customers\Enums;

enum CustomerType: string
{
    case RETAIL = 'retail';
    case DEALER = 'dealer';
    case WHOLESALE = 'wholesale';
    case CORPORATE = 'corporate';

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
            self::DEALER->value => 'Dealer (Activates Pricing)',
            self::WHOLESALE->value => 'Wholesale',
            self::CORPORATE->value => 'Corporate',
        ];
    }

    /**
     * Get label
     */
    public function label(): string
    {
        return match($this) {
            self::RETAIL => 'Retail Customer',
            self::DEALER => 'Dealer',
            self::WHOLESALE => 'Wholesale',
            self::CORPORATE => 'Corporate',
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
