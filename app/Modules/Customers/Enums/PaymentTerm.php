<?php

namespace App\Modules\Customers\Enums;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

enum PaymentTerm: string
{
    case DAYS_30 = '30_days';
    case DAYS_60 = '60_days';
    case DAYS_90 = '90_days';
    case CASH_ON_DELIVERY = 'cash_on_delivery';

    public function label(): string
    {
        return match ($this) {
            self::DAYS_30 => '30 Days',
            self::DAYS_60 => '60 Days',
            self::DAYS_90 => '90 Days',
            self::CASH_ON_DELIVERY => 'Cash on Delivery',
        };
    }

    public function dueDateFrom(CarbonInterface $issueDate): CarbonImmutable
    {
        $issueDate = CarbonImmutable::instance($issueDate->toDateTimeImmutable())->startOfDay();

        return match ($this) {
            self::DAYS_30 => $issueDate->addDays(30),
            self::DAYS_60 => $issueDate->addDays(60),
            self::DAYS_90 => $issueDate->addDays(90),
            self::CASH_ON_DELIVERY => $issueDate,
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $term): array => [$term->value => $term->label()])
            ->all();
    }

    public static function default(): self
    {
        return self::DAYS_30;
    }
}
