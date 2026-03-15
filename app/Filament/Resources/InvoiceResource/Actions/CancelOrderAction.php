<?php

namespace App\Filament\Resources\InvoiceResource\Actions;

use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Services\OrderFulfillmentService;
use App\Modules\Inventory\Models\Warehouse;
use Filament\Actions\Action;
use Filament\Actions\Action as TableAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class CancelOrderAction
{
    public static function make(): TableAction
    {
        return TableAction::make('cancel_order')
            ->label('Cancel Order')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (Order $record) => 
                !in_array($record->order_status->value, ['cancelled', 'completed']) && $record->document_type->value === 'invoice'
            )
            ->requiresConfirmation()
            ->modalHeading(fn (Order $record) => 'Cancel Order #' . $record->order_number)
            ->modalDescription('Select items to cancel and specify return conditions. Inventory will be updated for good items.')
            ->modalWidth('6xl')
            ->modalIcon('heroicon-o-exclamation-triangle')
            ->form(function (Order $record) {
                // Get all items in the order
                $allItems = $record->items()
                    ->get()
                    ->map(function ($item) {
                        $item->quantity_cancellable = max(0, $item->quantity);
                        return $item;
                    });

                // Filter items that can be cancelled
                $cancellableItems = $allItems->filter(fn ($item) => $item->quantity_cancellable > 0);

                // Get active warehouses
                $warehouses = Warehouse::where('status', 1)
                    ->orderBy('warehouse_name')
                    ->get()
                    ->mapWithKeys(fn ($wh) => [$wh->id => $wh->warehouse_name . ' (' . $wh->code . ')']);

                return [
                    // Customer Information Section
                    Section::make('Customer Information')
                        ->schema([
                            Grid::make(3)
                                ->schema([
                                    Placeholder::make('customer_name')
                                        ->label('Customer')
                                        ->content($record->customer->business_name ?? $record->customer->full_name ?? 'N/A'),
                                    
                                    Placeholder::make('customer_email')
                                        ->label('Email')
                                        ->content($record->customer->email ?? 'N/A'),
                                    
                                    Placeholder::make('customer_phone')
                                        ->label('Phone')
                                        ->content($record->customer->phone ?? 'N/A'),
                                ]),
                        ]),
                    
                    // Items to Cancel Section
                    Section::make('Items to Refund/Cancel & Return Inventory')
                        ->description($cancellableItems->isEmpty() 
                            ? '⚠️ No items available to cancel.' 
                            : 'Select items to cancel. "Good" condition items will be added back into the target inventory warehouse.')
                        ->schema([
                            Repeater::make('cancelled_items')
                                ->label('')
                                ->defaultItems($cancellableItems->count())
                                ->collapsed(false)
                                ->addable(false)
                                ->deletable(false)
                                ->schema([
                                    Select::make('item_id')
                                        ->label('Item')
                                        ->options($cancellableItems->mapWithKeys(function ($item) {
                                            $name = $item->product_name ?? 'Unknown item';
                                            return [
                                                $item->id => "{$name} - SKU: {$item->sku} (Qty: {$item->quantity_cancellable})"
                                            ];
                                        }))
                                        ->required()
                                        ->reactive()
                                        ->afterStateUpdated(function ($state, callable $set) use ($cancellableItems) {
                                            $item = $cancellableItems->firstWhere('id', $state);
                                            if ($item) {
                                                $set('max_quantity', (int) $item->quantity_cancellable);
                                                $set('quantity', (int) $item->quantity_cancellable);
                                            }
                                        })
                                        ->searchable()
                                        ->columnSpan(2),
                                    
                                    TextInput::make('quantity')
                                        ->label('Qty to Return')
                                        ->numeric()
                                        ->required()
                                        ->minValue(1)
                                        ->maxValue(fn (callable $get) => $get('max_quantity') ?? 999)
                                        ->live(onBlur: true)
                                        ->rule('integer')
                                        ->rule('min:1')
                                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                            $maxQty = $get('max_quantity');
                                            if ($maxQty && $state > $maxQty) {
                                                $set('quantity', $maxQty);
                                            }
                                        })
                                        ->helperText(fn (callable $get) => 
                                            'Max cancellable: ' . ($get('max_quantity') ?? 'N/A')
                                        )
                                        ->columnSpan(1),
                                    
                                    Select::make('warehouse_id')
                                        ->label('Return to Warehouse')
                                        ->options($warehouses)
                                        ->required()
                                        ->searchable()
                                        ->helperText('Select warehouse')
                                        ->columnSpan(2),
                                    
                                    Select::make('condition')
                                        ->label('Condition')
                                        ->options([
                                            'good' => '✅ Good',
                                            'damaged' => '⚠️ Damaged',
                                            'defective' => '❌ Defective',
                                        ])
                                        ->default('good')
                                        ->required()
                                        ->helperText('Only "Good" is added back to stock')
                                        ->columnSpan(1),
                                    
                                    // Hidden field for tracking
                                    TextInput::make('max_quantity')->hidden()->default(0),
                                ])
                                ->columns(6)
                                ->reorderable(false)
                                ->columnSpanFull(),
                        ]),
                    
                    // Cancellation Details Section
                    Section::make('Cancellation Details')
                        ->schema([
                            Select::make('cancellation_reason')
                                ->label('Cancellation Reason')
                                ->options([
                                    'customer_request' => 'Customer Request',
                                    'exchange' => 'Exchange / Wrong Item',
                                    'damaged' => 'Arrived Damaged/Defective',
                                    'other' => 'Other',
                                ])
                                ->helperText('Select the reason for cancellation')
                                ->columnSpan(1),
                            
                            Textarea::make('cancellation_notes')
                                ->label('Cancellation Notes (Optional)')
                                ->rows(3)
                                ->placeholder('Add any notes about this cancellation...')
                                ->columnSpanFull(),
                        ])
                        ->columns(1),
                ];
            })
            ->fillForm(function (Order $record) {
                // Pre-fill the repeater with all items
                $items = $record->items;
                $cancelledItems = tap(collect(), function ($cItems) use ($items) {
                    foreach ($items as $item) {
                        $qty = collect($item->quantities)->first();
                        $whId = $qty ? $qty->warehouse_id : null;
                        
                        $cItems->push([
                            'item_id' => $item->id,
                            'quantity' => (int) $item->quantity,
                            'max_quantity' => (int) $item->quantity,
                            'warehouse_id' => $whId,
                            'condition' => 'good',
                        ]);
                    }
                })->toArray();
                
                return [
                    'cancelled_items' => $cancelledItems,
                ];
            })
            ->action(function (Order $record, array $data, OrderFulfillmentService $service) {
                try {
                    $cancelledItems = collect($data['cancelled_items'] ?? [])
                        ->filter(fn ($item) => !empty($item['item_id']) && ($item['quantity'] ?? 0) > 0)
                        ->map(function ($item) {
                            return [
                                'item_id' => $item['item_id'],
                                'quantity' => $item['quantity'],
                                'warehouse_id' => $item['warehouse_id'],
                                'condition' => $item['condition'] ?? 'good',
                            ];
                        })
                        ->toArray();

                    if (empty($cancelledItems)) {
                        Notification::make()
                            ->warning()
                            ->title('No Items to Cancel')
                            ->body('Please add at least one item with a quantity greater than 0.')
                            ->send();
                        return;
                    }
                    
                    $reason = $data['cancellation_reason'] ?? 'No reason provided';
                    $notes = $data['cancellation_notes'] ?? null;
                    
                    $service->cancelOrderWithReturns($record, $cancelledItems, $reason, $notes);
                    
                    $totalQty = collect($cancelledItems)->sum('quantity');
                    $goodQty = collect($cancelledItems)->where('condition', 'good')->sum('quantity');
                    
                    Notification::make()
                        ->success()
                        ->title('Order Cancelled')
                        ->body("Cancelled {$totalQty} units. {$goodQty} units restored to inventory.")
                        ->send();
                    
                } catch (\Exception $e) {
                    Notification::make()
                        ->danger()
                        ->title('Error Cancelling Order')
                        ->body($e->getMessage())
                        ->send();
                    
                    throw $e;
                }
            })
            ->modalSubmitActionLabel('Confirm Cancellation & Returns');
    }
}
