<?php

namespace App\Filament\Resources\ConsignmentResource\Actions;

use App\Modules\Consignments\Models\Consignment;
use App\Modules\Consignments\Services\ConsignmentReturnService;
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

class RecordReturnAction
{
    public static function make(): Action
    {
        return Action::make('record_return')
            ->label('Record Return')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('info')
            ->modalHeading(fn (Consignment $record) => 'Record Return - Consignment #' . $record->consignment_number)
            ->modalDescription('Select items that were returned to warehouse. Inventory will be updated.')
            ->modalWidth('6xl')
            ->visible(fn (Consignment $record) => $record->canRecordReturn())
            ->form(function (Consignment $record) {
                // Get ALL items with calculated returnable quantities
                $allItems = $record->items()
                    ->get()
                    ->map(function ($item) {
                        // Can only return items that are still with customer (not sold, not already returned)
                        $canReturn = $item->quantity_sent - ($item->quantity_sold ?? 0) - ($item->quantity_returned ?? 0);
                        $item->quantity_returnable = $canReturn;
                        return $item;
                    });

                // Filter items that can be returned (have returnable quantity > 0)
                $returnableItems = $allItems->filter(fn ($item) => $item->quantity_returnable > 0);

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
                    
                    // Items to Return Section - Show ALL items sent
                    Section::make('Items to Return')
                        ->description($returnableItems->isEmpty() 
                            ? '⚠️ No items available to return. All items have been returned.' 
                            : 'Select items and specify quantities to return to warehouse')
                        ->schema([
                            Repeater::make('returned_items')
                                ->label('')
                                ->defaultItems(1)
                                ->collapsed(false)
                                ->addable($returnableItems->count() > 1)
                                ->schema([
                                    Select::make('item_id')
                                        ->label('Item')
                                        ->options($returnableItems->mapWithKeys(function ($item) {
                                            return [
                                                $item->id => "{$item->product_name} - SKU: {$item->sku} (Can return: {$item->quantity_returnable})"
                                            ];
                                        }))
                                        ->required()
                                        ->reactive()
                                        ->afterStateUpdated(function ($state, callable $set) use ($returnableItems) {
                                            $item = $returnableItems->firstWhere('id', $state);
                                            if ($item) {
                                                $set('max_quantity', $item->quantity_returnable);
                                                $set('quantity', 1);
                                            }
                                        })
                                        ->searchable()
                                        ->columnSpan(2),
                                    
                                    TextInput::make('quantity')
                                        ->label('Quantity to Return')
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
                                                    $fail("Cannot return more than {$maxQty} units (returnable quantity).");
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
                                            'Max returnable: ' . ($get('max_quantity') ?? 'N/A')
                                        )
                                        ->columnSpan(1),
                                    
                                    Select::make('warehouse_id')
                                        ->label('Return to Warehouse')
                                        ->options($warehouses)
                                        ->required()
                                        ->searchable()
                                        ->helperText('Select warehouse to receive returned items')
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
                                ->addActionLabel('Add Item to Return')
                                ->reorderable(false)
                                ->columnSpanFull(),
                        ]),
                    
                    // Return Details Section
                    Section::make('Return Details')
                        ->schema([
                            Select::make('return_reason')
                                ->label('Return Reason')
                                ->options([
                                    'customer_request' => 'Customer Request',
                                    'not_sold' => 'Items Not Sold',
                                    'damaged' => 'Damaged/Defective',
                                    'wrong_item' => 'Wrong Item',
                                    'end_of_period' => 'End of Consignment Period',
                                    'other' => 'Other',
                                ])
                                ->helperText('Select the reason for return')
                                ->columnSpan(1),
                            
                            Textarea::make('return_notes')
                                ->label('Return Notes (Optional)')
                                ->rows(3)
                                ->placeholder('Add any notes about this return...')
                                ->columnSpanFull(),
                        ])
                        ->columns(1),
                ];
            })
            ->action(function (Consignment $record, array $data, ConsignmentReturnService $service) {
                \Log::info('RecordReturnAction called', [
                    'consignment_id' => $record->id,
                    'consignment_number' => $record->consignment_number,
                    'action' => 'record_return',
                    'returned_items_count' => count($data['returned_items'] ?? []),
                    'return_reason' => $data['return_reason'] ?? null,
                ]);
                
                try {
                    // Filter out items with missing data
                    $returnedItems = collect($data['returned_items'] ?? [])
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

                    if (empty($returnedItems)) {
                        Notification::make()
                            ->warning()
                            ->title('No Items to Return')
                            ->body('Please add at least one item with a quantity greater than 0.')
                            ->send();
                        return;
                    }
                    
                    // Call service to record return
                    $service = app(ConsignmentReturnService::class);
                    $service->recordReturn(
                        consignment: $record,
                        returnedItems: $returnedItems,
                        reason: $data['return_reason'] ?? null,
                        notes: $data['return_notes'] ?? null
                    );
                    
                    // Count quantities
                    $totalQty = collect($returnedItems)->sum('quantity');
                    $goodQty = collect($returnedItems)
                        ->where('condition', 'good')
                        ->sum('quantity');
                    
                    // Success notification
                    Notification::make()
                        ->success()
                        ->title('Return Recorded Successfully')
                        ->body("Recorded return of {$totalQty} units. {$goodQty} units added back to inventory.")
                        ->send();
                    
                } catch (\Exception $e) {
                    Notification::make()
                        ->danger()
                        ->title('Error Recording Return')
                        ->body($e->getMessage())
                        ->send();
                }
            });
    }
}
