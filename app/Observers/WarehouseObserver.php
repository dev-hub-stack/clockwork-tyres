<?php

namespace App\Observers;

use App\Modules\Inventory\Models\Warehouse;

class WarehouseObserver
{
    /**
     * Handle the Warehouse "creating" event.
     * Ensure only one warehouse is marked as primary
     */
    public function creating(Warehouse $warehouse): void
    {
        if ($warehouse->is_primary) {
            // Set all other warehouses to non-primary
            Warehouse::where('is_primary', true)->update(['is_primary' => false]);
        }
    }

    /**
     * Handle the Warehouse "updating" event.
     * Ensure only one warehouse is marked as primary
     */
    public function updating(Warehouse $warehouse): void
    {
        if ($warehouse->isDirty('is_primary') && $warehouse->is_primary) {
            // Set all other warehouses to non-primary
            Warehouse::where('id', '!=', $warehouse->id)
                ->where('is_primary', true)
                ->update(['is_primary' => false]);
        }
    }

    /**
     * Handle the Warehouse "deleting" event.
     * If the primary warehouse is being deleted, make another warehouse primary
     */
    public function deleting(Warehouse $warehouse): void
    {
        if ($warehouse->is_primary) {
            // Find the next active warehouse and make it primary
            $nextWarehouse = Warehouse::where('id', '!=', $warehouse->id)
                ->where('status', 1)
                ->orderBy('created_at', 'asc')
                ->first();

            if ($nextWarehouse) {
                $nextWarehouse->update(['is_primary' => true]);
            }
        }
    }
}
