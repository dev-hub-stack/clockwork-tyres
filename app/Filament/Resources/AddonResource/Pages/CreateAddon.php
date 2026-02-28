<?php

namespace App\Filament\Resources\AddonResource\Pages;

use App\Filament\Resources\AddonResource;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\ProductInventory;
use Filament\Resources\Pages\CreateRecord;

class CreateAddon extends CreateRecord
{
    protected static string $resource = AddonResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * After the addon is created, sync per-warehouse inventory quantities.
     */
    protected function afterCreate(): void
    {
        $data = $this->form->getState();
        $record = $this->record;

        $warehouses = Warehouse::where('status', 1)
            ->where('code', '!=', 'NON-STOCK')
            ->get();

        $totalQty = 0;

        foreach ($warehouses as $warehouse) {
            $key = 'warehouse_qty_' . $warehouse->id;
            $qty = isset($data[$key]) ? (int) $data[$key] : 0;

            if ($qty > 0) {
                ProductInventory::updateOrCreate(
                    [
                        'add_on_id'    => $record->id,
                        'warehouse_id' => $warehouse->id,
                    ],
                    [
                        'quantity'           => $qty,
                        'product_id'         => null,
                        'product_variant_id' => null,
                    ]
                );
            }

            $totalQty += $qty;
        }

        if ($totalQty > 0) {
            $record->updateQuietly(['total_quantity' => $totalQty]);
        }
    }
}
