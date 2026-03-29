<?php

namespace App\Modules\Accounts\Enums;

enum AccountType: string
{
    case RETAILER = 'retailer';
    case SUPPLIER = 'supplier';
    case BOTH = 'both';

    public function label(): string
    {
        return match ($this) {
            self::RETAILER => 'Retailer',
            self::SUPPLIER => 'Supplier',
            self::BOTH => 'Retailer & Supplier',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
