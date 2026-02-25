<?php

namespace App\Filament\Resources\ConsignmentResource\Actions;

use App\Modules\Consignments\Models\Consignment;
use App\Modules\Consignments\Services\ConsignmentService;
use App\Modules\Inventory\Models\Warehouse;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class CancelConsignmentAction
{
    public static function make(): Action
    {
        return Action::make('cancel_consignment')
            ->label('Cancel Items')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (Consignment $record) => 
                in_array($record->status->value, ['draft', 'sent']) && 
                $record->items_sold_count === 0
            )
            ->requiresConfirmation()
            ->modalHeading(fn (Consignment $record) => 'Cancel Items from Consignment #' . $record->consignment_number)
            ->modalDescription('Select items to cancel and specify return conditions. Inventory will be updated for good items. Remaining items will stay in consignment.')
            ->modalWidth('6xl')
            ->modalIcon('heroicon-o-exclamation-triangle')
            ->form(function (Consignment $record) {
                // Get items that can be cancelled (not sold, not already returned)
                $allItems = $record->items()
                    ->get()
                    ->map(function ($item) {
                        $canCancel = $item->quantity_sent - ($item->quantity_sold ?? 0) - ($item->quantity_returned ?? 0);
                        $item->quantity_cancellable = $canCancel;
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
                                        ->content($record->customer->business_name ?? $record->customer->full_name),
                                    
                                    Placeholder::make('customer_email')
                                        ->label('Email')
                                        ->content($record->customer->email),
                                    
                                    Placeholder::make('customer_phone')
                                        ->label('Phone')
                                        ->content($record->customer->phone ?? 'N/A'),
                                ]),
                        ]),
                    
                    // Items to Cancel Section
                    Section::make('Items to Cancel')
                        ->description($cancellableItems->isEmpty() 
                            ? '⚠️ No items available to cancel. All items have been sold or returned.' 
                            : 'Select items and specify quantities to cancel. Good items will be returned to selected warehouse.')
                        ->schema([
                            Repeater::make('cancelled_items')
                                ->label('')
                                ->defaultItems(1)
                                ->collapsed(false)
                                ->addable($cancellableItems->count() > 1)
                                ->schema([
                                    Select::make('item_id')
                                        ->label('Item')
                                        ->options($cancellableItems->mapWithKeys(function ($item) {
                                            return [
                                                $item->id => "{$item->product_name} - SKU: {$item->sku} (Can cancel: {$item->quantity_cancellable})"
                                            ];
                                        }))
                                        ->required()
                                        ->reactive()
                                        ->afterStateUpdated(function ($state, callable $set) use ($cancellableItems) {
                                            $item = $cancellableItems->firstWhere('id', $state);
                                            if ($item) {
                                                $set('max_quantity', $item->quantity_cancellable);
                                                $set('quantity', 1);
                                            }
                                        })
                                        ->searchable()
                                        ->columnSpan(2),
                                    
                                    TextInput::make('quantity')
                                        ->label('Quantity to Cancel')
                                        ->numeric()
                                        ->required()
                                        ->minValue(1)
                                        ->maxValue(fn (callable $get) => $get('max_quantity') ?? 999)
                                        ->default(1)
                                        ->live(onBlur: true)
                                        ->rule('integer')
                                        ->rule('min:1')
                                        ->rule(function (callable $get) {
                                            return function ($attribute, $value, $fail) use ($get) {
                                                $maxQty = $get('max_quantity');
                                                if ($maxQty && $value > $maxQty) {
                                                    $fail("Cannot cancel more than {$maxQty} units (available quantity).");
                                                }
                                            };
                                        })
                                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                            // Auto-correct if exceeds max
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
                                        ->helperText('Select warehouse to receive cancelled items')
                                        ->columnSpan(2),
                                    
                                    Select::make('condition')
                                        ->label('Item Condition')
                                        ->options([
                                            'good' => '✅ Good (Add to inventory)',
                                            'damaged' => '⚠️ Damaged',
                                            'defective' => '❌ Defective',
                                        ])
                                        ->default('good')
                                        ->required()
                                        ->helperText('Only "Good" items will be added back to inventory')
                                        ->columnSpan(1),
                                    
                                    // Hidden field for tracking
                                    TextInput::make('max_quantity')->hidden()->default(0),
                                ])
                                ->columns(6)
                                ->addActionLabel('Add Item to Cancel')
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
                                    'not_sold' => 'Items Not Sold',
                                    'damaged' => 'Damaged/Defective',
                                    'wrong_item' => 'Wrong Item',
                                    'end_of_period' => 'End of Consignment Period',
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
            ->action(function (Consignment $record, array $data, ConsignmentService $service) {
                \Log::info('CancelConsignmentAction called', [
                    'consignment_id' => $record->id,
                    'consignment_number' => $record->consignment_number,
                    'action' => 'cancel_consignment',
                    'cancelled_items_count' => count($data['cancelled_items'] ?? []),
                    'cancellation_reason' => $data['cancellation_reason'] ?? null,
                ]);
                
                try {
                    // Additional validation
                    if ($record->items_sold_count > 0) {
                        Notification::make()
                            ->warning()
                            ->title('Cannot Cancel')
                            ->body('Cannot cancel consignment with sold items. Please record returns first.')
                            ->send();
                        return;
                    }
                    
                    // Filter out items with missing data
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
                    
                    // Call service to cancel with items
                    $service->cancelConsignmentWithItems($record, $cancelledItems, $reason, $notes);
                    
                    // Count quantities
                    $totalQty = collect($cancelledItems)->sum('quantity');
                    $goodQty = collect($cancelledItems)
                        ->where('condition', 'good')
                        ->sum('quantity');
                    
                    // Determine if this was full or partial cancellation
                    $isFullCancellation = $record->fresh()->status->value === 'cancelled';
                    $message = $isFullCancellation 
                        ? "Consignment {$record->consignment_number} has been fully cancelled."
                        : "Items have been cancelled from consignment {$record->consignment_number}.";
                    
                    Notification::make()
                        ->success()
                        ->title('Items Cancelled')
                        ->body("{$message}<br>Cancelled {$totalQty} units. {$goodQty} units added back to inventory.")
                        ->send();
                    
                } catch (\Exception $e) {
                    Notification::make()
                        ->danger()
                        ->title('Error Cancelling Consignment')
                        ->body($e->getMessage())
                        ->send();
                    
                    throw $e;
                }
            })
            ->modalSubmitActionLabel('Cancel Selected Items')
            ->modalSubmitAction(fn ($action) => $action->color('danger'));
    }
}
