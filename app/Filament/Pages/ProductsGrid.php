<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use BackedEnum;
use UnitEnum;

class ProductsGrid extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-table-cells';
    
    protected string $view = 'filament.pages.products-grid-redirect';
    
    protected static UnitEnum|string|null $navigationGroup = 'Products';
    
    protected static ?int $navigationSort = 10;
    
    protected static ?string $title = 'Products Grid';
    
    protected static ?string $navigationLabel = 'Products Grid';
}
