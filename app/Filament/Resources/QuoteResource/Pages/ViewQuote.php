<?php

namespace App\Filament\Resources\QuoteResource\Pages;

use App\Filament\Resources\QuoteResource;
use App\Modules\Orders\Enums\QuoteStatus;
use App\Modules\Orders\Services\QuoteConversionService;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewQuote extends ViewRecord
{
    protected static string $resource = QuoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('send')
                ->label('Send Quote')
                ->icon('heroicon-o-paper-airplane')
                ->color('primary')
                ->visible(fn () => $this->record->quote_status?->canSend() ?? false)
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->update([
                        'quote_status' => QuoteStatus::SENT,
                        'sent_at' => now(),
                    ]);
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
                ->visible(fn () => $this->record->canConvertToInvoice())
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
}
