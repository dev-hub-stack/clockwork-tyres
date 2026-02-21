<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use BackedEnum;
use UnitEnum;

class SalesDashboard extends Page
{
    protected string $view = 'filament.pages.sales-dashboard';
    
    protected static ?string $navigationLabel = 'Sales Dashboard';
    
    protected static ?string $title = 'Sales Dashboard';
    
    protected static string | UnitEnum | null $navigationGroup = 'Reports';
    
    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_reports') ?? false;
    }

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Widgets\SalesOverviewStats::class,
            \App\Filament\Widgets\RevenueByMonthChart::class,
            \App\Filament\Widgets\TopProductsChart::class,
            \App\Filament\Widgets\TopCustomersTable::class,
        ];
    }
    
    public function getHeaderWidgetsColumns(): int | array
    {
        return 2;
    }
}
