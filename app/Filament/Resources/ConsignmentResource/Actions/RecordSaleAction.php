<?php

namespace App\Filament\Resources\ConsignmentResource\Actions;

use App\Modules\Consignments\Models\Consignment;
use App\Modules\Consignments\Services\ConsignmentService;
use App\Modules\Settings\Models\CurrencySetting;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class RecordSaleAction
{
    public static function make(): Action
    {
        return Action::make('record_sale')
            ->label('Record Sale')
            ->icon('heroicon-o-banknotes')
            ->color('success')
            ->modalHeading('Record Items Sold')
            ->modalDescription('Select which items have been sold and optionally create an invoice')
            ->modalWidth('5xl')
            ->visible(fn (Consignment $record) => $record->canRecordSale())
            ->form(function (Consignment $record) {
                $currency = CurrencySetting::getBase()?->currency_symbol ?? 'AED';
                
                // Get items that can be sold (have available quantity)
                $availableItems = $record->items()
                    ->get()
                    ->filter(fn ($item) => $item->getAvailableToSell() > 0);

                if ($availableItems->isEmpty()) {
                    return [
                        Placeholder::make('no_items')
                            ->content('No items available to sell. All items have been sold or returned.')
                            ->columnSpanFull(),
                    ];
                }

                return [
                    Placeholder::make('info')
                        ->content("**Consignment:** {$record->consignment_number}<br>**Customer:** {$record->customer->business_name}")
                        ->columnSpanFull(),
                    
                    Repeater::make('sold_items')
                        ->label('Items to Sell')
                        ->schema([
                            TextInput::make('item_id')
                                ->hidden()
                                ->default(fn ($state, $get) => $get('item_id')),
                            
                            Placeholder::make('product_info')
                                ->label('Product')
                                ->content(function ($get, $state) {
                                    $itemId = $get('item_id');
                                    if (!$itemId) return 'Select item';
                                    
                                    $item = \App\Modules\Consignments\Models\ConsignmentItem::find($itemId);
                                    if (!$item) return 'Item not found';
                                    
                                    return "**{$item->product_name}**<br>SKU: {$item->sku}<br>Sent: {$item->qty_sent} | Sold: {$item->qty_sold} | Available: {$item->getAvailableToSell()}";
                                })
                                ->columnSpan(2),
                            
                            TextInput::make('quantity')
                                ->label('Quantity to Sell')
                                ->numeric()
                                ->required()
                                ->minValue(1)
                                ->default(1)
                                ->live()
                                ->maxValue(function ($get) {
                                    $itemId = $get('item_id');
                                    if (!$itemId) return 999;
                                    
                                    $item = \App\Modules\Consignments\Models\ConsignmentItem::find($itemId);
                                    return $item ? $item->getAvailableToSell() : 999;
                                })
                                ->helperText(fn ($get) => 'Available: ' . ($get('available') ?? 'N/A'))
                                ->columnSpan(1),
                            
                            TextInput::make('actual_sale_price')
                                ->label('Sale Price')
                                ->numeric()
                                ->required()
                                ->prefix($currency)
                                ->default(fn ($get) => $get('price'))
                                ->minValue(0)
                                ->step(0.01)
                                ->helperText('Price per unit')
                                ->columnSpan(1),
                        ])
                        ->default(function () use ($availableItems) {
                            return $availableItems->map(function ($item) {
                                return [
                                    'item_id' => $item->id,
                                    'product_name' => $item->product_name,
                                    'sku' => $item->sku,
                                    'quantity_sent' => $item->qty_sent,
                                    'quantity_sold' => $item->qty_sold,
                                    'available' => $item->getAvailableToSell(),
                                    'quantity' => 1,
                                    'price' => $item->price,
                                    'actual_sale_price' => $item->price,
                                ];
                            })->toArray();
                        })
                        ->columns(4)
                        ->reorderable(false)
                        ->addable(false)
                        ->deletable(true)
                        ->minItems(1)
                        ->columnSpanFull(),
                    
                    Checkbox::make('create_invoice')
                        ->label('Create Invoice for Sold Items')
                        ->helperText('If checked, an invoice will be automatically created with the sold items')
                        ->default(true)
                        ->columnSpanFull(),
                ];
            })
            ->action(function (Consignment $record, array $data, ConsignmentService $service) {
                try {
                    // Filter out items with 0 quantity and prepare data
                    $soldItems = collect($data['sold_items'] ?? [])
                        ->filter(fn ($item) => ($item['quantity'] ?? 0) > 0)
                        ->map(function ($item) {
                            return [
                                'item_id' => $item['item_id'],
                                'quantity' => $item['quantity'],
                                'actual_sale_price' => $item['actual_sale_price'],
                            ];
                        })
                        ->toArray();

                    if (empty($soldItems)) {
                        Notification::make()
                            ->warning()
                            ->title('No Items to Sell')
                            ->body('Please select at least one item with a quantity greater than 0.')
                            ->send();
                        return;
                    }

                    $createInvoice = $data['create_invoice'] ?? false;
                    
                    // Call service to record sale
                    $invoice = $service->recordSale($record, $soldItems, $createInvoice);
                    
                    // Success notification
                    $soldCount = count($soldItems);
                    $totalQty = collect($soldItems)->sum('quantity');
                    
                    $notification = Notification::make()
                        ->success()
                        ->title('Sale Recorded Successfully')
                        ->body("Recorded sale of {$totalQty} items ({$soldCount} line items)");
                    
                    if ($invoice) {
                        $notification->body("Invoice {$invoice->order_number} created with {$totalQty} items");
                        $notification->actions([
                            \Filament\Notifications\Actions\Action::make('view_invoice')
                                ->label('View Invoice')
                                ->url(route('filament.admin.resources.invoices.edit', $invoice->id))
                                ->button(),
                        ]);
                    }
                    
                    $notification->send();
                    
                    // Redirect to invoice if created, otherwise just refresh
                    if ($invoice) {
                        return redirect()->route('filament.admin.resources.invoices.edit', $invoice->id);
                    }
                    
                } catch (\Exception $e) {
                    Notification::make()
                        ->danger()
                        ->title('Error Recording Sale')
                        ->body($e->getMessage())
                        ->send();
                    
                    throw $e;
                }
            })
            ->successNotificationTitle('Sale recorded successfully')
            ->requiresConfirmation(false); // Already has modal with form
    }
}
