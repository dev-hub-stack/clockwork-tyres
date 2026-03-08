<?php

namespace App\Filament\Pages;

use App\Modules\Inventory\Models\Warehouse;
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
        return auth()->user()?->can('view_inventory') ?? false;
    }

    protected string $view = 'filament.pages.inventory-grid';

    public $products_data = [];
    public $warehouses = [];
    public bool $canEditCells = false;

    public function mount(): void
    {
        // Only super_admin can directly edit grid cells
        $this->canEditCells = auth()->user()?->hasRole('super_admin') ?? false;
        $this->loadInventoryData();
    }

    protected function loadInventoryData(): void
    {
        // Get all active warehouses (excluding system warehouses)
        $this->warehouses = Warehouse::where('status', 1)
            ->where('is_system', false)
            ->orderBy('code')
            ->get();

        // ── 1. Single flat JOIN for all variant + product metadata ────────────
        // Replaces: Product::with([brand, model, finish, variants=>with([inventories, consignmentItems...])])
        // Old: hundreds of ORM queries over 51k variants
        // New: 1 query
        $variants = DB::table('product_variants as pv')
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
                'b.name as brand',
                'm.name as model',
                'f.finish as finish'
            )
            ->whereNotNull('pv.sku')
            ->where('p.track_inventory', true)
            ->orderBy('b.name')
            ->orderBy('m.name')
            ->orderBy('pv.sku')
            ->get()
            ->keyBy('id');

        if ($variants->isEmpty()) {
            $this->products_data = [];
            return;
        }

        // ── 2. One query for all inventory rows ───────────────────────────────
        // Use a JOIN to the same filtered variant set instead of a 51k-item IN clause
        $inventoryRows = DB::table('product_inventories as pi')
            ->join('product_variants as pv2', 'pv2.id', '=', 'pi.product_variant_id')
            ->whereNotNull('pv2.sku')
            ->select('pi.product_variant_id', 'pi.warehouse_id', 'pi.quantity', 'pi.eta', 'pi.eta_qty')
            ->get()
            ->groupBy('product_variant_id');

        // ── 3. One aggregate query for consignment stock ──────────────────────
        $consignmentStock = DB::table('consignment_items as ci')
            ->join('consignments as c', 'c.id', '=', 'ci.consignment_id')
            ->join('product_variants as pv3', 'pv3.id', '=', 'ci.product_variant_id')
            ->whereIn('c.status', ['sent', 'delivered', 'partially_sold', 'partially_returned'])
            ->whereNull('ci.deleted_at')
            ->whereNull('c.deleted_at')
            ->whereNotNull('pv3.sku')
            ->select(
                'ci.product_variant_id',
                DB::raw('SUM(ci.quantity_sent - ci.quantity_sold - ci.quantity_returned) as consignment_qty')
            )
            ->groupBy('ci.product_variant_id')
            ->pluck('consignment_qty', 'product_variant_id');

        // ── 3.1 One aggregate query for damaged stock ────────────────────────
        $damagedStock = DB::table('damaged_inventories as di')
            ->join('product_variants as pv4', 'pv4.id', '=', 'di.product_variant_id')
            ->whereNotNull('pv4.sku')
            ->select(
                'di.product_variant_id',
                DB::raw('SUM(di.quantity) as damaged_qty')
            )
            ->groupBy('di.product_variant_id')
            ->pluck('damaged_qty', 'product_variant_id');

        // ── 4. Assemble rows in PHP (no more nested loops with ORM calls) ─────
        $this->products_data = [];

        foreach ($variants as $variant) {
            $invRows   = $inventoryRows->get($variant->id, collect());
            $csnQty    = (int) ($consignmentStock[$variant->id] ?? 0);
            $dmgQty    = (int) ($damagedStock[$variant->id] ?? 0);

            $inventoryArr = $invRows->map(fn($i) => [
                'warehouse_id' => $i->warehouse_id,
                'quantity'     => $i->quantity ?? 0,
                'eta'          => $i->eta ?? '',
                'eta_qty'      => $i->eta_qty ?? 0,
            ])->values()->toArray();

            $this->products_data[] = [
                'id'               => $variant->id,
                'product_id'       => $variant->product_id,
                'sku'              => $variant->sku ?? '',
                'product_full_name'=> trim(($variant->brand ?? 'N/A') . ' ' . ($variant->model ?? 'N/A') . ' ' . ($variant->finish ?? 'N/A')),
                'brand'            => $variant->brand ?? '',
                'model'            => $variant->model ?? '',
                'finish'           => $variant->finish ?? '',
                'size'             => $variant->size ?? '',
                'rim_width'        => $variant->rim_width ?? '',
                'rim_diameter'     => $variant->rim_diameter ?? '',
                'bolt_pattern'     => $variant->bolt_pattern ?? '',
                'offset'           => $variant->offset ?? '',
                'hub_bore'         => $variant->hub_bore ?? '',
                'inventory'        => $inventoryArr,
                'incoming_stock'   => $invRows->sum('eta_qty'),
                'consignment_stock'=> $csnQty,
                'damaged_stock'    => $dmgQty,
            ];
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
