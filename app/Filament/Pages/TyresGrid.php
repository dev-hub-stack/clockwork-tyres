<?php

namespace App\Filament\Pages;

use App\Modules\Products\Support\CatalogCategoryRegistry;
use App\Modules\Products\Support\TyreCatalogContract;
use App\Modules\Products\Support\TyreGridLayout;
use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

class TyresGrid extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-table-cells';

    protected static UnitEnum|string|null $navigationGroup = 'Tyres';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Tyres Grid';

    protected static ?string $slug = 'tyre-grid';

    protected string $view = 'filament.pages.tyres-grid';

    public array $tyres_data = [];
    public array $category_definition = [];
    public array $pricing_levels = [];
    public array $launch_notes = [];
    public array $grid_columns = [];
    public array $toolbar_actions = [];

    public function mount(): void
    {
        $this->category_definition = CatalogCategoryRegistry::definition(CatalogCategoryRegistry::TYRES) ?? [];

        $blueprint = TyreCatalogContract::blueprint();
        $this->pricing_levels = $blueprint['pricing_levels'] ?? [];
        $this->launch_notes = $blueprint['launch_notes'] ?? [];
        $this->grid_columns = TyreGridLayout::columns();
        $this->toolbar_actions = TyreGridLayout::toolbarActions();

        $this->loadTyresData();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_products') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->can('view_products') ?? false;
    }

    protected function loadTyresData(): void
    {
        // Seed the grid with George's sample row so the scaffold matches the launch tyre contract.
        $this->tyres_data = $this->buildPlaceholderRows();
    }

    protected function buildPlaceholderRows(): array
    {
        return [
            [
                'sku' => '1234',
                'brand' => 'michelin',
                'model' => 'Pilot Sport 4S',
                'width' => 245,
                'height' => 30,
                'rim_size' => 20,
                'full_size' => '245/35R20',
                'load_index' => 118,
                'speed_rating' => 'S',
                'dot' => '2026',
                'country' => 'Japan',
                'type' => 'Performance',
                'runflat' => 'NO',
                'rfid' => 'YES',
                'sidewall' => 'Black',
                'warranty' => '5 Years',
                'retail_price' => 1000,
                'wholesale_price_lvl1' => 900,
                'wholesale_price_lvl2' => 850,
                'wholesale_price_lvl3' => 700,
                'brand_image' => 'brand_image.png',
                'product_image_1' => 'product_image_1.png',
                'product_image_2' => 'product_image_2.png',
                'product_image_3' => 'product_image_3.png',
            ],
        ];
    }
}
