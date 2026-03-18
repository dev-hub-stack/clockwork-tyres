<?php

namespace App\Observers;

use App\Modules\Inventory\Models\InventoryLog;
use App\Services\ActivityLogService;

class InventoryLogObserver
{
    public function created(InventoryLog $inventoryLog): void
    {
        $userId = $inventoryLog->user_id ?? auth()->id();

        if (! $userId) {
            return;
        }

        $action = $inventoryLog->action === InventoryLog::ACTION_IMPORT
            ? 'inventory_stock_in'
            : 'inventory_adjusted';

        $quantity = abs((int) ($inventoryLog->quantity_change ?? 0));
        $reference = $inventoryLog->reference_type
            ? ' via ' . str_replace('_', ' ', $inventoryLog->reference_type)
            : '';

        $description = match ($inventoryLog->action) {
            InventoryLog::ACTION_IMPORT => "Added {$quantity} units to inventory{$reference}",
            InventoryLog::ACTION_TRANSFER_IN => "Adjusted inventory: transfer in{$reference}",
            InventoryLog::ACTION_TRANSFER_OUT => "Adjusted inventory: transfer out{$reference}",
            default => 'Adjusted inventory' . ($inventoryLog->notes ? ' - ' . $inventoryLog->notes : $reference),
        };

        ActivityLogService::log($action, $description, $inventoryLog, $userId);
    }
}