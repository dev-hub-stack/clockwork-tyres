<?php

namespace App\Filament\Pages;

use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductVariant;
use Filament\Pages\Page;
use BackedEnum;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
        $this->warehouses = Cache::remember('active_warehouses', 3600, function () {
            return Warehouse::where('status', 1)->orderBy('code')->get();
        });

        // Single efficient join query — no limit, no N+1
        $rows = DB::table('product_variants as pv')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->leftJoin('brands as b', 'b.id', '=', 'p.brand_id')
            ->leftJoin('models as m', 'm.id', '=', 'p.model_id')
            ->leftJoin('finishes as f', 'f.id', '=', 'p.finish_id')
            ->select(
                'pv.id',
                'pv.product_id',
                'pv.sku',
                'pv.size',
                'pv.rim_width',
                'pv.rim_diameter',
                'pv.bolt_pattern',
                'pv.offset',
                'pv.hub_bore',
                'pv.backspacing',
                'pv.max_wheel_load',
                'pv.weight',
                'pv.lipsize',
                'pv.uae_retail_price',
                'pv.sale_price',
                'pv.finish',
                'b.name as brand',
                'm.name as model',
                'f.finish as finish_name',
                'p.name as product_name',
                'p.construction',
                'p.images',
                'p.available_on_wholesale',
                'pv.track_inventory'
            )
            ->orderBy('b.name')
            ->orderBy('m.name')
            ->orderBy('pv.sku')
            ->get();

        $this->products_data = [];

        foreach ($rows as $row) {
            $finish = $row->finish_name ?: ($row->finish ?? '');
            $images = '';

            if (is_string($row->images) && $row->images !== '') {
                $decodedImages = json_decode($row->images, true);

                if (is_array($decodedImages)) {
                    $images = implode(', ', array_values(array_filter($decodedImages, fn ($image) => is_scalar($image) && $image !== '')));
                } elseif (is_string($decodedImages) && $decodedImages !== '') {
                    $images = $decodedImages;
                } else {
                    $images = $row->images;
                }
            }

            $this->products_data[] = [
                'id'                  => $row->id,
                'product_id'          => $row->product_id,
                'sku'                 => $row->sku ?? '',
                'product_full_name'   => trim(($row->brand ?? '') . ' ' . ($row->model ?? '') . ' ' . $finish),
                'brand'               => $row->brand ?? '',
                'model'               => $row->model ?? '',
                'finish'              => $finish,
                'construction'        => $row->construction ?? '',
                'rim_width'           => $row->rim_width ?? '',
                'rim_diameter'        => $row->rim_diameter ?? '',
                'size'                => $row->size ?? '',
                'bolt_pattern'        => $row->bolt_pattern ?? '',
                'hub_bore'            => $row->hub_bore ?? '',
                'offset'              => $row->offset ?? '',
                'backspacing'         => $row->backspacing ?? '',
                'max_wheel_load'      => $row->max_wheel_load ?? '',
                'weight'              => $row->weight ?? '',
                'lipsize'             => $row->lipsize ?? '',
                'uae_retail_price'    => $row->uae_retail_price ?? 0,
                'sale_price'          => $row->sale_price ?? 0,
                'available_on_wholesale' => (bool) $row->available_on_wholesale,
                'track_inventory'     => (bool) $row->track_inventory,
                'images'              => $images,
                'inventory'           => [],
            ];
        }
    }
}
