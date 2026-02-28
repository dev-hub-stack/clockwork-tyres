<?php

namespace App\Filament\Resources\AddonResource\Pages;

use App\Filament\Resources\AddonResource;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\ProductInventory;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAddon extends EditRecord
{
    protected static string $resource = AddonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * After the addon is saved, sync per-warehouse inventory quantities.
     */
    protected function afterSave(): void
    {
        $this->syncWarehouseInventory();
    }

    protected function syncWarehouseInventory(): void
    {
        // Use $this->data (raw Livewire state) — $this->form->getState() excludes dehydrated(false) fields
        $data = $this->data;
        $record = $this->record;

        $warehouses = Warehouse::where('status', 1)
            ->where('code', '!=', 'NON-STOCK')
            ->get();

        $totalQty = 0;

        foreach ($warehouses as $warehouse) {
            $key = 'warehouse_qty_' . $warehouse->id;
            $qty = isset($data[$key]) ? (int) $data[$key] : 0;

            ProductInventory::updateOrCreate(
                [
                    'add_on_id'    => $record->id,
                    'warehouse_id' => $warehouse->id,
                ],
                [
                    'quantity' => $qty,
                    // Ensure product_id and product_variant_id stay null
                    'product_id'         => null,
                    'product_variant_id' => null,
                ]
            );

            $totalQty += $qty;
        }

        // Keep total_quantity in sync
        $record->updateQuietly(['total_quantity' => $totalQty]);
    }
}
