<?php

namespace App\Modules\Accounts\Enums;

enum AccountType: string
{
    case Retailer = 'retailer';
    case Supplier = 'supplier';
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::Retailer => 'Retailer',
            self::Supplier => 'Supplier',
            self::Both => 'Retailer & Supplier',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
