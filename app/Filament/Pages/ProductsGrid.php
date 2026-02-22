<?php

namespace App\Filament\Pages;

use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductVariant;
use Filament\Pages\Page;
use BackedEnum;
use UnitEnum;

class ProductsGrid extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-table-cells';

    protected static UnitEnum|string|null $navigationGroup = 'Products';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Products Grid';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_products') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->can('view_products') ?? false;
    }

    protected string $view = 'filament.pages.products-grid';

    public $products_data = [];
    public $warehouses = [];

    public function mount(): void
    {
        $this->loadProductsData();
    }
    
    protected function loadProductsData(): void
    {
        // Get all active warehouses
        $this->warehouses = Warehouse::where('status', 1)
            ->orderBy('code')
            ->get();

        // Get all products with their variants and inventory
        $products = Product::with([
            'brand',
            'model',
            'finish',
            'variants' => function ($query) {
                $query->with(['inventories.warehouse']);
            }
        ])
        ->whereHas('variants')
        ->get();

        $this->products_data = [];

        foreach ($products as $product) {
            foreach ($product->variants as $variant) {
                $row = [
                    'id' => $variant->id,
                    'product_id' => $product->id,
                    'sku' => $variant->sku ?? '',
                    'product_full_name' => trim(($product->brand?->name ?? '') . ' ' . ($product->model?->name ?? '') . ' ' . ($variant->finish ?? '')),
                    'brand' => $product->brand?->name ?? '',
                    'model' => $product->model?->name ?? '',
                    'finish' => $variant->finishRelation->finish ?? $variant->finish ?? '',  // Try relationship first, then column
                    'construction' => $product->construction ?? '',  // From product table
                    'rim_width' => $variant->rim_width ?? '',
                    'rim_diameter' => $variant->rim_diameter ?? '',
                    'size' => $variant->size ?? '',
                    'bolt_pattern' => $variant->bolt_pattern ?? '',
                    'hub_bore' => $variant->hub_bore ?? '',
                    'offset' => $variant->offset ?? '',
                    'backspacing' => $variant->backspacing ?? '',
                    'max_wheel_load' => $variant->max_wheel_load ?? '',
                    'weight' => $variant->weight ?? '',
                    'lipsize' => $variant->lipsize ?? '',
                    // 'us_retail_price' => $variant->us_retail_price ?? 0, // Hidden
                    'uae_retail_price' => $variant->uae_retail_price ?? 0,
                    'sale_price' => $variant->sale_price ?? 0,
                    'images' => is_string($product->images) 
                        ? implode(', ', json_decode($product->images, true) ?: []) 
                        : implode(', ', $product->images?->toArray() ?? []),  // Display image paths
                    'inventory' => []
                ];

                // Add inventory data for each warehouse
                foreach ($variant->inventories as $inventory) {
                    $row['inventory'][] = [
                        'warehouse_id' => $inventory->warehouse_id,
                        'quantity' => $inventory->quantity ?? 0,
                        'eta' => $inventory->eta ?? '',
                        'eta_qty' => $inventory->eta_qty ?? 0,
                    ];
                }

                $this->products_data[] = $row;
            }
        }
    }
}
