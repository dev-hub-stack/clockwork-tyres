<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Products\Models\ProductVariant;
use App\Modules\Consignments\Models\ConsignmentItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryApiController extends Controller
{
    /**
     * Get consignments for a specific product variant (by variant ID)
     * Returns list of customers with consignment quantities and dates
     */
    public function getConsignmentsByVariant($variantId)
    {
        try {
            $variant = ProductVariant::findOrFail($variantId);
            
            $consignments = ConsignmentItem::where('product_variant_id', $variant->id)
                ->whereHas('consignment', function($q) {
                    $q->whereIn('status', ['sent', 'delivered', 'partially_sold', 'partially_returned']);
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
    public function getIncomingStockByVariant($variantId)
    {
        try {
            $variant = ProductVariant::with(['inventories.warehouse'])->findOrFail($variantId);
            
            $incomingStock = $variant->inventories()
                ->with('warehouse')
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
    public function getConsignmentsBySku($sku)
    {
        try {
            $variant = ProductVariant::where('sku', $sku)->firstOrFail();
            
            $consignments = ConsignmentItem::where('product_variant_id', $variant->id)
                ->whereHas('consignment', function($q) {
                    $q->whereIn('status', ['sent', 'delivered', 'partially_sold', 'partially_returned']);
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
            $variant = ProductVariant::where('sku', $sku)->firstOrFail();
            
            // Get warehouse filter if provided
            $warehouseCode = $request->query('warehouse');
            
            $query = $variant->inventories()->with('warehouse');
            
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
    public function gridData()
    {
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
            ->join('product_variants as pv2', 'pv2.id', '=', 'pi.product_variant_id')
            ->whereNotNull('pv2.sku')
            ->select('pi.product_variant_id', 'pi.warehouse_id', 'pi.quantity', 'pi.eta', 'pi.eta_qty')
            ->get()
            ->groupBy('product_variant_id');

        // 3. Consignment aggregate (1 query)
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

        // 3.1 Damaged aggregate (1 query)
        $damagedStock = DB::table('damaged_inventories as di')
            ->join('product_variants as pv4', 'pv4.id', '=', 'di.product_variant_id')
            ->whereNotNull('pv4.sku')
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
     * Get damaged stock details for a specific SKU
     */
    public function getDamagedStockBySku($sku)
    {
        try {
            $variant = ProductVariant::where('sku', $sku)->firstOrFail();
            
            $damagedItems = DB::table('damaged_inventories as di')
                ->join('warehouses as w', 'w.id', '=', 'di.warehouse_id')
                ->where('di.product_variant_id', $variant->id)
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
