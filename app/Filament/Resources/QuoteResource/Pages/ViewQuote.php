<?php

namespace App\Filament\Resources\QuoteResource\Pages;

use App\Filament\Resources\QuoteResource;
use App\Modules\Accounts\Support\CurrentAccountResolver;
use App\Modules\Orders\Enums\QuoteStatus;
use App\Modules\Orders\Services\QuoteConversionService;
use App\Modules\Procurement\Actions\ApproveProcurementRequestAction;
use App\Modules\Procurement\Enums\ProcurementWorkflowStage;
use App\Modules\Procurement\Models\ProcurementRequest;
use App\Modules\Procurement\Support\ProcurementQuoteLifecycle;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewQuote extends ViewRecord
{
    protected static string $resource = QuoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('open_procurement_request')
                ->label('Open Procurement Request')
                ->icon('heroicon-o-clipboard-document-list')
                ->color('gray')
                ->visible(fn () => $this->record->procurementQuoteRequest !== null)
                ->url(fn (): ?string => $this->record->procurementQuoteRequest
                    ? route('filament.admin.resources.procurement-requests.view', ['record' => $this->record->procurementQuoteRequest])
                    : null),

            Actions\Action::make('approve_procurement')
                ->label('Approve Procurement')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn (): bool => $this->canApproveProcurement())
                ->requiresConfirmation()
                ->modalHeading('Approve Procurement Quote')
                ->modalDescription('Approve the linked procurement request and convert this quote into invoice flow using the CRM workflow.')
                ->action(function () {
                    $procurementRequest = $this->record->procurementQuoteRequest;

                    if (! $procurementRequest instanceof ProcurementRequest) {
                        return;
                    }

                    $approvedRequest = app(ApproveProcurementRequestAction::class)->execute($procurementRequest);

                    \Filament\Notifications\Notification::make()
                        ->title('Procurement approved')
                        ->body(($approvedRequest->request_number ?? 'Procurement request').' moved into invoice flow.')
                        ->success()
                        ->send();

                    if ($approvedRequest->invoiceOrder) {
                        return redirect()->route('filament.admin.resources.invoices.view', ['record' => $approvedRequest->invoiceOrder]);
                    }
                }),

            Actions\Action::make('start_supplier_review')
                ->label('Start Supplier Review')
                ->icon('heroicon-o-eye')
                ->color('warning')
                ->visible(fn (): bool => $this->canStartSupplierReview())
                ->action(function () {
                    $request = app(ProcurementQuoteLifecycle::class)->startSupplierReview($this->record);

                    if (! $request instanceof ProcurementRequest) {
                        return;
                    }

                    \Filament\Notifications\Notification::make()
                        ->title('Supplier review started')
                        ->body(($request->request_number ?? 'Procurement request').' moved into supplier review.')
                        ->success()
                        ->send();
                }),

            Actions\Action::make('send')
                ->label('Send Quote')
                ->icon('heroicon-o-paper-airplane')
                ->color('primary')
                ->visible(fn () => $this->record->quote_status?->canSend() ?? false)
                ->form([
                    \Filament\Forms\Components\TextInput::make('email')
                        ->label('Send To Email')
                        ->email()
                        ->required()
                        ->default(fn () => $this->record->customer?->email ?? ''),
                ])
                ->action(function (array $data) {
                    app(ProcurementQuoteLifecycle::class)->markQuoted($this->record);

                    $this->record->update([
                        'quote_status' => QuoteStatus::SENT,
                        'sent_at' => now(),
                    ]);
                    
                    try {
                        $mailStatus = app(\App\Support\TransactionalCustomerMail::class)->send(
                            $data['email'],
                            new \App\Mail\QuoteSentMail($this->record),
                            [
                                'trigger' => 'quote.send',
                                'quote_id' => $this->record->id,
                                'quote_number' => $this->record->quote_number,
                            ]
                        );
                        $note = $mailStatus === 'suppressed'
                            ? 'Email suppressed by system setting.'
                            : "Email sent to {$data['email']}.";
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::warning('Failed to send quote email', [
                            'quote_id' => $this->record->id,
                            'error' => $e->getMessage(),
                        ]);
                        $note = '(Email delivery failed — check mail settings.)';
                    }
                    
                    \Filament\Notifications\Notification::make()
                        ->success()
                        ->title('Quote Sent')
                        ->body("{$this->record->quote_number} marked as sent. {$note}")
                        ->send();
                })
                ->successNotificationTitle('Quote sent successfully!'),

            Actions\Action::make('mark_as_sent')
                ->label('Mark as Sent')
                ->icon('heroicon-o-check')
                ->color('gray')
                ->visible(fn () => $this->record->quote_status === QuoteStatus::DRAFT)
                ->requiresConfirmation()
                ->modalHeading('Mark Quote as Sent')
                ->modalDescription('This will mark the quote as sent without sending an email. Use this if you shared the quote manually or via another channel.')
                ->modalSubmitActionLabel('Yes, Mark as Sent')
                ->action(function () {
                    app(ProcurementQuoteLifecycle::class)->markQuoted($this->record);

                    $this->record->update([
                        'quote_status' => QuoteStatus::SENT,
                        'sent_at' => now(),
                    ]);

                    \Filament\Notifications\Notification::make()
                        ->title('Quote Marked as Sent')
                        ->body("Quote {$this->record->quote_number} has been marked as sent.")
                        ->success()
                        ->send();
                }),

            Actions\Action::make('convert')
                ->label('Convert to Invoice')
                ->icon('heroicon-o-arrow-right-circle')
                ->color('success')
                ->visible(fn () => $this->record->canConvertToInvoice() && $this->record->procurementQuoteRequest === null)
                ->requiresConfirmation()
                ->modalHeading('Convert Quote to Invoice')
                ->modalDescription('This will convert the quote to an invoice. This action cannot be undone.')
                ->action(function () {
                    $conversionService = app(QuoteConversionService::class);
                    $invoice = $conversionService->convertQuoteToInvoice($this->record);
                    
                    return redirect()->route('filament.admin.resources.invoices.view', ['record' => $invoice]);
                })
                ->successNotificationTitle('Quote converted to invoice!'),

            Actions\EditAction::make()
                ->visible(fn () => $this->record->quote_status?->canEdit() ?? false),
                
            Actions\DeleteAction::make()
                ->visible(fn () => $this->record->quote_status === QuoteStatus::DRAFT),
        ];
    }

    private function canApproveProcurement(): bool
    {
        if (! (auth()->user()?->can('edit_quotes') ?? false)) {
            return false;
        }

        $request = $this->record->procurementQuoteRequest;

        if (! $request instanceof ProcurementRequest) {
            return false;
        }

        if (! $this->isActiveSupplierForRequest($request)) {
            return false;
        }

        return in_array($request->current_stage, [
            ProcurementWorkflowStage::QUOTED,
            ProcurementWorkflowStage::APPROVED,
        ], true);
    }

    private function canStartSupplierReview(): bool
    {
        if (! (auth()->user()?->can('edit_quotes') ?? false)) {
            return false;
        }

        $request = $this->record->procurementQuoteRequest;

        if (! $request instanceof ProcurementRequest) {
            return false;
        }

        if (! $this->isActiveSupplierForRequest($request)) {
            return false;
        }

        return in_array($request->current_stage, [
            ProcurementWorkflowStage::DRAFT,
            ProcurementWorkflowStage::SUBMITTED,
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
