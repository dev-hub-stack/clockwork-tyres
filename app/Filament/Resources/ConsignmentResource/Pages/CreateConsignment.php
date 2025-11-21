<?php

namespace App\Filament\Resources\ConsignmentResource\Pages;

use App\Filament\Resources\ConsignmentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateConsignment extends CreateRecord
{
    protected static string $resource = ConsignmentResource::class;

    /**
     * Mutate form data before creating the record
     */
    protected function mutateFormDataBeforeCreate(array $data): array
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
        
        $discount = floatval($data['discount'] ?? 0);
        $shipping = floatval($data['shipping_cost'] ?? 0);
        
        // Tax on discounted amount
        $discountedAmount = $subtotal - $discount;
        $tax = $discountedAmount * ($taxRate / 100);
        $total = $discountedAmount + $tax + $shipping;
        
        // Set calculated values
        $data['subtotal'] = $subtotal;
        $data['tax'] = $tax;
        $data['total'] = $total;
        
        // Generate consignment number if not set
        if (empty($data['consignment_number'])) {
            $data['consignment_number'] = \App\Modules\Consignments\Models\Consignment::generateConsignmentNumber();
        }
        
        // Set created_by
        $data['created_by'] = auth()->id();
        
        // Save notes to cache for next consignment
        if (!empty($data['notes'])) {
            cache()->put("consignment_notes_{$data['created_by']}", $data['notes'], now()->addDays(30));
        }
        
        return $data;
    }
    
    /**
     * Redirect to list page after creation
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
