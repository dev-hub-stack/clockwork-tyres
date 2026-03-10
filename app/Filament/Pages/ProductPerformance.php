<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use BackedEnum;
use UnitEnum;

class ProductPerformance extends Page
{
    protected string $view = 'filament.pages.product-performance';
    
    protected static ?string $navigationLabel = 'Product Performance';
    
    protected static ?string $title = 'Product Performance';
    
    protected static string | UnitEnum | null $navigationGroup = 'Reports';
    
    protected static ?int $navigationSort = 3;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->can('view_reports') ?? false;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_reports') ?? false;
    }

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Widgets\BrandPerformanceChart::class,
            \App\Filament\Widgets\ProductPerformanceTable::class,
        ];
    }
    
    public function getHeaderWidgetsColumns(): int | array
    {
        return 1;
    }
}
