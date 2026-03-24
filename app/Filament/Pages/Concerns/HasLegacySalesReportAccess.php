<?php

namespace App\Filament\Pages\Concerns;

trait HasLegacySalesReportAccess
{
    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return ($user?->can('view_reports') ?? false)
            && ($user?->can('view_sales_reports') ?? false);
    }
}