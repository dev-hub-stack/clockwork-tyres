<?php

namespace App\Filament\Pages;

use App\Modules\Products\Support\CatalogCategoryRegistry;
use App\Modules\Products\Support\TyreCatalogContract;
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

    public function mount(): void
    {
        $this->category_definition = CatalogCategoryRegistry::definition(CatalogCategoryRegistry::TYRES) ?? [];

        $blueprint = TyreCatalogContract::blueprint();
        $this->pricing_levels = $blueprint['pricing_levels'] ?? [];
        $this->launch_notes = $blueprint['launch_notes'] ?? [];

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
        // George's sample tyre sheet is still pending, so keep the launch scaffold generic.
        // Do not map wheel-specific fields into this page; this is a separate tyres-only surface.
        $this->tyres_data = $this->buildPlaceholderRows();
    }

    protected function buildPlaceholderRows(): array
    {
        return [
            [
                'sku' => 'TYR-PENDING-001',
                'product_name' => 'Tyre scaffold placeholder',
                'brand' => 'Pending',
                'pattern' => 'Pending sample sheet',
                'size' => 'Pending',
                'load_index' => 'Pending',
                'speed_rating' => 'Pending',
                'retail_price' => null,
                'wholesale_lvl1_price' => null,
                'wholesale_lvl2_price' => null,
                'wholesale_lvl3_price' => null,
                'availability_note' => 'Sample sheet fields pending',
            ],
            [
                'sku' => 'TYR-PENDING-002',
                'product_name' => 'Tyre scaffold placeholder',
                'brand' => 'Pending',
                'pattern' => 'Pending sample sheet',
                'size' => 'Pending',
                'load_index' => 'Pending',
                'speed_rating' => 'Pending',
                'retail_price' => null,
                'wholesale_lvl1_price' => null,
                'wholesale_lvl2_price' => null,
                'wholesale_lvl3_price' => null,
                'availability_note' => 'Sample sheet fields pending',
            ],
            [
                'sku' => 'TYR-PENDING-003',
                'product_name' => 'Tyre scaffold placeholder',
                'brand' => 'Pending',
                'pattern' => 'Pending sample sheet',
                'size' => 'Pending',
                'load_index' => 'Pending',
                'speed_rating' => 'Pending',
                'retail_price' => null,
                'wholesale_lvl1_price' => null,
                'wholesale_lvl2_price' => null,
                'wholesale_lvl3_price' => null,
                'availability_note' => 'Sample sheet fields pending',
            ],
        ];
    }

    public function pricingLevelLabel(string $pricingLevel): string
    {
        return match ($pricingLevel) {
            'wholesale_lvl1' => 'Wholesale L1',
            'wholesale_lvl2' => 'Wholesale L2',
            'wholesale_lvl3' => 'Wholesale L3',
            default => ucfirst(str_replace('_', ' ', $pricingLevel)),
        };
    }
}
