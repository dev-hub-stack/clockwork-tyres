<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('startProcessing')
                ->label('Start Processing')
                ->icon('heroicon-o-cog-6-tooth')
                ->color('primary')
                ->visible(fn () => $this->record->order_status->value === 'pending')
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->update([
                        'order_status' => OrderStatus::PROCESSING,
                    ]);
                }),

            Actions\Action::make('markShipped')
                ->label('Mark as Shipped')
                ->icon('heroicon-o-truck')
                ->color('primary')
                ->visible(fn () => $this->record->order_status->value === 'processing')
                ->form([
                    \Filament\Forms\Components\TextInput::make('tracking_number')
                        ->label('Tracking Number')
                        ->required(),
                    \Filament\Forms\Components\TextInput::make('shipping_carrier')
                        ->label('Shipping Carrier')
                        ->required(),
                ])
                ->action(function (array $data) {
                    $this->record->update([
                        'order_status' => OrderStatus::SHIPPED,
                        'tracking_number' => $data['tracking_number'],
                        'shipping_carrier' => $data['shipping_carrier'],
                        'shipped_at' => now(),
                    ]);
                }),

            Actions\Action::make('markCompleted')
                ->label('Complete Order')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn () => $this->record->order_status->value === 'shipped')
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->update([
                        'order_status' => OrderStatus::COMPLETED,
                    ]);
                }),

            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
