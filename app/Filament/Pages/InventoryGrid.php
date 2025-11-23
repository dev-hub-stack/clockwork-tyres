<?php

namespace App\Filament\Pages;

use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\ProductInventory;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductVariant;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use UnitEnum;
use BackedEnum;

class InventoryGrid extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'Inventory Grid';

    protected static UnitEnum|string|null $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return auth()->user()->can('view_inventory');
    }

    protected string $view = 'filament.pages.inventory-grid';

    public $products_data = [];
    public $warehouses = [];

    public function mount(): void
    {
        $this->loadInventoryData();
    }

    protected function loadInventoryData(): void
    {
        // Get all active warehouses
        $this->warehouses = Warehouse::where('status', 1)
            ->orderBy('code')
            ->get();

        // Get all products with their variants and inventory
        // Using the EXACT same structure as old Reporting system
        $products = Product::with([
            'brand',
            'model',
            'finish',
            'variants' => function ($query) {
                $query->with([
                    'inventories.warehouse',
                    'consignmentItems' => function($q) {
                        $q->whereHas('consignment', function($query) {
                            $query->whereIn('status', ['sent', 'delivered', 'partially_sold']);
                        });
                    },
                    'consignmentItems.consignment.customer'
                ]);
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
                    'product_full_name' => $product->brand->name . ' ' . $product->model->name . ' ' . $product->finish->name,
                    'brand' => $product->brand->name ?? '',
                    'model' => $product->model->name ?? '',
                    'finish' => $product->finish->name ?? '',
                    'size' => $variant->size ?? '',
                    'rim_width' => $variant->rim_width ?? '',
                    'rim_diameter' => $variant->rim_diameter ?? '',
                    'bolt_pattern' => $variant->bolt_pattern ?? '',
                    'offset' => $variant->offset ?? '',
                    'hub_bore' => $variant->hub_bore ?? '',
                    'inventory' => []
                ];

                // Add inventory data for each warehouse (matching old system structure)
                foreach ($variant->inventories as $inventory) {
                    $row['inventory'][] = [
                        'warehouse_id' => $inventory->warehouse_id,
                        'quantity' => $inventory->quantity ?? 0,
                        'eta' => $inventory->eta ?? '',
                        'eta_qty' => $inventory->eta_qty ?? 0,
                    ];
                }

                // Calculate total incoming stock (sum of all ETA quantities across warehouses)
                $row['incoming_stock'] = $variant->inventories->sum('eta_qty') ?? 0;

                // Calculate total consignment stock (sent - sold - returned for active consignments)
                $consignmentQty = 0;
                foreach ($variant->consignmentItems as $item) {
                    $consignmentQty += ($item->quantity_sent - $item->quantity_sold - $item->quantity_returned);
                }
                $row['consignment_stock'] = $consignmentQty;

                $this->products_data[] = $row;
            }
        }
    }

    public function getProductsDataProperty()
    {
        return $this->products_data;
    }

    public function getWarehousesProperty()
    {
        return $this->warehouses;
    }
}
