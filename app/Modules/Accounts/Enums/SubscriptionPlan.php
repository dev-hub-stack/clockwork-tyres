<?php

namespace App\Modules\Accounts\Enums;

enum SubscriptionPlan: string
{
    case BASIC = 'basic';
    case PREMIUM = 'premium';

    public function label(): string
    {
        return match ($this) {
            self::BASIC => 'Basic',
            self::PREMIUM => 'Premium',
        };
    }
}
