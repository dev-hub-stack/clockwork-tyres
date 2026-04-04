<?php

namespace App\Observers;

use App\Models\Addon;
use App\Modules\Accounts\Support\CurrentAccountResolver;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\ProductInventory;
use App\Services\ActivityLogService;

class AddonInventoryObserver
{
    /**
     * Handle the Addon "created" event.
     * Auto-creates inventory records for all active warehouses when an addon is created.
     */
    public function created(Addon $addon): void
    {
        // Get all active warehouses
        $warehouses = Warehouse::query()
            ->where('status', 1)
            ->when(
                auth()->check() && request(),
                function ($query) {
                    $currentAccountId = app(CurrentAccountResolver::class)
                        ->resolve(request(), auth()->user())
                        ->currentAccount?->id;

                    if ($currentAccountId) {
                        $query->where('account_id', $currentAccountId);
                    }
                }
            )
            ->get();
        
        foreach ($warehouses as $warehouse) {
            ProductInventory::create([
                'warehouse_id' => $warehouse->id,
                'add_on_id' => $addon->id,
                'quantity' => 0,
                'eta' => null,
                'eta_qty' => 0,
            ]);
        }
        
        \Log::info("Auto-created inventory for addon", [
            'addon_id' => $addon->id,
            'addon_title' => $addon->title,
            'warehouses_count' => $warehouses->count(),
        ]);

        if (auth()->check()) {
            ActivityLogService::log(
                'product_added',
                'Added add-on ' . ($addon->title ?: ('#' . $addon->id)),
                $addon,
            );
        }
    }

    /**
     * Handle the Addon "updated" event.
     */
    public function updated(Addon $addon): void
    {
        if (! auth()->check() || empty($addon->getChanges())) {
            return;
        }

        ActivityLogService::log(
            'product_updated',
            'Updated add-on ' . ($addon->title ?: ('#' . $addon->id)),
            $addon,
        );
    }

    /**
     * Handle the Addon "deleted" event.
     * Optionally clean up inventory records when addon is deleted.
     */
    public function deleted(Addon $addon): void
    {
        // Soft delete - keep inventory records for historical purposes
        // ProductInventory::where('add_on_id', $addon->id)->delete();
        
        \Log::info("Addon deleted", [
            'addon_id' => $addon->id,
            'addon_title' => $addon->title,
        ]);
    }
}
