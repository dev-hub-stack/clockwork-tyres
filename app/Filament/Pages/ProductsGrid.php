<?php

namespace App\Filament\Pages;

use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductVariant;
use Filament\Pages\Page;
use BackedEnum;
use Illuminate\Support\Facades\Cache;
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
        // Get all active warehouses (cache this as it doesn't change often)
        $this->warehouses = Cache::remember('active_warehouses', 3600, function () {
            return Warehouse::where('status', 1)
                ->orderBy('code')
                ->get();
        });

        // Load products with proper eager loading and pagination
        $query = Product::with([
            'brand',
            'model',
            'variants' => function ($query) {
                $query->with([
                    'finishRelation', // Eager load finish relationship to avoid N+1
                    'inventories.warehouse'
                ]);
            }
        ])
        ->whereHas('variants');

        // Apply pagination - load only 1000 records at a time to prevent timeout
        $products = $query->limit(1000)->get();

        $this->products_data = [];

        foreach ($products as $product) {
            foreach ($product->variants as $variant) {
                // Pre-load finish data to avoid individual queries
                $finishName = '';
                if ($variant->finishRelation) {
                    $finishName = $variant->finishRelation->finish;
                } elseif (isset($variant->finish)) {
                    $finishName = $variant->finish;
                }

                $row = [
                    'id' => $variant->id,
                    'product_id' => $product->id,
                    'sku' => $variant->sku ?? '',
                    'product_full_name' => trim(($product->brand?->name ?? '') . ' ' . ($product->model?->name ?? '') . ' ' . $finishName),
                    'brand' => $product->brand?->name ?? '',
                    'model' => $product->model?->name ?? '',
                    'finish' => $finishName,
                    'construction' => $product->construction ?? '',
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
                    'uae_retail_price' => $variant->uae_retail_price ?? 0,
                    'sale_price' => $variant->sale_price ?? 0,
                    'available_on_wholesale' => (bool) ($product->available_on_wholesale ?? true),
                    'track_inventory' => (bool) ($product->track_inventory ?? false),
                    'images' => is_string($product->images) 
                        ? implode(', ', json_decode($product->images, true) ?: []) 
                        : implode(', ', $product->images?->toArray() ?? []),
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
