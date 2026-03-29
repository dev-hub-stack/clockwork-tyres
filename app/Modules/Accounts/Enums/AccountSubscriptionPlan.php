<?php

namespace App\Modules\Accounts\Enums;

enum AccountSubscriptionPlan: string
{
    case Basic = 'basic';
    case Premium = 'premium';

    public function label(): string
    {
        return match ($this) {
            self::Basic => 'Basic',
            self::Premium => 'Premium',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
