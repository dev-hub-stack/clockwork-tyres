<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Accounts\Support\CurrentAccountResolver;
use App\Modules\Products\Models\ProductVariant;
use App\Modules\Products\Models\TyreAccountOffer;
use App\Modules\Products\Support\TyreImageStorage;
use App\Modules\Consignments\Models\ConsignmentItem;
use App\Modules\Inventory\Models\TyreDamagedInventory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryApiController extends Controller
{
    private function currentAccountId(Request $request): ?int
    {
        if (! auth()->check()) {
            return null;
        }

        return app(CurrentAccountResolver::class)
            ->resolve($request, auth()->user())
            ->currentAccount?->id;
    }

    /**
     * Get consignments for a specific product variant (by variant ID)
     * Returns list of customers with consignment quantities and dates
     */
    public function getConsignmentsByVariant($variantId, Request $request)
    {
        try {
            $currentAccountId = $this->currentAccountId($request);
            $variant = ProductVariant::findOrFail($variantId);
            
            $consignments = ConsignmentItem::where('product_variant_id', $variant->id)
                ->whereHas('consignment', function($q) {
                    $q->whereIn('status', ['sent', 'delivered', 'partially_sold', 'partially_returned']);
                })
                ->when($currentAccountId, function ($query) use ($currentAccountId) {
                    $query->whereHas('consignment.customer', function ($customerQuery) use ($currentAccountId) {
                        $customerQuery->where('account_id', $currentAccountId);
                    });
                })
                ->with('consignment.customer')
                ->get()
                ->map(function($item) {
                    $availableQty = $item->quantity_sent - $item->quantity_sold - $item->quantity_returned;
                    
                    // Only include items with available quantity > 0
                    if ($availableQty > 0) {
                        return [
                            'customer' => $item->consignment->customer->business_name ?? $item->consignment->customer->name,
                            'customer_id' => $item->consignment->customer_id,
                            'quantity_sent' => $item->quantity_sent,
                            'quantity_sold' => $item->quantity_sold,
                            'quantity_returned' => $item->quantity_returned,
                            'available_qty' => $availableQty,
                            'date_consigned' => $item->consignment->issue_date->format('d-m-Y'),
                            'consignment_id' => $item->consignment_id,
                            'consignment_number' => $item->consignment->consignment_number,
                        ];
                    }
                    return null;
                })
                ->filter() // Remove null values
                ->values(); // Re-index array
                
            return response()->json($consignments);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to load consignment data',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get incoming stock for a specific product variant (by variant ID)
     * Returns list of incoming stock with ETA dates and quantities per warehouse
     */
    public function getIncomingStockByVariant($variantId, Request $request)
    {
        try {
            $currentAccountId = $this->currentAccountId($request);
            $variant = ProductVariant::with(['inventories.warehouse'])->findOrFail($variantId);
            
            $incomingStock = $variant->inventories()
                ->with('warehouse')
                ->when($currentAccountId, function ($query) use ($currentAccountId) {
                    $query->whereHas('warehouse', function ($warehouseQuery) use ($currentAccountId) {
                        $warehouseQuery->where('account_id', $currentAccountId);
                    });
                })
                ->where(function($q) {
                    $q->where('eta_qty', '>', 0)
                      ->orWhereNotNull('eta');
                })
                ->get()
                ->map(function($inventory) {
                    // Only include if there's actually incoming quantity
                    if ($inventory->eta_qty > 0) {
                        return [
                            'warehouse_id' => $inventory->warehouse_id,
                            'warehouse_code' => $inventory->warehouse->code ?? 'N/A',
                            'warehouse_name' => $inventory->warehouse->warehouse_name ?? 'N/A',
                            'eta' => $inventory->eta ? date('d-m-Y', strtotime($inventory->eta)) : null,
                            'quantity' => $inventory->eta_qty ?? 0,
                            'notes' => $inventory->notes ?? '',
                        ];
                    }
                    return null;
                })
                ->filter() // Remove null values
                ->values(); // Re-index array
                
            return response()->json($incomingStock);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to load incoming stock data',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get consignments for a specific product variant (by SKU)
     * Returns list of customers with consignment quantities and dates
     */
    public function getConsignmentsBySku($sku, Request $request)
    {
        try {
            $currentAccountId = $this->currentAccountId($request);
            $variant = ProductVariant::where('sku', $sku)->firstOrFail();
            
            $consignments = ConsignmentItem::where('product_variant_id', $variant->id)
                ->whereHas('consignment', function($q) {
                    $q->whereIn('status', ['sent', 'delivered', 'partially_sold', 'partially_returned']);
                })
                ->when($currentAccountId, function ($query) use ($currentAccountId) {
                    $query->whereHas('consignment.customer', function ($customerQuery) use ($currentAccountId) {
                        $customerQuery->where('account_id', $currentAccountId);
                    });
                })
                ->with('consignment.customer')
                ->get()
                ->map(function($item) {
                    $availableQty = $item->quantity_sent - $item->quantity_sold - $item->quantity_returned;
                    
                    // Only include items with available quantity > 0
                    if ($availableQty > 0) {
                        return [
                            'customer' => $item->consignment->customer->business_name ?? $item->consignment->customer->name ?? 'Unknown Customer',
                            'customer_id' => $item->consignment->customer_id,
                            'consignment_id' => $item->consignment_id,
                            'quantity_sent' => $item->quantity_sent,
                            'quantity_sold' => $item->quantity_sold,
                            'quantity_returned' => $item->quantity_returned,
                            'available_qty' => $availableQty,
                            'date_consigned' => $item->consignment->issue_date ? $item->consignment->issue_date->format('d-m-Y') : 'N/A',
                            'consignment_number' => $item->consignment->consignment_number ?? 'N/A',
                        ];
                    }
                    return null;
                })
                ->filter() // Remove null values
                ->values(); // Re-index array
                
            return response()->json($consignments);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to load consignment data',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get incoming stock for a specific product variant (by SKU)
     * Returns list of incoming stock with ETA dates and quantities per warehouse
     */
    public function getIncomingStockBySku($sku, Request $request)
    {
        try {
            $currentAccountId = $this->currentAccountId($request);
            $variant = ProductVariant::where('sku', $sku)->firstOrFail();
            
            // Get warehouse filter if provided
            $warehouseCode = $request->query('warehouse');
            
            $query = $variant->inventories()->with('warehouse');

            if ($currentAccountId) {
                $query->whereHas('warehouse', function ($warehouseQuery) use ($currentAccountId) {
                    $warehouseQuery->where('account_id', $currentAccountId);
                });
            }
            
            // Filter by warehouse if specified
            if ($warehouseCode) {
                $query->whereHas('warehouse', function($q) use ($warehouseCode) {
                    $q->where('code', $warehouseCode);
                });
            }
            
            $incomingStock = $query->get()
                ->map(function($inventory) {
                    // Only include if there's actually incoming quantity
                    if (isset($inventory->eta_qty) && $inventory->eta_qty > 0) {
                        return [
                            'warehouse' => $inventory->warehouse->warehouse_name ?? 'Unknown',
                            'warehouse_code' => $inventory->warehouse->code ?? 'N/A',
                            'eta' => $inventory->eta ?? null,
                            'quantity' => $inventory->eta_qty ?? 0,
                            'notes' => $inventory->notes ?? null,
                        ];
                    }
                    return null;
                })
                ->filter() // Remove null values
                ->values(); // Re-index array
                
            return response()->json($incomingStock);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to load incoming stock data',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Return all inventory grid data as JSON.
     * Called via AJAX from the inventory grid page — avoids inlining 51k rows
     * into the Livewire snapshot.
     */
    public function gridData(Request $request)
    {
        $currentAccountId = $this->currentAccountId($request);

        if (! $currentAccountId) {
            return response()->json([]);
        }

        // 1. All variants with product metadata (1 query)
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
            ->where('pv.track_inventory', true)
            ->orderBy('b.name')
            ->orderBy('m.name')
            ->orderBy('pv.sku')
            ->get()
            ->keyBy('id');

        if ($variants->isEmpty()) {
            return response()->json([]);
        }

        // 2. All inventory rows (1 query via JOIN, no IN clause)
        $inventoryRows = DB::table('product_inventories as pi')
            ->join('warehouses as w', 'w.id', '=', 'pi.warehouse_id')
            ->join('product_variants as pv2', 'pv2.id', '=', 'pi.product_variant_id')
            ->whereNotNull('pv2.sku')
            ->where('w.account_id', $currentAccountId)
            ->select('pi.product_variant_id', 'pi.warehouse_id', 'pi.quantity', 'pi.eta', 'pi.eta_qty')
            ->get()
            ->groupBy('product_variant_id');

        // 3. Consignment aggregate (1 query)
        $consignmentStock = DB::table('consignment_items as ci')
            ->join('consignments as c', 'c.id', '=', 'ci.consignment_id')
            ->join('customers as cu', 'cu.id', '=', 'c.customer_id')
            ->join('product_variants as pv3', 'pv3.id', '=', 'ci.product_variant_id')
            ->whereIn('c.status', ['sent', 'delivered', 'partially_sold', 'partially_returned'])
            ->whereNull('ci.deleted_at')
            ->whereNull('c.deleted_at')
            ->whereNotNull('pv3.sku')
            ->where('cu.account_id', $currentAccountId)
            ->select(
                'ci.product_variant_id',
                DB::raw('SUM(ci.quantity_sent - ci.quantity_sold - ci.quantity_returned) as consignment_qty')
            )
            ->groupBy('ci.product_variant_id')
            ->pluck('consignment_qty', 'product_variant_id');

        // 3.1 Damaged aggregate (1 query)
        $damagedStock = DB::table('damaged_inventories as di')
            ->join('warehouses as w2', 'w2.id', '=', 'di.warehouse_id')
            ->join('product_variants as pv4', 'pv4.id', '=', 'di.product_variant_id')
            ->whereNotNull('pv4.sku')
            ->where('w2.account_id', $currentAccountId)
            ->select(
                'di.product_variant_id',
                DB::raw('SUM(di.quantity) as damaged_qty')
            )
            ->groupBy('di.product_variant_id')
            ->pluck('damaged_qty', 'product_variant_id');

        // 4. Assemble
        $rows = [];
        foreach ($variants as $variant) {
            $invRows = $inventoryRows->get($variant->id, collect());
            $rows[] = [
                'id'                => $variant->id,
                'product_id'        => $variant->product_id,
                'sku'               => $variant->sku ?? '',
                'product_full_name' => trim(($variant->brand ?? 'N/A') . ' ' . ($variant->model ?? 'N/A') . ' ' . ($variant->finish ?? 'N/A')),
                'brand'             => $variant->brand ?? '',
                'model'             => $variant->model ?? '',
                'finish'            => $variant->finish ?? '',
                'size'              => $variant->size ?? '',
                'rim_width'         => $variant->rim_width ?? '',
                'rim_diameter'      => $variant->rim_diameter ?? '',
                'bolt_pattern'      => $variant->bolt_pattern ?? '',
                'offset'            => $variant->offset ?? '',
                'hub_bore'          => $variant->hub_bore ?? '',
                'inventory'         => $invRows->map(fn($i) => [
                    'warehouse_id' => $i->warehouse_id,
                    'quantity'     => $i->quantity ?? 0,
                    'eta'          => $i->eta ?? '',
                    'eta_qty'      => $i->eta_qty ?? 0,
                ])->values()->toArray(),
                'incoming_stock'    => $invRows->sum('eta_qty'),
                'consignment_stock' => (int) ($consignmentStock[$variant->id] ?? 0),
                'damaged_stock'     => (int) ($damagedStock[$variant->id] ?? 0),
            ];
        }

        return response()->json($rows);
    }

    /**
     * Return tyre inventory grid data as JSON.
     */
    public function tyreGridData(Request $request)
    {
        $currentAccountId = $this->currentAccountId($request);

        if (! $currentAccountId) {
            return response()->json([]);
        }

        $offers = DB::table('tyre_account_offers as tao')
            ->join('tyre_catalog_groups as tcg', 'tcg.id', '=', 'tao.tyre_catalog_group_id')
            ->select(
                'tao.id',
                'tao.source_sku',
                'tao.retail_price',
                'tao.wholesale_price_lvl1',
                'tao.inventory_status',
                'tao.product_image_1',
                'tcg.brand_name as brand',
                'tcg.model_name as model',
                'tcg.full_size',
                'tcg.width',
                'tcg.height',
                'tcg.rim_size',
                'tcg.load_index',
                'tcg.speed_rating',
                'tcg.dot_year',
                'tcg.tyre_type',
                'tcg.runflat',
                'tcg.rfid'
            )
            ->where('tao.account_id', $currentAccountId)
            ->orderBy('tcg.brand_name')
            ->orderBy('tcg.model_name')
            ->orderBy('tao.source_sku')
            ->get()
            ->keyBy('id');

        if ($offers->isEmpty()) {
            return response()->json([]);
        }

        $inventoryRows = DB::table('tyre_offer_inventories as toi')
            ->join('warehouses as w', 'w.id', '=', 'toi.warehouse_id')
            ->where('toi.account_id', $currentAccountId)
            ->where('w.account_id', $currentAccountId)
            ->select('toi.tyre_account_offer_id', 'toi.warehouse_id', 'toi.quantity', 'toi.eta', 'toi.eta_qty')
            ->get()
            ->groupBy('tyre_account_offer_id');

        $consignmentStock = DB::table('consignment_items as ci')
            ->join('consignments as c', 'c.id', '=', 'ci.consignment_id')
            ->join('customers as cu', 'cu.id', '=', 'c.customer_id')
            ->whereIn('c.status', ['sent', 'delivered', 'partially_sold', 'partially_returned'])
            ->whereNull('ci.deleted_at')
            ->whereNull('c.deleted_at')
            ->whereNotNull('ci.tyre_account_offer_id')
            ->where('cu.account_id', $currentAccountId)
            ->select(
                'ci.tyre_account_offer_id',
                DB::raw('SUM(ci.quantity_sent - ci.quantity_sold - ci.quantity_returned) as consignment_qty')
            )
            ->groupBy('ci.tyre_account_offer_id')
            ->pluck('consignment_qty', 'ci.tyre_account_offer_id');

        $damagedStock = DB::table('tyre_damaged_inventories as tdi')
            ->join('warehouses as w2', 'w2.id', '=', 'tdi.warehouse_id')
            ->where('w2.account_id', $currentAccountId)
            ->select(
                'tdi.tyre_account_offer_id',
                DB::raw('SUM(tdi.quantity) as damaged_qty')
            )
            ->groupBy('tdi.tyre_account_offer_id')
            ->pluck('damaged_qty', 'tdi.tyre_account_offer_id');

        $rows = [];
        foreach ($offers as $offer) {
            $offerInventories = $inventoryRows->get($offer->id, collect());

            $rows[] = [
                'id' => $offer->id,
                'sku' => $offer->source_sku ?? '',
                'brand' => $offer->brand ?? '',
                'model' => $offer->model ?? '',
                'full_size' => $offer->full_size ?? '',
                'width' => $offer->width ?? '',
                'height' => $offer->height ?? '',
                'rim_size' => $offer->rim_size ?? '',
                'load_index' => $offer->load_index ?? '',
                'speed_rating' => $offer->speed_rating ?? '',
                'dot_year' => $offer->dot_year ?? '',
                'tyre_type' => $offer->tyre_type ?? '',
                'runflat' => $offer->runflat ? 'Yes' : 'No',
                'rfid' => $offer->rfid ? 'Yes' : 'No',
                'inventory_status' => $offer->inventory_status ?? '',
                'retail_price' => $offer->retail_price,
                'wholesale_price_lvl1' => $offer->wholesale_price_lvl1,
                'image' => TyreImageStorage::url($offer->product_image_1),
                'inventory' => $offerInventories->map(fn ($inventory) => [
                    'warehouse_id' => $inventory->warehouse_id,
                    'quantity' => $inventory->quantity ?? 0,
                    'eta' => $inventory->eta ?? '',
                    'eta_qty' => $inventory->eta_qty ?? 0,
                ])->values()->toArray(),
                'incoming_stock' => $offerInventories->sum('eta_qty'),
                'current_stock' => $offerInventories->sum('quantity'),
                'consignment_stock' => (int) ($consignmentStock[$offer->id] ?? 0),
                'damaged_stock' => (int) ($damagedStock[$offer->id] ?? 0),
            ];
        }

        return response()->json($rows);
    }

    public function getTyreConsignmentsBySku($sku, Request $request)
    {
        try {
            $currentAccountId = $this->currentAccountId($request);
            $offer = TyreAccountOffer::query()
                ->when($currentAccountId, fn ($query) => $query->where('account_id', $currentAccountId))
                ->where('source_sku', $sku)
                ->firstOrFail();

            $consignments = ConsignmentItem::where('tyre_account_offer_id', $offer->id)
                ->whereHas('consignment', function ($q) {
                    $q->whereIn('status', ['sent', 'delivered', 'partially_sold', 'partially_returned']);
                })
                ->when($currentAccountId, function ($query) use ($currentAccountId) {
                    $query->whereHas('consignment.customer', function ($customerQuery) use ($currentAccountId) {
                        $customerQuery->where('account_id', $currentAccountId);
                    });
                })
                ->with('consignment.customer')
                ->get()
                ->map(function ($item) {
                    $availableQty = $item->quantity_sent - $item->quantity_sold - $item->quantity_returned;

                    if ($availableQty > 0) {
                        return [
                            'customer' => $item->consignment->customer->business_name ?? $item->consignment->customer->name ?? 'Unknown Customer',
                            'customer_id' => $item->consignment->customer_id,
                            'consignment_id' => $item->consignment_id,
                            'quantity_sent' => $item->quantity_sent,
                            'quantity_sold' => $item->quantity_sold,
                            'quantity_returned' => $item->quantity_returned,
                            'available_qty' => $availableQty,
                            'date_consigned' => $item->consignment->issue_date ? $item->consignment->issue_date->format('d-m-Y') : 'N/A',
                            'consignment_number' => $item->consignment->consignment_number ?? 'N/A',
                        ];
                    }

                    return null;
                })
                ->filter()
                ->values();

            return response()->json($consignments);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to load tyre consignment data',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function getTyreIncomingStockBySku($sku, Request $request)
    {
        try {
            $currentAccountId = $this->currentAccountId($request);
            $offer = TyreAccountOffer::query()
                ->when($currentAccountId, fn ($query) => $query->where('account_id', $currentAccountId))
                ->where('source_sku', $sku)
                ->firstOrFail();

            $warehouseCode = $request->query('warehouse');

            $query = $offer->inventories()->with('warehouse');

            if ($currentAccountId) {
                $query->whereHas('warehouse', function ($warehouseQuery) use ($currentAccountId) {
                    $warehouseQuery->where('account_id', $currentAccountId);
                });
            }

            if ($warehouseCode) {
                $query->whereHas('warehouse', function ($warehouseQuery) use ($warehouseCode) {
                    $warehouseQuery->where('code', $warehouseCode);
                });
            }

            $incomingStock = $query->get()
                ->map(function ($inventory) {
                    if ((int) ($inventory->eta_qty ?? 0) > 0) {
                        return [
                            'warehouse' => $inventory->warehouse->warehouse_name ?? 'Unknown',
                            'warehouse_code' => $inventory->warehouse->code ?? 'N/A',
                            'eta' => $inventory->eta ?? null,
                            'quantity' => $inventory->eta_qty ?? 0,
                            'notes' => $inventory->notes ?? null,
                        ];
                    }

                    return null;
                })
                ->filter()
                ->values();

            return response()->json($incomingStock);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to load incoming tyre stock data',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function getTyreDamagedStockBySku($sku, Request $request)
    {
        try {
            $currentAccountId = $this->currentAccountId($request);
            $offer = TyreAccountOffer::query()
                ->when($currentAccountId, fn ($query) => $query->where('account_id', $currentAccountId))
                ->where('source_sku', $sku)
                ->firstOrFail();

            $damagedItems = TyreDamagedInventory::query()
                ->join('warehouses as w', 'w.id', '=', 'tyre_damaged_inventories.warehouse_id')
                ->where('tyre_damaged_inventories.tyre_account_offer_id', $offer->id)
                ->when($currentAccountId, fn ($query) => $query->where('w.account_id', $currentAccountId))
                ->select(
                    'w.warehouse_name as warehouse',
                    'w.code as warehouse_code',
                    'tyre_damaged_inventories.quantity',
                    'tyre_damaged_inventories.condition',
                    'tyre_damaged_inventories.notes',
                    'tyre_damaged_inventories.created_at'
                )
                ->orderByDesc('tyre_damaged_inventories.created_at')
                ->get()
                ->map(function ($item) {
                    return [
                        'warehouse' => $item->warehouse,
                        'warehouse_code' => $item->warehouse_code,
                        'quantity' => $item->quantity,
                        'condition' => ucfirst($item->condition),
                        'notes' => $item->notes ?? '-',
                        'date_recorded' => date('d-m-Y', strtotime($item->created_at)),
                    ];
                });

            return response()->json($damagedItems);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to load damaged tyre stock data',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get damaged stock details for a specific SKU
     */
    public function getDamagedStockBySku($sku, Request $request)
    {
        try {
            $currentAccountId = $this->currentAccountId($request);
            $variant = ProductVariant::where('sku', $sku)->firstOrFail();
            
            $damagedItems = DB::table('damaged_inventories as di')
                ->join('warehouses as w', 'w.id', '=', 'di.warehouse_id')
                ->where('di.product_variant_id', $variant->id)
                ->when($currentAccountId, fn ($query) => $query->where('w.account_id', $currentAccountId))
                ->select(
                    'w.warehouse_name as warehouse',
                    'w.code as warehouse_code',
                    'di.quantity',
                    'di.condition',
                    'di.notes',
                    'di.created_at'
                )
                ->orderBy('di.created_at', 'desc')
                ->get()
                ->map(function($item) {
                    return [
                        'warehouse' => $item->warehouse,
                        'warehouse_code' => $item->warehouse_code,
                        'quantity' => $item->quantity,
                        'condition' => ucfirst($item->condition),
                        'notes' => $item->notes ?? '-',
                        'date_recorded' => date('d-m-Y', strtotime($item->created_at)),
                    ];
                });
                
            return response()->json($damagedItems);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to load damaged stock data',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
