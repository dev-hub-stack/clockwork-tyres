<?php

namespace App\Filament\Resources\QuoteResource\Pages;

use App\Filament\Resources\QuoteResource;
use App\Modules\Orders\Enums\QuoteStatus;
use App\Modules\Orders\Services\QuoteConversionService;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;

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

            Actions\Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn () => $this->record->quote_status === QuoteStatus::SENT)
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->update([
                        'quote_status' => QuoteStatus::APPROVED,
                        'approved_at' => now(),
                    ]);
                })
                ->successNotificationTitle('Quote approved!'),

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

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Quote Timeline')
                    ->schema([
                        ViewEntry::make('timeline')
                            ->view('filament.components.order-timeline')
                            ->columnSpanFull(),
                    ]),

                Section::make('Quote Information')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('quote_number')
                            ->label('Quote #'),
                        TextEntry::make('customer.name')
                            ->label('Customer'),
                        TextEntry::make('issue_date')
                            ->label('Issue Date')
                            ->date(),
                        TextEntry::make('valid_until')
                            ->label('Valid Until')
                            ->date(),
                        TextEntry::make('quote_status')
                            ->badge(),
                    ]),

                Section::make('Amounts')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('sub_total')
                            ->money('AED'),
                        TextEntry::make('vat')
                            ->money('AED'),
                        TextEntry::make('total')
                            ->money('AED')
                            ->weight('bold'),
                    ]),
            ]);
    }
}
