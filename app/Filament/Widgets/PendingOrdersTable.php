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
                    // Include shipped orders - they should remain visible until completed
                    ->whereIn('order_status', ['pending', 'processing', 'shipped'])
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
                        $record->vehicle_year && $record->vehicle_make 
                            ? "{$record->vehicle_year} {$record->vehicle_make} {$record->vehicle_model}"
                            : ''
                    ),
                    
                Tables\Columns\TextColumn::make('tracking_number')
                    ->label('Import Tracking')
                    ->badge()
                    ->color(fn (Order $record) => $record->tracking_url ? 'success' : 'info')
                    ->size('sm')
                    ->default('PENDING')
                    ->wrap()
                    ->getStateUsing(fn (Order $record) => 
                        $record->tracking_number ?: 'FEDEX TBD'
                    )
                    ->url(fn (Order $record) => $record->tracking_url ?? null)
                    ->openUrlInNewTab(),
                    
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
            ->actions([
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
                    
                // Mark Order as Done
                Tables\Actions\Action::make('mark_done')
                    ->label('Mark Order as Done')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Order $record) => $record->payment_status === 'paid')
                    ->requiresConfirmation()
                    ->modalHeading('Mark Order as Complete')
                    ->modalDescription('Are you sure you want to mark this order as complete? This cannot be undone.')
                    ->action(function (Order $record) {
                        $record->update([
                            'order_status' => 'completed',
                            'completed_at' => now(),
                        ]);
                        
                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Order Completed')
                            ->body("Order #{$record->order_number} has been marked as complete.")
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No Pending Orders')
            ->emptyStateDescription('All orders have been completed or there are no pending orders.')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->poll('30s') // Auto-refresh every 30 seconds
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->striped();
    }
}
