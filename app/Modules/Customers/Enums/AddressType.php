<?php

namespace App\Modules\Customers\Enums;

enum AddressType: int
{
    case BILLING = 1;
    case SHIPPING = 2;

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
            self::BILLING->value => 'Billing Address',
            self::SHIPPING->value => 'Shipping Address',
        ];
    }

    /**
     * Get label
     */
    public function label(): string
    {
        return match($this) {
            self::BILLING => 'Billing Address',
            self::SHIPPING => 'Shipping Address',
        };
    }
}
