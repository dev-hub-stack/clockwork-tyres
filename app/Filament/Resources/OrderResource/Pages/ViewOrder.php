<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Modules\Orders\Services\QuoteConversionService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Convert Quote to Invoice Action (THE CRITICAL ONE!)
            Actions\Action::make('convert_to_invoice')
                ->label('Convert to Invoice')
                ->icon('heroicon-o-arrow-right-circle')
                ->color('success')
                ->visible(fn ($record) => $record->canConvertToInvoice())
                ->requiresConfirmation()
                ->modalHeading('Convert Quote to Invoice')
                ->modalDescription(fn ($record) => "Are you sure you want to convert Quote #{$record->quote_number} to an invoice? This action cannot be undone.")
                ->modalSubmitActionLabel('Convert to Invoice')
                ->action(function ($record) {
                    try {
                        $conversionService = app(QuoteConversionService::class);
                        $invoice = $conversionService->convertQuoteToInvoice($record);
                        
                        Notification::make()
                            ->title('Quote Converted Successfully!')
                            ->body("Quote #{$record->quote_number} has been converted to invoice #{$invoice->order_number}")
                            ->success()
                            ->send();
                        
                        // Refresh the page to show invoice view
                        return redirect()->route('filament.admin.resources.orders.view', ['record' => $invoice->id]);
                        
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Conversion Failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
