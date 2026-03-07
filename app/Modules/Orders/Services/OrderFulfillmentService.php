<?php

namespace App\Modules\Orders\Services;

use App\Modules\Inventory\Models\InventoryLog;
use App\Modules\Inventory\Models\ProductInventory;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;
use App\Modules\Orders\Models\OrderItemQuantity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * OrderFulfillmentService
 * 
 * Handles inventory allocation and release for orders
 * Integrates with the Inventory/Warehouse module
 */
class OrderFulfillmentService
{
    /**
     * Allocate inventory for an order
     * Creates OrderItemQuantity records and updates ProductInventory
     * 
     * @param Order $order
     * @param int|null $warehouseId Specific warehouse, or auto-select
     * @return array Allocation results
     */
    public function allocateInventory(Order $order, ?int $warehouseId = null): array
    {
        // Skip allocation for consignment orders as their inventory is
        // handled directly at the consignment SENT stage.
        if ($order->external_source === 'consignment') {
            return [
                'skipped' => true,
                'reason' => 'Consignment inventory already deducted',
                'allocated' => [],
                'partial' => [],
                'failed' => [],
            ];
        }

        return DB::transaction(function () use ($order, $warehouseId) {
            
            Log::info("Allocating inventory for order", [
                'order_id' => $order->id,
                'warehouse_id' => $warehouseId,
            ]);
            
            $results = [
                'allocated' => [],
                'partial' => [],
                'failed' => [],
            ];
            
            foreach ($order->items as $item) {
                try {
                    $allocation = $this->allocateItem($item, $warehouseId);
                    
                    if ($allocation['allocated'] === $item->quantity) {
                        $results['allocated'][] = $allocation;
                    } elseif ($allocation['allocated'] > 0) {
                        $results['partial'][] = $allocation;
                    } else {
                        $results['failed'][] = $allocation;
                    }
                    
                } catch (\Exception $e) {
                    $results['failed'][] = [
                        'item_id' => $item->id,
                        'sku' => $item->sku,
                        'allocated' => 0,
                        'error' => $e->getMessage(),
                    ];
                }
            }
            
            Log::info("Inventory allocation completed", [
                'order_id' => $order->id,
                'allocated' => count($results['allocated']),
                'partial' => count($results['partial']),
                'failed' => count($results['failed']),
            ]);

            // Set order-level warehouse_id to the most-used warehouse (removes "Non-Stock" label)
            if (!$order->warehouse_id) {
                $order->refresh();
                $dominantWarehouse = $order->items()
                    ->whereNotNull('warehouse_id')
                    ->select('warehouse_id', DB::raw('count(*) as cnt'))
                    ->groupBy('warehouse_id')
                    ->orderByDesc('cnt')
                    ->value('warehouse_id');
                if ($dominantWarehouse) {
                    $order->update(['warehouse_id' => $dominantWarehouse]);
                }
            }

            return $results;
        });
    }

