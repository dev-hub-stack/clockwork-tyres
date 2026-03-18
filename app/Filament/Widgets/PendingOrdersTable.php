<?php

namespace App\Filament\Widgets;

use App\Modules\Orders\Models\Order;
use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PendingOrdersTable extends BaseWidget
{
    protected static ?int $sort = 2;
    
    protected int | string | array $columnSpan = 'full';
    
    protected static ?string $heading = 'Order Sheet';
    
    public function table(Table $table): Table
    {
        return $table
            ->query(
                Order::query()
                    ->with(['customer', 'orderItems.productVariant.product.brand'])
                    // Include shipped and delivered orders - they should remain visible until completed
                    ->whereIn('order_status', ['pending', 'processing', 'shipped', 'delivered'])
                    ->orderBy('created_at', 'desc')
            )
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->date('n/j/y')
                    ->sortable()
                    ->size('sm'),
                    
                Tables\Columns\TextColumn::make('order_number')
                    ->label('Order #')
                    ->searchable()
                    ->weight('bold')
                    ->size('sm'),
                    
                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable()
                    ->size('sm'),
                    
                Tables\Columns\TextColumn::make('wheel_brand')
                    ->label('Wheel Brand')
                    ->size('sm')
                    ->getStateUsing(function (Order $record) {
                        $firstItem = $record->orderItems->first();
                        if ($firstItem && $firstItem->productVariant) {
                            $variant = $firstItem->productVariant;
                            $brand = $variant->product->brand->name ?? '';
                            $model = $variant->product->model->name ?? '';
                            $finish = $variant->finish->name ?? '';
                            return trim("{$brand} - {$model} {$finish}");
                        }
                        return 'N/A';
                    }),
                    
                Tables\Columns\TextColumn::make('vehicle')
                    ->label('Vehicle')
                    ->size('sm')
                    ->getStateUsing(fn (Order $record) => 
                        $record->vehicle_year || $record->vehicle_make || $record->vehicle_model || $record->vehicle_sub_model
                            ? implode(' ', array_filter([
                                $record->vehicle_year,
                                $record->vehicle_make,
                                $record->vehicle_model,
                                $record->vehicle_sub_model,
                            ]))
                            : ''
                    ),
                    
                Tables\Columns\TextColumn::make('tracking_number')
                    ->label('Import Tracking')
                    ->size('sm')
                    ->weight('bold')
                    ->wrap()
                    ->copyable()
                    ->copyMessage('Tracking number copied')
                    ->getStateUsing(fn (Order $record) =>
                        $record->tracking_number ?: 'FEDEX TBD'
                    ),
                    
                Tables\Columns\TextColumn::make('payment_status')
                    ->label('Payment')
                    ->badge()
                    ->size('sm')
                    ->color(function (Order $record): string {
                        if ($record->payment_status === 'paid') {
                            return 'success';
                        }
                        if ($record->paid_amount > 0 && $record->paid_amount < $record->total) {
                            return 'warning';
                        }
                        return 'danger';
                    })
                    ->getStateUsing(function (Order $record) {
                        if ($record->payment_status === 'paid') {
                            return 'PAID';
                        }
                        if ($record->paid_amount > 0 && $record->paid_amount < $record->total) {
                            return 'PARTIAL PAYMENT';
                        }
                        return 'PENDING';
                    }),
            ])
            ->recordAction(null)
            ->actions([
                // Track Shipment
                Tables\Actions\Action::make('track_shipment')
                    ->label('Track')
                    ->icon('heroicon-o-truck')
                    ->color('success')
                    ->size('sm')
                    ->visible(fn (Order $record) => (bool) $record->tracking_url)
                    ->url(fn (Order $record) => $record->tracking_url)
                    ->openUrlInNewTab(),
                // Record Balance Payment
                Tables\Actions\Action::make('record_payment')
                    ->label('Record Balance Payment')
                    ->icon('heroicon-o-currency-dollar')
                    ->color('warning')
                    ->visible(fn (Order $record) => 
                        $record->payment_status !== 'paid' && 
                        ($record->total - $record->paid_amount) > 0
                    )
                    ->modalHeading('Record Balance Payment')
                    ->modalSubmitActionLabel('Record Payment')
                    ->form([
                        Forms\Components\TextInput::make('amount')
                            ->label('Payment Amount')
                            ->numeric()
                            ->prefix('$')
                            ->required()
                            ->default(fn (Order $record) => 
                                number_format($record->total - $record->paid_amount, 2, '.', '')
                            ),
                        Forms\Components\Select::make('payment_method')
                            ->label('Payment Method')
                            ->options([
                                'cash' => 'Cash',
                                'credit_card' => 'Credit Card',
                                'bank_transfer' => 'Bank Transfer',
                                'check' => 'Check',
                                'paypal' => 'PayPal',
                            ])
                            ->required(),
                        Forms\Components\DatePicker::make('payment_date')
                            ->label('Payment Date')
                            ->default(now())
                            ->required(),
                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->rows(2),
                    ])
                    ->action(function (Order $record, array $data) {
                        $amount = floatval($data['amount']);
                        $newAmountPaid = $record->paid_amount + $amount;
                        
                        $record->update([
                            'paid_amount' => $newAmountPaid,
                            'payment_status' => $newAmountPaid >= $record->total ? 'paid' : 'partial',
                        ]);
                        
                        // Create payment record if payments table exists
                        if (Schema::hasTable('payments')) {
                            DB::table('payments')->insert([
                                'order_id' => $record->id,
                                'amount' => $amount,
                                'payment_method' => $data['payment_method'],
                                'payment_date' => $data['payment_date'],
                                'notes' => $data['notes'] ?? null,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                        
                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Payment Recorded')
                            ->body("Payment of $" . number_format($amount, 2) . " recorded successfully.")
                            ->send();
                    }),
                    
                // Download Delivery Note
                Tables\Actions\Action::make('delivery_note')
                    ->label('Download Delivery Note')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('info')
                    ->url(fn (Order $record): string => route('orders.delivery-note.pdf', $record->id))
                    ->openUrlInNewTab(),
                    
                // Download Invoice
                Tables\Actions\Action::make('invoice')
                    ->label('Download Invoice')
                    ->icon('heroicon-o-document-text')
                    ->color('primary')
                    ->url(fn (Order $record): string => route('orders.invoice.pdf', $record->id))
                    ->openUrlInNewTab(),
                    
                // Mark Order as Delivered
                Tables\Actions\Action::make('mark_delivered')
                    ->label('Mark as Delivered')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Order $record) => in_array($record->order_status->value, ['pending', 'processing', 'shipped']))
                    ->requiresConfirmation()
                    ->modalHeading('Mark Order as Delivered')
                    ->modalDescription('Confirm that this order has been delivered to the customer. Payment can be updated separately.')
                    ->action(function (Order $record) {
                        $record->markAsDelivered();
                        
                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Order Delivered')
                            ->body("Order #{$record->order_number} has been marked as delivered.")
                            ->send();
                    }),
                
                // Mark Order as Completed (final step)
                Tables\Actions\Action::make('mark_completed')
                    ->label('Mark as Completed')
                    ->icon('heroicon-o-check-badge')
                    ->color('primary')
                    ->visible(fn (Order $record) => $record->order_status->value === 'delivered' && $record->payment_status->value === 'paid')
                    ->requiresConfirmation()
                    ->modalHeading('Mark Order as Completed')
                    ->modalDescription('This will complete the order. The order will be removed from the order sheet.')
                    ->action(function (Order $record) {
                        $record->markAsCompleted();
                        
                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Order Completed')
                            ->body("Order #{$record->order_number} has been marked as completed.")
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No Active Orders')
            ->emptyStateDescription('All orders have been completed or there are no active orders.')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->poll('30s') // Auto-refresh every 30 seconds
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->striped();
    }
}
