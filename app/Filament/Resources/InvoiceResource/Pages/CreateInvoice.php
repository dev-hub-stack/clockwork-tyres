<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;
    
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Ensure document_type is set to invoice
        $data['document_type'] = 'invoice';
        
        // Set default order status if not set
        if (!isset($data['order_status'])) {
            $data['order_status'] = 'pending';
        }
        
        // Set default payment status
        if (!isset($data['payment_status'])) {
            $data['payment_status'] = 'pending';
        }
        
        // Generate order number
        if (!isset($data['order_number'])) {
            $date = now()->format('Ymd');
            $count = \App\Modules\Orders\Models\Order::invoices()
                ->whereDate('created_at', now())
                ->count() + 1;
            $data['order_number'] = sprintf('INV-%s-%04d', $date, $count);
        }
        
        return $data;
    }
    
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