    /**
     * Allocate inventory for a single order item
     * 
     * @param OrderItem $item
     * @param int|null $warehouseId
     * @return array
     */
    protected function allocateItem(OrderItem $item, ?int $warehouseId = null): array
    {
        $quantityNeeded = $item->quantity;
        $quantityAllocated = 0;
        
        // Handle addons — only deduct if track_inventory is enabled
        if ($item->isAddon()) {
            $addon = $item->addon;
            if ($addon && $addon->track_inventory) {
                // Find inventory for this addon (prefer specific warehouse)
                $addonInventories = ProductInventory::where('add_on_id', $item->add_on_id)
                    ->where('quantity', '>', 0)
                    ->when($warehouseId, fn($q) => $q->orderByRaw("CASE WHEN warehouse_id = {$warehouseId} THEN 0 ELSE 1 END"))
                    ->orderBy('quantity', 'desc')
                    ->get();

                foreach ($addonInventories as $inventory) {
                    if ($quantityAllocated >= $quantityNeeded) break;

                    $available    = $inventory->quantity;
                    $qtyToAllocate = min($available, $quantityNeeded - $quantityAllocated);

                    if ($qtyToAllocate > 0) {
                        OrderItemQuantity::updateOrCreate(
                            ['order_item_id' => $item->id, 'warehouse_id' => $inventory->warehouse_id],
                            ['quantity' => $qtyToAllocate]
                        );
                        $inventory->decrement('quantity', $qtyToAllocate);
                        InventoryLog::create([
                            'warehouse_id'       => $inventory->warehouse_id,
                            'add_on_id'          => $item->add_on_id,
                            'action'             => 'sale',
                            'quantity_before'    => $available,
                            'quantity_after'     => $available - $qtyToAllocate,
                            'quantity_change'    => -$qtyToAllocate,
                            'reference_type'     => 'order',
                            'reference_id'       => $item->order_id,
                            'notes'              => "Addon allocated for Order #{$item->order->order_number}",
                        ]);
                        // Set warehouse on the item so quote detail shows it
                        if (!$item->warehouse_id) {
                            $item->update(['warehouse_id' => $inventory->warehouse_id]);
                        }
                        $quantityAllocated += $qtyToAllocate;
                    }
                }
            } else {
                // Non-tracked addon — mark as allocated, no inventory deduction needed
                $quantityAllocated = $quantityNeeded;
            }

            $item->update(['allocated_quantity' => $quantityAllocated]);
            return [
                'item_id'   => $item->id,
                'sku'       => $item->sku,
                'requested' => $quantityNeeded,
                'allocated' => $quantityAllocated,
                'is_addon'  => true,
            ];
        }
        
        // Get available inventory
        if (!$item->product_variant_id) {
            // No variant linked — cannot allocate inventory, treat as non-stock
            $item->update(['allocated_quantity' => 0]);
            return [
                'item_id'   => $item->id,
                'sku'       => $item->sku ?? 'N/A',
                'requested' => $quantityNeeded,
                'allocated' => 0,
                'is_addon'  => false,
                'error'     => 'No product variant linked to this item',
            ];
        }

        $inventories = $this->getAvailableInventory(
            $item->product_variant_id,
            $warehouseId ?? $item->order->warehouse_id
        );
        
        foreach ($inventories as $inventory) {
            if ($quantityAllocated >= $quantityNeeded) {
                break;
            }
            
            $availableQty = $inventory->quantity;
            $qtyToAllocate = min($availableQty, $quantityNeeded - $quantityAllocated);
            
            if ($qtyToAllocate > 0) {
                // Create or update allocation record
                OrderItemQuantity::updateOrCreate(
                    [
                        'order_item_id' => $item->id,
                        'warehouse_id' => $inventory->warehouse_id,
                    ],
                    [
                        'quantity' => $qtyToAllocate,
                    ]
                );
                
                // Reduce inventory
                $inventory->decrement('quantity', $qtyToAllocate);
                
                // Set warehouse on the order item itself for quote detail visibility
                if (!$item->warehouse_id) {
                    $item->update(['warehouse_id' => $inventory->warehouse_id]);
                }
                
                // Create inventory log
                InventoryLog::create([
                    'warehouse_id' => $inventory->warehouse_id,
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'action' => 'sale',
                    'quantity_before' => $availableQty,
                    'quantity_after' => $availableQty - $qtyToAllocate,
                    'quantity_change' => -$qtyToAllocate,
                    'reference_type' => 'order',
                    'reference_id' => $item->order_id,
                    'notes' => "Allocated for Order #{$item->order->order_number}",
                ]);
                
                $quantityAllocated += $qtyToAllocate;
            }
        }
        
        // Update item allocated quantity
        $item->update(['allocated_quantity' => $quantityAllocated]);
        
        return [
            'item_id' => $item->id,
            'sku' => $item->sku,
            'requested' => $quantityNeeded,
            'allocated' => $quantityAllocated,
            'is_addon' => false,
        ];
    }

    /**
     * Get available inventory for a product variant
     * 
     * @param int $variantId
     * @param int|null $warehouseId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function getAvailableInventory(int $variantId, ?int $warehouseId = null)
    {
        $query = ProductInventory::where('product_variant_id', $variantId)
            ->where('quantity', '>', 0);
        
        if ($warehouseId) {
            // Prefer specific warehouse first, then others
            $query->orderByRaw("CASE WHEN warehouse_id = {$warehouseId} THEN 0 ELSE 1 END");
        }
        
        return $query->orderBy('quantity', 'desc')->get();
    }

    /**
     * Validate if order can be fulfilled with current inventory
     * 
     * @param Order $order
     * @return array
     */
    public function validateInventoryAvailability(Order $order): array
    {
        $results = [
            'can_fulfill' => true,
            'items' => [],
        ];
        
        foreach ($order->items as $item) {
            // Skip addons
            if ($item->isAddon()) {
                $results['items'][] = [
                    'item_id' => $item->id,
                    'sku' => $item->sku,
                    'available' => true,
                    'quantity_needed' => $item->quantity,
                    'quantity_available' => $item->quantity,
                ];
                continue;
            }
            
            // Check inventory
            $available = ProductInventory::where('product_variant_id', $item->product_variant_id)
                ->sum('quantity');
            
            $isAvailable = $available >= $item->quantity;
            
            if (!$isAvailable) {
                $results['can_fulfill'] = false;
            }
            
            $results['items'][] = [
                'item_id' => $item->id,
                'sku' => $item->sku,
                'available' => $isAvailable,
                'quantity_needed' => $item->quantity,
                'quantity_available' => $available,
                'shortage' => max(0, $item->quantity - $available),
            ];
        }
        
        return $results;
    }

