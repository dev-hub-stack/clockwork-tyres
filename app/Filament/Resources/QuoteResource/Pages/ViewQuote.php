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
                ->form([
                    \Filament\Forms\Components\TextInput::make('email')
                        ->label('Send To Email')
                        ->email()
                        ->required()
                        ->default(fn () => $this->record->customer?->email ?? ''),
                ])
                ->action(function (array $data) {
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
