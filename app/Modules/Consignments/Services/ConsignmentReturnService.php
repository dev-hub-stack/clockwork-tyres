<?php

namespace App\Modules\Consignments\Services;

use App\Modules\Consignments\Models\Consignment;
use App\Modules\Consignments\Models\ConsignmentItem;
use App\Modules\Consignments\Enums\ConsignmentStatus;
use App\Modules\Inventory\Models\ProductInventory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ConsignmentReturnService
{
    /**
     * Record return of consigned items
     * 
     * @param Consignment $consignment
     * @param array $returnedItems [['item_id' => 1, 'quantity' => 2, 'warehouse_id' => 1, 'condition' => 'good'], ...]
     * @param string|null $reason
     * @param string|null $notes
     * @return void
     * @throws \Exception
     */
    public function recordReturn(
        Consignment $consignment,
        array $returnedItems,
        ?string $reason = null,
        ?string $notes = null
    ): void {
        // Validate the return operation
        $this->validateReturn($consignment, $returnedItems);
        
        DB::transaction(function () use ($consignment, $returnedItems, $reason, $notes) {
            // 1. Update consignment items
            $this->updateConsignmentItemsAfterReturn($consignment, $returnedItems);
            
            // 2. Add items back to warehouse inventory
            $this->updateWarehouseInventory($consignment, $returnedItems);
            
            // 3. Update consignment status
            $this->updateConsignmentStatusAfterReturn($consignment);
            
            // 4. Log the return action
            $this->logReturnAction($consignment, $returnedItems, $reason, $notes);
            
            Log::info('Consignment return recorded successfully', [
                'consignment_id' => $consignment->id,
                'consignment_number' => $consignment->consignment_number,
                'returned_items_count' => count($returnedItems),
                'reason' => $reason,
            ]);
        });
    }
    
    /**
     * Validate return operation
     */
    protected function validateReturn(Consignment $consignment, array $returnedItems): void
    {
        // Check if consignment can record return
        if (!$consignment->canRecordReturn()) {
            throw new \InvalidArgumentException(
                "Cannot record return for consignment with status: {$consignment->status->getLabel()}"
            );
        }
        
        // Validate returned items
        if (empty($returnedItems)) {
            throw new \InvalidArgumentException('No items selected for return');
        }
        
        // Validate each item's availability for return
        foreach ($returnedItems as $itemData) {
            $item = $consignment->items()->find($itemData['item_id']);
            
            if (!$item) {
                throw new \InvalidArgumentException("Item {$itemData['item_id']} not found in consignment");
            }
            
            // Check if there's quantity available to return (sent items that haven't been sold or returned)
            $availableToReturn = $item->quantity_sent - ($item->quantity_sold ?? 0) - ($item->quantity_returned ?? 0);
            
            if ($itemData['quantity'] > $availableToReturn) {
                throw new \InvalidArgumentException(
                    "Cannot return {$itemData['quantity']} of item '{$item->product_name}'. Only {$availableToReturn} available to return."
                );
            }
            
            // Validate warehouse exists
            if (empty($itemData['warehouse_id'])) {
                throw new \InvalidArgumentException('Warehouse ID is required for returns');
            }
        }
    }
    
    /**
     * Update consignment items after return
     */
    protected function updateConsignmentItemsAfterReturn(Consignment $consignment, array $returnedItems): void
    {
        foreach ($returnedItems as $itemData) {
            $item = $consignment->items()->find($itemData['item_id']);
            
            if ($item) {
                // Update quantity returned
                $item->update([
                    'quantity_returned' => $item->quantity_returned + $itemData['quantity'],
                    'return_warehouse_id' => $itemData['warehouse_id'],
                    'return_condition' => $itemData['condition'] ?? 'good',
                    'status' => 'returned',
                ]);
            }
        }
        
        // Update consignment item counts
        $consignment->updateItemCounts();
    }
    
    /**
     * Update warehouse inventory after return
     */
    protected function updateWarehouseInventory(Consignment $consignment, array $returnedItems): void
    {
        foreach ($returnedItems as $itemData) {
            $item = $consignment->items()->find($itemData['item_id']);
            
            if (!$item || !$item->product_variant_id) {
                continue; // Skip items without product variant (external products)
            }
            
            $warehouseId = $itemData['warehouse_id'];
            $quantity = $itemData['quantity'];
            $condition = $itemData['condition'] ?? 'good';
            
            // Only add back to inventory if condition is good
            if ($condition === 'good') {
                // Get product_id from variant relationship
                $variant = $item->productVariant;
                $productId = $variant ? $variant->product_id : null;
                
                // Find or create product inventory record
                $inventory = ProductInventory::firstOrCreate(
                    [
                        'product_variant_id' => $item->product_variant_id,
                        'warehouse_id' => $warehouseId,
                    ],
                    [
                        'product_id' => $productId,
                        'quantity' => 0,
                        'cost_price' => 0,
                    ]
                );
                
                // Increment quantity
                $inventory->increment('quantity', $quantity);
                
                Log::info('Warehouse inventory updated after consignment return', [
                    'product_variant_id' => $item->product_variant_id,
                    'warehouse_id' => $warehouseId,
                    'quantity_added' => $quantity,
                    'new_total' => $inventory->fresh()->quantity,
                ]);
            } else {
                // Log damaged/defective items separately
                Log::warning('Damaged/defective item returned - not added to inventory', [
                    'consignment_item_id' => $item->id,
                    'product_variant_id' => $item->product_variant_id,
                    'condition' => $condition,
                    'quantity' => $quantity,
                ]);
            }
        }
    }
    
    /**
     * Update consignment status after return
     */
    protected function updateConsignmentStatusAfterReturn(Consignment $consignment): void
    {
        $consignment->refresh();
        
        Log::debug('ConsignmentReturnService::updateConsignmentStatusAfterReturn', [
            'consignment_id' => $consignment->id,
            'items_sent_count' => $consignment->items_sent_count,
            'items_sold_count' => $consignment->items_sold_count,
            'items_returned_count' => $consignment->items_returned_count,
        ]);
        
        // Determine new status based on items
        $newStatus = null;
        
        // If all sent items are returned
        if ($consignment->items_returned_count >= $consignment->items_sent_count) {
            $newStatus = ConsignmentStatus::RETURNED;
        }
        // If some items are returned but not all
        elseif ($consignment->items_returned_count > 0 && $consignment->items_returned_count < $consignment->items_sent_count) {
            $newStatus = ConsignmentStatus::PARTIALLY_RETURNED;
        }
        // If all non-returned items are sold
        elseif (($consignment->items_sold_count + $consignment->items_returned_count) >= $consignment->items_sent_count) {
            $newStatus = ConsignmentStatus::INVOICED_IN_FULL;
        }
        // If some items are sold
        elseif ($consignment->items_sold_count > 0) {
            $newStatus = ConsignmentStatus::PARTIALLY_SOLD;
        }
        
        Log::debug('ConsignmentReturnService::updateConsignmentStatusAfterReturn - Status determined', [
            'consignment_id' => $consignment->id,
            'new_status' => $newStatus?->value,
            'current_status' => $consignment->status?->value,
        ]);
        
        // Update status if changed
        if ($newStatus && $newStatus !== $consignment->status) {
            $consignment->update(['status' => $newStatus]);
            
            Log::debug('ConsignmentReturnService::updateConsignmentStatusAfterReturn - Status updated', [
                'consignment_id' => $consignment->id,
                'old_status' => $consignment->status?->value,
                'new_status' => $newStatus?->value,
            ]);
        }
    }
    
    /**
     * Log return action in consignment history
     */
    protected function logReturnAction(
        Consignment $consignment,
        array $returnedItems,
        ?string $reason,
        ?string $notes
    ): void {
        $totalQuantity = collect($returnedItems)->sum('quantity');
        
        $description = "Returned {$totalQuantity} item(s) to warehouse.";
        if ($reason) {
            $description .= " Reason: {$reason}.";
        }
        if ($notes) {
            $description .= " Notes: {$notes}";
        }
        
        $consignment->histories()->create([
            'action' => 'return_recorded',
            'description' => $description,
            'performed_by' => auth()->id(),
            'metadata' => [
                'action' => 'return_recorded',
                'returned_items' => $returnedItems,
                'reason' => $reason,
                'notes' => $notes,
            ],
        ]);
    }
}