    /**
     * Release allocated inventory (e.g., when order is cancelled)
     * 
     * @param Order $order
     * @return int Number of items released
     */
    public function releaseInventory(Order $order): int
    {
        // Skip release for consignment orders to avoid erroneous stock increases.
        if ($order->external_source === 'consignment') {
            return 0;
        }

        return DB::transaction(function () use ($order) {
            
            Log::info("Releasing inventory for order", ['order_id' => $order->id]);
            
            $itemsReleased = 0;
            
            foreach ($order->items as $item) {
                // Release tracked addon inventory
                if ($item->isAddon()) {
                    $addon = $item->addon;
                    if ($addon && $addon->track_inventory) {
                        foreach ($item->quantities as $quantity) {
                            $inventory = ProductInventory::where('warehouse_id', $quantity->warehouse_id)
                                ->where('add_on_id', $item->add_on_id)
                                ->first();
                            if ($inventory) {
                                $oldQty = $inventory->quantity;
                                $inventory->increment('quantity', $quantity->quantity);
                                InventoryLog::create([
                                    'warehouse_id'    => $inventory->warehouse_id,
                                    'add_on_id'       => $item->add_on_id,
                                    'action'          => 'return',
                                    'quantity_before' => $oldQty,
                                    'quantity_after'  => $oldQty + $quantity->quantity,
                                    'quantity_change' => $quantity->quantity,
                                    'reference_type'  => 'order',
                                    'reference_id'    => $order->id,
                                    'notes'           => "Addon released from cancelled Order #{$order->order_number}",
                                ]);
                            }
                            $quantity->delete();
                            $itemsReleased++;
                        }
                        $item->update(['allocated_quantity' => 0]);
                    }
                    continue;
                }
                
                foreach ($item->quantities as $quantity) {
                    // Find the inventory record
                    $inventory = ProductInventory::where('warehouse_id', $quantity->warehouse_id)
                        ->where('product_variant_id', $item->product_variant_id)
                        ->first();
                    
                    if ($inventory) {
                        // Return quantity to inventory
                        $oldQty = $inventory->quantity;
                        $inventory->increment('quantity', $quantity->quantity);
                        
                        // Create inventory log
                        InventoryLog::create([
                            'warehouse_id' => $inventory->warehouse_id,
                            'product_id' => $item->product_id,
                            'product_variant_id' => $item->product_variant_id,
                            'action' => 'return',
                            'quantity_before' => $oldQty,
                            'quantity_after' => $oldQty + $quantity->quantity,
                            'quantity_change' => $quantity->quantity,
                            'reference_type' => 'order',
                            'reference_id' => $order->id,
                            'notes' => "Released from cancelled Order #{$order->order_number}",
                        ]);
                    }
                    
                    // Delete allocation record
                    $quantity->delete();
                    
                    $itemsReleased++;
                }
                
                // Reset allocated quantity on item
                $item->update(['allocated_quantity' => 0]);
            }
            
            Log::info("Inventory released", [
                'order_id' => $order->id,
                'items_released' => $itemsReleased,
            ]);
            
            return $itemsReleased;
        });
    }

