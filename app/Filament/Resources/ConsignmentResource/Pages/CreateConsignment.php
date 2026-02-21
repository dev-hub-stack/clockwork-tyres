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
        // Calculate totals using centralized method from ConsignmentForm
        $items = $data['items'] ?? [];
        $shipping = floatval($data['shipping_cost'] ?? 0);
        $discount = floatval($data['discount'] ?? 0);
        
        $totals = \App\Filament\Resources\ConsignmentResource\Schemas\ConsignmentForm::calculateValues($items, $shipping, $discount);
        
        // Set basic totals
        $data['subtotal'] = $totals['sub_total'];
        $data['tax'] = $totals['vat'];
        $data['total'] = $totals['total'];
        
        // Set value tracking fields for consignments
        $data['total_value'] = $totals['sub_total'];
        $data['invoiced_value'] = 0;
        $data['returned_value'] = 0;
        $data['balance_value'] = $totals['sub_total'];
        
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
     * Recalculate totals AFTER relationship items are saved by Filament
     */
    protected function afterCreate(): void
    {
        $this->record->calculateTotals();
        $this->record->updateItemCounts();
    }

    /**
     * Redirect to list page after creation
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
