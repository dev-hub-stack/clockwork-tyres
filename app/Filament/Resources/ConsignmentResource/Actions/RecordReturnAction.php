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
                // Get items that can be returned (sent items that haven't been returned yet)
                $returnableItems = $record->items()
                    ->get()
                    ->filter(function ($item) {
                        $canReturn = $item->quantity_sent - ($item->quantity_returned ?? 0);
                        return $canReturn > 0;
                    })
                    ->map(function ($item) {
                        $canReturn = $item->quantity_sent - ($item->quantity_returned ?? 0);
                        $item->quantity_returnable = $canReturn;
                        return $item;
                    });

                if ($returnableItems->isEmpty()) {
                    return [
                        Placeholder::make('no_items')
                            ->content('⚠️ No items available to return. All items have been returned.')
                            ->columnSpanFull(),
                    ];
                }

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
                    
                    // Items to Return Section
                    Section::make('Items to Return')
                        ->schema([
                            Repeater::make('returned_items')
                                ->label('')
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
                                ->defaultItems(0)
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
            ->action(function (Consignment $record, array $data) {
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
                    
                    // Count good items that will be added to inventory
                    $goodItems = collect($returnedItems)->where('condition', 'good')->count();
                    $totalQty = collect($returnedItems)->sum('quantity');
                    
                    // Success notification
                    Notification::make()
                        ->success()
                        ->title('Return Recorded Successfully')
                        ->body("Recorded return of {$totalQty} items. {$goodItems} items added back to inventory.")
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
