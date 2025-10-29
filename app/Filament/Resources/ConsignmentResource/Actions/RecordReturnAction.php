<?php

namespace App\Filament\Resources\ConsignmentResource\Actions;

use App\Modules\Consignments\Models\Consignment;
use App\Modules\Consignments\Services\ConsignmentService;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

class RecordReturnAction
{
    public static function make(): Action
    {
        return Action::make('record_return')
            ->label('Record Return')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('warning')
            ->modalHeading('Record Items Returned')
            ->modalDescription('Select which sold items have been returned by the customer')
            ->modalWidth('5xl')
            ->visible(fn (Consignment $record) => $record->canRecordReturn())
            ->form(function (Consignment $record) {
                // Get items that can be returned (have sold quantity > returned quantity)
                $returnableItems = $record->items()
                    ->get()
                    ->filter(fn ($item) => $item->getAvailableToReturn() > 0);

                if ($returnableItems->isEmpty()) {
                    return [
                        Placeholder::make('no_items')
                            ->content('No items available to return. No items have been sold yet or all sold items have been returned.')
                            ->columnSpanFull(),
                    ];
                }

                return [
                    Placeholder::make('info')
                        ->content("**Consignment:** {$record->consignment_number}<br>**Customer:** {$record->customer->business_name}")
                        ->columnSpanFull(),
                    
                    Repeater::make('returned_items')
                        ->label('Items to Return')
                        ->schema([
                            TextInput::make('item_id')
                                ->hidden()
                                ->default(fn ($state, $get) => $get('item_id')),
                            
                            Placeholder::make('product_info')
                                ->label('Product')
                                ->content(function ($get) {
                                    $itemId = $get('item_id');
                                    if (!$itemId) return 'Select item';
                                    
                                    $item = \App\Modules\Consignments\Models\ConsignmentItem::find($itemId);
                                    if (!$item) return 'Item not found';
                                    
                                    return "**{$item->product_name}**<br>SKU: {$item->sku}<br>Sent: {$item->qty_sent} | Sold: {$item->qty_sold} | Returned: {$item->qty_returned} | Available to Return: {$item->getAvailableToReturn()}";
                                })
                                ->columnSpan(2),
                            
                            TextInput::make('quantity')
                                ->label('Quantity to Return')
                                ->numeric()
                                ->required()
                                ->minValue(1)
                                ->default(1)
                                ->live()
                                ->maxValue(function ($get) {
                                    $itemId = $get('item_id');
                                    if (!$itemId) return 999;
                                    
                                    $item = \App\Modules\Consignments\Models\ConsignmentItem::find($itemId);
                                    return $item ? $item->getAvailableToReturn() : 999;
                                })
                                ->helperText(fn ($get) => 'Available to return: ' . ($get('available_to_return') ?? 'N/A'))
                                ->columnSpan(1),
                            
                            Textarea::make('return_reason')
                                ->label('Return Reason')
                                ->rows(2)
                                ->placeholder('Optional: Why is this item being returned?')
                                ->columnSpan(1),
                        ])
                        ->default(function () use ($returnableItems) {
                            return $returnableItems->map(function ($item) {
                                return [
                                    'item_id' => $item->id,
                                    'product_name' => $item->product_name,
                                    'sku' => $item->sku,
                                    'quantity_sent' => $item->qty_sent,
                                    'quantity_sold' => $item->qty_sold,
                                    'quantity_returned' => $item->qty_returned,
                                    'available_to_return' => $item->getAvailableToReturn(),
                                    'quantity' => 1,
                                    'return_reason' => '',
                                ];
                            })->toArray();
                        })
                        ->columns(4)
                        ->reorderable(false)
                        ->addable(false)
                        ->deletable(true)
                        ->minItems(1)
                        ->columnSpanFull(),
                    
                    Checkbox::make('update_inventory')
                        ->label('Update Warehouse Inventory')
                        ->helperText('If checked, returned items will be added back to warehouse stock')
                        ->default(true)
                        ->columnSpanFull(),
                ];
            })
            ->action(function (Consignment $record, array $data, ConsignmentService $service) {
                try {
                    // Filter out items with 0 quantity and prepare data
                    $returnedItems = collect($data['returned_items'] ?? [])
                        ->filter(fn ($item) => ($item['quantity'] ?? 0) > 0)
                        ->map(function ($item) {
                            return [
                                'item_id' => $item['item_id'],
                                'quantity' => $item['quantity'],
                                'return_reason' => $item['return_reason'] ?? null,
                            ];
                        })
                        ->toArray();

                    if (empty($returnedItems)) {
                        Notification::make()
                            ->warning()
                            ->title('No Items to Return')
                            ->body('Please select at least one item with a quantity greater than 0.')
                            ->send();
                        return;
                    }

                    $updateInventory = $data['update_inventory'] ?? false;
                    
                    // Call service to record return
                    $service->recordReturn($record, $returnedItems, $updateInventory);
                    
                    // Success notification
                    $returnedCount = count($returnedItems);
                    $totalQty = collect($returnedItems)->sum('quantity');
                    
                    $body = "Recorded return of {$totalQty} items ({$returnedCount} line items)";
                    if ($updateInventory) {
                        $body .= ". Warehouse inventory has been updated.";
                    }
                    
                    Notification::make()
                        ->success()
                        ->title('Return Recorded Successfully')
                        ->body($body)
                        ->send();
                    
                } catch (\Exception $e) {
                    Notification::make()
                        ->danger()
                        ->title('Error Recording Return')
                        ->body($e->getMessage())
                        ->send();
                    
                    throw $e;
                }
            })
            ->successNotificationTitle('Return recorded successfully')
            ->requiresConfirmation(false); // Already has modal with form
    }
}
