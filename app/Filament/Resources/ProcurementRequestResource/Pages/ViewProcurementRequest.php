<?php

namespace App\Filament\Resources\ProcurementRequestResource\Pages;

use App\Filament\Resources\ProcurementRequestResource;
use App\Modules\Procurement\Actions\ApproveProcurementRequestAction;
use App\Modules\Procurement\Enums\ProcurementWorkflowStage;
use App\Modules\Procurement\Models\ProcurementRequest;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewProcurementRequest extends ViewRecord
{
    protected static string $resource = ProcurementRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve_to_invoice')
                ->label('Approve to Invoice')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn (): bool => $this->canApprove($this->record))
                ->requiresConfirmation()
                ->action(function (): void {
                    app(ApproveProcurementRequestAction::class)->execute($this->record);

                    $this->record->refresh();

                    Notification::make()
                        ->title('Procurement approved')
                        ->body(($this->record->request_number ?? 'Procurement request').' was approved and moved into invoice flow.')
                        ->success()
                        ->send();
                }),
        ];
    }

    private function canApprove(ProcurementRequest $record): bool
    {
        if (! ($record->quoteOrder || $record->invoiceOrder)) {
            return false;
        }

        return ! in_array($record->current_stage, [
            ProcurementWorkflowStage::STOCK_RESERVED,
            ProcurementWorkflowStage::STOCK_DEDUCTED,
            ProcurementWorkflowStage::FULFILLED,
            ProcurementWorkflowStage::CANCELLED,
        ], true);
    }
}
