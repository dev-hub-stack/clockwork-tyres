<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Infolists\Components\Grid;

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

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Order Timeline')
                    ->schema([
                        ViewEntry::make('timeline')
                            ->view('filament.components.order-timeline')
                            ->columnSpanFull(),
                    ]),

                Section::make('Invoice Information')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('order_number')
                            ->label('Invoice #'),
                        TextEntry::make('customer.name')
                            ->label('Customer'),
                        TextEntry::make('issue_date')
                            ->label('Issue Date')
                            ->date(),
                        TextEntry::make('valid_until')
                            ->label('Due Date')
                            ->date(),
                        TextEntry::make('order_status')
                            ->badge(),
                        TextEntry::make('payment_status')
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
