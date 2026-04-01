<?php

namespace App\Filament\Resources\ProcurementRequestResource\Pages;

use App\Filament\Resources\ProcurementRequestResource;
use App\Modules\Accounts\Support\CurrentAccountResolver;
use App\Modules\Procurement\Actions\ApproveProcurementRequestAction;
use App\Modules\Procurement\Enums\ProcurementWorkflowStage;
use App\Modules\Procurement\Models\ProcurementRequest;
use App\Modules\Procurement\Support\ProcurementQuoteLifecycle;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewProcurementRequest extends ViewRecord
{
    protected static string $resource = ProcurementRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('open_quote')
                ->label('Open Quote')
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->visible(fn (): bool => $this->record->quoteOrder !== null)
                ->url(fn (): ?string => $this->record->quoteOrder
                    ? route('filament.admin.resources.quotes.view', ['record' => $this->record->quoteOrder])
                    : null),

            Action::make('open_invoice')
                ->label('Open Invoice')
                ->icon('heroicon-o-document-currency-dollar')
                ->color('gray')
                ->visible(fn (): bool => $this->record->invoiceOrder !== null)
                ->url(fn (): ?string => $this->record->invoiceOrder
                    ? route('filament.admin.resources.invoices.view', ['record' => $this->record->invoiceOrder])
                    : null),

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
            Action::make('request_revision')
                ->label('Request Revision')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(fn (): bool => $this->canRequestRevision($this->record))
                ->form([
                    \Filament\Forms\Components\Textarea::make('note')
                        ->label('Revision Note')
                        ->required()
                        ->rows(4),
                ])
                ->action(function (array $data): void {
                    if (! $this->record->quoteOrder) {
                        return;
                    }

                    app(ProcurementQuoteLifecycle::class)->requestRevision($this->record->quoteOrder, $data['note']);
                    $this->record->refresh();

                    Notification::make()
                        ->title('Revision requested')
                        ->body(($this->record->request_number ?? 'Procurement request').' was returned to supplier review.')
                        ->success()
                        ->send();
                }),
            Action::make('reject_procurement')
                ->label("Reject / Can't Supply")
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (): bool => $this->canReject($this->record))
                ->form([
                    \Filament\Forms\Components\Textarea::make('reason')
                        ->label('Rejection Reason')
                        ->required()
                        ->rows(4),
                ])
                ->requiresConfirmation()
                ->action(function (array $data): void {
                    if (! $this->record->quoteOrder) {
                        return;
                    }

                    app(ProcurementQuoteLifecycle::class)->reject($this->record->quoteOrder, $data['reason']);
                    $this->record->refresh();

                    Notification::make()
                        ->title('Procurement rejected')
                        ->body(($this->record->request_number ?? 'Procurement request').' was cancelled for the supplier.')
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

        if (! $this->isActiveSupplierForRequest($record)) {
            return false;
        }

        return in_array($record->current_stage, [
            ProcurementWorkflowStage::QUOTED,
            ProcurementWorkflowStage::APPROVED,
        ], true);
    }

    private function canReject(ProcurementRequest $record): bool
    {
        if (! $record->quoteOrder || $record->invoiceOrder) {
            return false;
        }

        if (! $this->isActiveSupplierForRequest($record)) {
            return false;
        }

        return in_array($record->current_stage, [
            ProcurementWorkflowStage::DRAFT,
            ProcurementWorkflowStage::SUBMITTED,
            ProcurementWorkflowStage::SUPPLIER_REVIEW,
            ProcurementWorkflowStage::QUOTED,
        ], true);
    }

    private function canRequestRevision(ProcurementRequest $record): bool
    {
        if (! $record->quoteOrder || $record->invoiceOrder) {
            return false;
        }

        if (! $this->isActiveSupplierForRequest($record)) {
            return false;
        }

        return in_array($record->current_stage, [
            ProcurementWorkflowStage::SUPPLIER_REVIEW,
            ProcurementWorkflowStage::QUOTED,
        ], true);
    }

    private function isActiveSupplierForRequest(ProcurementRequest $request): bool
    {
        $user = auth()->user();

        if (! $user || $user->hasRole('super_admin')) {
            return false;
        }

        $currentAccount = app(CurrentAccountResolver::class)
            ->resolve(request(), $user)
            ->currentAccount;

        return $currentAccount !== null
            && (int) $request->supplier_account_id === (int) $currentAccount->id;
    }
}
