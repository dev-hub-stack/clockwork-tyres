<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasLegacySalesReportAccess;
use Filament\Pages\Page;
use BackedEnum;
use UnitEnum;

class CustomerAnalytics extends Page
{
    use HasLegacySalesReportAccess;

    protected string $view = 'filament.pages.customer-analytics';
    
    protected static ?string $navigationLabel = 'Customer Analytics';
    
    protected static ?string $title = 'Customer Analytics';
    
    protected static string | UnitEnum | null $navigationGroup = 'Reports';
    
    protected static ?int $navigationSort = 2;

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Widgets\CustomerAnalyticsTable::class,
        ];
    }
}