    /**
     * Cancel order and process returns with specific conditions
     * 
     * @param Order $order
     * @param array $returnedItems [['item_id' => 1, 'quantity' => 2, 'warehouse_id' => 1, 'condition' => 'good']]
     * @param string $reason
     * @param string|null $notes
     * @return void
     */
    public function cancelOrderWithReturns(Order $order, array $returnedItems, string $reason, ?string $notes = null): void
    {
        DB::transaction(function () use ($order, $returnedItems, $reason, $notes) {
            
            Log::info("Cancelling order with returns", ['order_id' => $order->id]);
            
            // For each item in the return array, we need to return it to the exact warehouse + condition
            foreach ($returnedItems as $returnReq) {
                $item = $order->items()->find($returnReq['item_id']);
                if (!$item) continue;
                
                $quantity = (int)$returnReq['quantity'];
                $warehouseId = $returnReq['warehouse_id'];
                $condition = $returnReq['condition'] ?? 'good';
                
                // Tracked addons
                if ($item->isAddon()) {
                    $addon = $item->addon;
                    if ($addon && $addon->track_inventory) {
                        if ($condition === 'good') {
                            $inventory = ProductInventory::firstOrCreate(
                                ['warehouse_id' => $warehouseId, 'add_on_id' => $item->add_on_id],
                                ['quantity' => 0]
                            );
                            
                            $oldQty = $inventory->quantity;
                            $inventory->increment('quantity', $quantity);
                            
                            InventoryLog::create([
                                'warehouse_id'    => $warehouseId,
                                'add_on_id'       => $item->add_on_id,
                                'action'          => 'return',
                                'quantity_before' => $oldQty,
                                'quantity_after'  => $oldQty + $quantity,
                                'quantity_change' => $quantity,
                                'reference_type'  => 'order',
                                'reference_id'    => $order->id,
                                'notes'           => "Addon returned from cancelled Order #{$order->order_number} (Good)",
                            ]);
                        } else {
                            // Damaged addon logic
                            $damagedNotes = "Returned from cancelled Order #{$order->order_number}";
                            if (!empty($notes)) $damagedNotes .= " - Notes: {$notes}";
                            
                            \App\Modules\Inventory\Models\DamagedInventory::create([
                                'add_on_id' => $item->add_on_id,
                                'warehouse_id' => $warehouseId,
                                'quantity' => $quantity,
                                'condition' => $condition,
                                'notes' => $damagedNotes,
                                'order_id' => $order->id,
                            ]);
                        }
                    }
                    continue;
                }
                
                // Regular variants
                if (!$item->product_variant_id) continue;
                
                if ($condition === 'good') {
                    $variant = clone $item; // Quick hack to check variant id exist
                    $inventory = ProductInventory::firstOrCreate(
                        ['warehouse_id' => $warehouseId, 'product_variant_id' => $item->product_variant_id],
                        ['product_id' => $item->product_id, 'quantity' => 0]
                    );
                    
                    $oldQty = $inventory->quantity;
                    $inventory->increment('quantity', $quantity);
                    
                    InventoryLog::create([
                        'warehouse_id' => $warehouseId,
                        'product_id' => $item->product_id,
                        'product_variant_id' => $item->product_variant_id,
                        'action' => 'return',
                        'quantity_before' => $oldQty,
                        'quantity_after' => $oldQty + $quantity,
                        'quantity_change' => $quantity,
                        'reference_type' => 'order',
                        'reference_id' => $order->id,
                        'notes' => "Returned from cancelled Order #{$order->order_number} (Good)",
                    ]);
                } else {
                    $damagedNotes = "Returned from cancelled Order #{$order->order_number}";
                    if (!empty($notes)) $damagedNotes .= " - Notes: {$notes}";
                    
                    \App\Modules\Inventory\Models\DamagedInventory::create([
                        'product_variant_id' => $item->product_variant_id,
                        'warehouse_id' => $warehouseId,
                        'quantity' => $quantity,
                        'condition' => $condition,
                        'notes' => $damagedNotes,
                        'order_id' => $order->id,
                    ]);
                }
            }
            
            // Clean up allocation items and zero out items allocation count
            // Since this replaces the auto-release, we must delete allocs
            foreach ($order->items as $item) {
                foreach ($item->quantities as $quantityRecord) {
                    $quantityRecord->delete();
                }
                $item->update(['allocated_quantity' => 0, 'shipped_quantity' => 0]);
            }
            
            // Mark the order as cancelled
            $order->update(['order_status' => \App\Modules\Orders\Enums\OrderStatus::CANCELLED]);
            
            // Add note to order history
            $order->notes()->create([
                'note' => "Order cancelled. Reason: {$reason}. " . ($notes ? "Notes: {$notes}" : ""),
                'created_by' => auth()->id() ?? 1,
            ]);
            
            // recalculate (zeros out)
            $order->calculateTotals();
        });
    }

    /**
     * Mark items as shipped
     * 
     * @param Order $order
     * @param array $itemQuantities ['item_id' => quantity]
     * @return bool
     */
    public function markAsShipped(Order $order, array $itemQuantities = []): bool
    {
        foreach ($order->items as $item) {
            $qtyToShip = $itemQuantities[$item->id] ?? $item->allocated_quantity;
            $item->update(['shipped_quantity' => $qtyToShip]);
        }
        
        return true;
    }

    /**
     * Get fulfillment summary for an order
     * 
     * @param Order $order
     * @return array
     */
    public function getFulfillmentSummary(Order $order): array
    {
        $summary = [
            'total_items' => 0,
            'fully_allocated' => 0,
            'partially_allocated' => 0,
            'not_allocated' => 0,
            'fully_shipped' => 0,
            'partially_shipped' => 0,
            'not_shipped' => 0,
        ];
        
        foreach ($order->items as $item) {
            $summary['total_items']++;
            
            // Allocation status
            if ($item->allocated_quantity >= $item->quantity) {
                $summary['fully_allocated']++;
            } elseif ($item->allocated_quantity > 0) {
                $summary['partially_allocated']++;
            } else {
                $summary['not_allocated']++;
            }
            
            // Shipping status
            if ($item->shipped_quantity >= $item->quantity) {
                $summary['fully_shipped']++;
            } elseif ($item->shipped_quantity > 0) {
                $summary['partially_shipped']++;
            } else {
                $summary['not_shipped']++;
            }
        }
        
        return $summary;
    }
}
