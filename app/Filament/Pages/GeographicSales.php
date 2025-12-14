<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use BackedEnum;
use UnitEnum;

class GeographicSales extends Page
{
    protected string $view = 'filament.pages.geographic-sales';
    
    protected static ?string $navigationLabel = 'Geographic Sales';
    
    protected static ?string $title = 'Geographic Sales';
    
    protected static string | UnitEnum | null $navigationGroup = 'Reports';
    
    protected static ?int $navigationSort = 4;
    
    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Widgets\GeographicSalesTable::class,
        ];
    }
}
