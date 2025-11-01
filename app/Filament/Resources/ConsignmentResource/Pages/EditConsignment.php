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
            $data['items'] = $this->record->items->toArray();
        }
        
        return $data;
    }

    /**
     * Mutate form data before saving
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Calculate totals from items
        $items = $data['items'] ?? [];
        $subtotal = 0;
        
        foreach ($items as $item) {
            $qty = floatval($item['quantity_sent'] ?? 0);
            $price = floatval($item['price'] ?? 0);
            $subtotal += ($qty * $price);
        }
        
        // Get tax rate from settings
        $taxSetting = \App\Modules\Settings\Models\TaxSetting::getDefault();
        $taxRate = $taxSetting ? floatval($taxSetting->rate) : 5;
        
        $tax = $subtotal * ($taxRate / 100);
        $discount = floatval($data['discount'] ?? 0);
        $shipping = floatval($data['shipping_cost'] ?? 0);
        $total = $subtotal + $tax - $discount + $shipping;
        
        // Set calculated values
        $data['subtotal'] = $subtotal;
        $data['tax'] = $tax;
        $data['total'] = $total;
        
        return $data;
    }
}
