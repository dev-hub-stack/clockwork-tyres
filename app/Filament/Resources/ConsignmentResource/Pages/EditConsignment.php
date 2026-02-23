<?php

namespace App\Filament\Resources\ConsignmentResource\Pages;

use App\Filament\Resources\ConsignmentResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditConsignment extends EditRecord
{
    protected static string $resource = ConsignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    /**
     * Mutate form data before filling the form
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Load items relationship if not loaded
        if (!isset($data['items']) && $this->record) {
            $data['items'] = $this->record->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'product_variant_id' => $item->product_variant_id,
                    'warehouse_id' => $item->warehouse_id,
                    'sku' => $item->sku,
                    'product_name' => $item->product_name,
                    'brand_name' => $item->brand_name,
                    'quantity_sent' => $item->quantity_sent,
                    'price' => $item->price,
                    'notes' => $item->notes,
                ];
            })->toArray();
        }
        
        return $data;
    }

    /**
     * Mutate form data before saving
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Calculate totals using centralized method from ConsignmentForm
        $items = $data['items'] ?? [];
        $shipping = floatval($data['shipping_cost'] ?? 0);
        $discount = floatval($data['discount'] ?? 0);
        
        $totals = \App\Filament\Resources\ConsignmentResource\Schemas\ConsignmentForm::calculateValues($items, $shipping, $discount);
        
        // Set calculated values
        $data['subtotal'] = $totals['sub_total'];
        $data['tax'] = $totals['vat'];
        $data['total'] = $totals['total'];
        
        // Only update total value to reflect current total sent items.
        // DO NOT overwrite invoiced_value or returned_value, as these are managed by
        // RecordSaleAction and RecordReturnAction respectively.
        $data['total_value'] = $totals['sub_total'];
        
        // Re-calculate balance manually instead of trusting the form defaults
        $invoiced = floatval($this->record->invoiced_value ?? 0);
        $returned = floatval($this->record->returned_value ?? 0);
        
        $data['balance_value'] = $totals['sub_total'] - $invoiced - $returned;
        
        \Illuminate\Support\Facades\Log::info('EditConsignment mutateFormDataBeforeSave:', $data);
        
        return $data;
    }

    protected function afterSave(): void
    {
        // Items and totals are already handled in mutateFormDataBeforeSave
        // Just update item counts since those depend on the saved items
        $this->record->updateItemCounts();
        
        // Refresh to get latest data
        $this->record->refresh();

        \Illuminate\Support\Facades\Log::info('EditConsignment afterSave DB record:', $this->record->toArray());
    }
}
