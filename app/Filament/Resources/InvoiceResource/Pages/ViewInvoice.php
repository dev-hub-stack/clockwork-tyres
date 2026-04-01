<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use App\Filament\Resources\InvoiceResource\Actions\CancelOrderAction;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    /**
     * Get the title for the view page
     * Shows invoice number in the header for clarity
     */
    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return 'Invoice: ' . ($this->record->order_number ?? 'N/A');
    }

    /**
     * Get the subheading with customer info
     */
    public function getSubheading(): string|null
    {
        $customer = $this->record->customer;
        if ($customer) {
            return 'Customer: ' . ($customer->business_name ?? $customer->full_name ?? $customer->name);
        }
        return null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('open_procurement_request')
                ->label('Open Procurement Request')
                ->icon('heroicon-o-clipboard-document-list')
                ->color('gray')
                ->visible(fn () => $this->record->procurementInvoiceRequest !== null)
                ->url(fn (): ?string => $this->record->procurementInvoiceRequest
                    ? route('filament.admin.resources.procurement-requests.view', ['record' => $this->record->procurementInvoiceRequest])
                    : null),

            Actions\Action::make('send_invoice')
                ->label('Send Invoice')
                ->icon('heroicon-o-paper-airplane')
                ->color('primary')
                ->form([
                    \Filament\Forms\Components\TextInput::make('email')
                        ->label('Send To Email')
                        ->email()
                        ->required()
                        ->default(fn () => $this->record->customer?->email ?? ''),
                ])
                ->action(function (array $data) {
                    try {
                        $mailStatus = app(\App\Support\TransactionalCustomerMail::class)->send(
                            $data['email'],
                            new \App\Mail\InvoiceCreatedMail($this->record),
                            [
                                'trigger' => 'invoice.send',
                                'invoice_id' => $this->record->id,
                                'order_number' => $this->record->order_number,
                            ]
                        );
                        $note = $mailStatus === 'suppressed'
                            ? 'Email suppressed by system setting.'
                            : "Email sent to {$data['email']}.";
                        $success = true;
                    } catch (\Throwable $e) {
                        Log::warning('Failed to send invoice email', [
                            'invoice_id'  => $this->record->id,
                            'order_number'=> $this->record->order_number,
                            'error'       => $e->getMessage(),
                        ]);
                        $note = 'Email delivery failed: ' . $e->getMessage();
                        $success = false;
                    }

                    \Filament\Notifications\Notification::make()
                        ->{$success ? 'success' : 'danger'}()
                        ->title($success ? 'Invoice Sent' : 'Send Failed')
                        ->body("{$this->record->order_number} — {$note}")
                        ->send();
                }),

            Actions\Action::make('captureStripePayment')
                ->label('Capture Payment')
                ->icon('heroicon-o-credit-card')
                ->color('success')
                ->visible(fn () => $this->record->payments()
                    ->where('payment_method', 'stripe')
                    ->where('status', 'authorized')
                    ->exists())
                ->requiresConfirmation()
                ->modalHeading('Capture Stripe Payment')
                ->modalDescription("This will capture the authorized Stripe payment. The customer's card will be charged immediately.")
                ->action(function () {
                    try {
                        $result = app(\App\Services\Wholesale\StripePaymentLifecycleService::class)
                            ->captureAuthorizedPayment($this->record);

                        $message = match ($result['status']) {
                            'captured' => 'Payment captured successfully.',
                            'already_captured' => 'Payment was already captured.',
                            default => 'No authorized Stripe payment found for this order.',
                        };

                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Payment Captured')
                            ->body($message)
                            ->send();
                    } catch (\Throwable $e) {
                        \Filament\Notifications\Notification::make()
                            ->danger()
                            ->title('Capture Failed')
                            ->body($e->getMessage())
                            ->send();
                    }
                }),

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
                    \Filament\Forms\Components\TextInput::make('tracking_url')
                        ->label('Tracking Link')
                        ->url()
                        ->placeholder('https://track.carrier.com/...')
                        ->default(fn () => $this->record->tracking_url),
                ])
                ->action(function (array $data) {
                    try {
                        app(\App\Services\Wholesale\StripePaymentLifecycleService::class)
                            ->captureAuthorizedPayment($this->record);
                    } catch (\Throwable $e) {
                        \Filament\Notifications\Notification::make()
                            ->title('Payment Capture Failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                        return;
                    }

                    $this->record->update([
                        'order_status'     => OrderStatus::SHIPPED,
                        'tracking_number'  => $data['tracking_number'],
                        'shipping_carrier' => $data['shipping_carrier'],
                        'tracking_url'     => $data['tracking_url'] ?? null,
                        'shipped_at'       => now(),
                    ]);
                }),

            Actions\Action::make('markCompleted')
                ->label('Mark as Delivered')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn () => in_array($this->record->order_status->value, ['pending', 'processing', 'shipped']))
                ->requiresConfirmation()
                ->modalHeading('Mark as Delivered')
                ->modalDescription('Confirm that this order has been delivered to the customer.')
                ->action(function () {
                    $this->record->update([
                        'order_status' => OrderStatus::DELIVERED,
                    ]);
                }),

            CancelOrderAction::make(),

            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
