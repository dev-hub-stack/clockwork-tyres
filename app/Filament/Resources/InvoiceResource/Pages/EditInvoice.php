<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInvoice extends EditRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('syncToWafeq')
                ->label('Send to Wafeq')
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->action(function () {
                    // Placeholder for Wafeq integration
                    \Filament\Notifications\Notification::make()
                        ->title('Wafeq Sync')
                        ->body('This feature is coming soon.')
                        ->info()
                        ->send();
                }),
        ];
    }
    
    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Load payment status from database (it's auto-calculated)
        return $data;
    }
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
