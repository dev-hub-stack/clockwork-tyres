<?php

namespace App\Filament\Resources\ConsignmentResource\Pages;

use App\Filament\Resources\ConsignmentResource;
use App\Modules\Consignments\Models\Consignment;
use App\Modules\Consignments\Services\ConsignmentService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\UniqueConstraintViolationException;

class CreateConsignment extends CreateRecord
{
    protected static string $resource = ConsignmentResource::class;

    /**
     * Override to use ConsignmentService which handles inventory reduction
     */
    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        // Debug: Log the data received
        \Illuminate\Support\Facades\Log::debug('CreateConsignment::handleRecordCreation - Data received', [
            'total_value' => $data['total_value'] ?? 'NOT SET',
            'subtotal' => $data['subtotal'] ?? 'NOT SET',
            'total' => $data['total'] ?? 'NOT SET',
        ]);
        
        $attempts = 0;
        while (true) {
            try {
                // Use ConsignmentService to create consignment with inventory reduction
                $service = app(ConsignmentService::class);
                
                // Prepare items data for the service
                $items = [];
                foreach ($data['items'] ?? [] as $item) {
                    $items[] = [
                        'product_variant_id' => $item['product_variant_id'],
                        'quantity_sent' => $item['quantity_sent'] ?? $item['quantity'] ?? 1,
                        'warehouse_id' => $item['warehouse_id'] ?? $data['warehouse_id'] ?? null,
                        'price' => $item['price'] ?? 0,
                        'sku' => $item['sku'] ?? null,
                        'product_name' => $item['product_name'] ?? null,
                        'brand_name' => $item['brand_name'] ?? null,
                        'notes' => $item['notes'] ?? null,
                        'tax_inclusive' => $item['tax_inclusive'] ?? true,
                    ];
                }
                
                $consignmentData = [
                    'consignment_number' => $data['consignment_number'] ?? Consignment::generateConsignmentNumber(),
                    'customer_id' => $data['customer_id'],
                    'warehouse_id' => $data['warehouse_id'] ?? null,
                    'representative_id' => $data['representative_id'] ?? null,
                    'created_by' => $data['created_by'] ?? auth()->id(),
                    'issue_date' => $data['issue_date'] ?? now(),
                    'expected_return_date' => $data['expected_return_date'] ?? null,
                    'tracking_number' => $data['tracking_number'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'internal_notes' => $data['internal_notes'] ?? null,
                    'terms_conditions' => $data['terms_conditions'] ?? null,
                    'shipping_cost' => $data['shipping_cost'] ?? 0,
                    'discount' => $data['discount'] ?? 0,
                    'subtotal' => $data['subtotal'] ?? 0,
                    'tax' => $data['tax'] ?? 0,
                    'total' => $data['total'] ?? 0,
                    'total_value' => $data['total_value'] ?? 0,
                    'invoiced_value' => $data['invoiced_value'] ?? 0,
                    'returned_value' => $data['returned_value'] ?? 0,
                    'balance_value' => $data['balance_value'] ?? 0,
                    'status' => $data['status'] ?? \App\Modules\Consignments\Enums\ConsignmentStatus::DRAFT,
                    'items' => $items,
                ];
                
                \Illuminate\Support\Facades\Log::debug('CreateConsignment::handleRecordCreation - ConsignmentData', [
                    'total_value' => $consignmentData['total_value'],
                    'subtotal' => $consignmentData['subtotal'],
                ]);
                
                return $service->createConsignment($consignmentData);
            } catch (UniqueConstraintViolationException $e) {
                if (++$attempts >= 5) {
                    throw $e;
                }
                // Regenerate a fresh number and retry
                $data['consignment_number'] = Consignment::generateConsignmentNumber();
            }
        }
    }

    /**
     * Mutate form data before creating the record
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Ensure shipping_cost is never null (DB column is NOT NULL)
        $data['shipping_cost'] = floatval($data['shipping_cost'] ?? 0);

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
     * Service already handles totals calculation, but ensure counts are correct
     */
    protected function afterCreate(): void
    {
        // Service already calculated totals and item counts
        // Just refresh the record to ensure we have latest data
        $this->record->refresh();
    }

    /**
     * Redirect to list page after creation
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
