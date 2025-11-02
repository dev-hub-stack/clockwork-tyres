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
                    $q->whereIn('status', ['sent', 'delivered', 'partially_sold']);
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
                            'available_qty' => $availableQty,
                            'date_consigned' => $item->consignment->issue_date->format('d-m-Y'),
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
                            'warehouse_name' => $inventory->warehouse->name ?? 'N/A',
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
}
