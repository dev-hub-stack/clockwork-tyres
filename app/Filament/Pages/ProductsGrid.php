<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use BackedEnum;
use UnitEnum;

class ProductsGrid extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-table-cells';

    protected static string|UnitEnum|null $navigationGroup = 'Products';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Products Grid';

    protected static ?string $title = 'Products Management Grid';

    protected static ?string $slug = 'products-grid';

    protected string $view = 'filament.pages.products-grid';

    public function mount(): void
    {
        // Load any initial data if needed
    }
    
    /**
     * Get products data for grid
     */
    public function getProductsData()
    {
        // This will be called by the blade view to pass data to JavaScript
        // For now, return empty array - data is loaded via AJAX in the grid
        return [];
    }
}
