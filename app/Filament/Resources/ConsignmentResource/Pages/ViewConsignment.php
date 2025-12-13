<?php

namespace App\Filament\Resources\ConsignmentResource\Pages;

use App\Filament\Resources\ConsignmentResource;
use App\Filament\Resources\ConsignmentResource\Actions\RecordSaleAction;
use App\Filament\Resources\ConsignmentResource\Actions\RecordReturnAction;
use App\Filament\Resources\ConsignmentResource\Actions\ConvertToInvoiceAction;
use App\Filament\Resources\ConsignmentResource\Actions\MarkAsSentAction;
use App\Filament\Resources\ConsignmentResource\Actions\CancelConsignmentAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewConsignment extends ViewRecord
{
    protected static string $resource = ConsignmentResource::class;

    /**
     * Get the title for the view page
     * Shows consignment number in the header
     */
    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return 'Consignment: ' . $this->record->consignment_number;
    }

    /**
     * Header actions for consignment workflow
     * 
     * Each action has its own visibility logic:
     * - MarkAsSent: Only visible when status = 'draft'
     * - RecordSale: Visible when canRecordSale() = true (SENT/DELIVERED/PARTIALLY_SOLD with available items)
     * - RecordReturn: Visible when canRecordReturn() = true (has items that can be returned)
     * - ConvertToInvoice: Visible when canRecordSale() AND not already converted
     * - CancelConsignment: Visible based on cancellation rules
     * - Edit: Always visible (Filament handles permissions)
     */
    protected function getHeaderActions(): array
    {
        return [
            // Primary workflow actions (in logical order)
            MarkAsSentAction::make(),
            RecordSaleAction::make(),
            RecordReturnAction::make(),
            ConvertToInvoiceAction::make(),
            
            // Secondary actions
            CancelConsignmentAction::make()
                ->color('danger'),
            
            // Standard edit action
            EditAction::make()
                ->color('gray'),
        ];
    }
}
