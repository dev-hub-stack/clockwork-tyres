<?php

namespace App\Filament\Resources\ConsignmentResource\Actions;

use App\Modules\Consignments\Models\Consignment;
use App\Modules\Consignments\Services\ConsignmentInvoiceService;
use App\Modules\Settings\Models\CurrencySetting;
use App\Filament\Resources\InvoiceResource;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

class ConvertToInvoiceAction
{
    public static function make(): Action
    {
        return Action::make('convert_to_invoice')
            ->label('Convert to Invoice')
            ->icon('heroicon-o-document-text')
            ->color('warning')
            ->modalHeading('Convert Consignment to Invoice')
            ->modalDescription('Select items to include in the invoice. Items will be marked as sold.')
            ->modalWidth('6xl')
            ->visible(fn (Consignment $record) => 
                $record->canRecordSale() && empty($record->converted_invoice_id)
            )
            ->form(function (Consignment $record) {
                $currency = CurrencySetting::getBase()?->currency_symbol ?? 'AED';
                
                // Get items that can be converted (have available quantity)
                $availableItems = $record->items()
                    ->get()
                    ->filter(fn ($item) => $item->quantity_available > 0);

                if ($availableItems->isEmpty()) {
                    return [
                        Placeholder::make('no_items')
                            ->content('⚠️ No items available to convert. All items have been sold or returned.')
                            ->columnSpanFull(),
                    ];
                }

                return [
                    // Info Section
                    Placeholder::make('info')
                        ->content(function () use ($record) {
                            $customerName = $record->customer->business_name ?? $record->customer->full_name;
                            return "**Consignment:** {$record->consignment_number}<br>**Customer:** {$customerName}";
                        })
                        ->columnSpanFull(),
                    
                    // Items to Convert Section
                    Section::make('Items to Invoice')
                        ->description('Select items to include in the invoice')
                        ->schema([
                            Repeater::make('items_to_convert')
                                ->label('')
                                ->schema([
                                    Select::make('item_id')
                                        ->label('Item')
                                        ->options($availableItems->mapWithKeys(fn ($item) => [
                                            $item->id => "{$item->name} - SKU: {$item->sku} (Available: {$item->quantity_available})"
                                        ]))
                                        ->required()
                                        ->reactive()
                                        ->afterStateUpdated(function ($state, callable $set) use ($record) {
                                            $item = $record->items()->find($state);
                                            if ($item) {
                                                $set('max_quantity', $item->quantity_available);
                                                $set('price', $item->price);
                                                $set('quantity', $item->quantity_available); // Default to all available
                                                $set('total', $item->quantity_available * $item->price);
                                            }
                                        })
                                        ->searchable()
                                        ->columnSpan(3),
                                    
                                    TextInput::make('quantity')
                                        ->label('Quantity')
                                        ->numeric()
                                        ->required()
                                        ->minValue(1)
                                        ->maxValue(fn (callable $get) => $get('max_quantity') ?? 999)
                                        ->default(1)
                                        ->reactive()
                                        ->afterStateUpdated(fn ($state, callable $set, callable $get) => 
                                            $set('total', ($state ?? 0) * ($get('price') ?? 0))
                                        )
                                        ->columnSpan(1),
                                    
                                    TextInput::make('price')
                                        ->label("Price ({$currency})")
                                        ->numeric()
                                        ->required()
                                        ->minValue(0)
                                        ->step(0.01)
                                        ->reactive()
                                        ->afterStateUpdated(fn ($state, callable $set, callable $get) => 
                                            $set('total', ($state ?? 0) * ($get('quantity') ?? 0))
                                        )
                                        ->columnSpan(1),
                                    
                                    Placeholder::make('total_display')
                                        ->label('Total')
                                        ->content(fn (callable $get) => 
                                            $currency . ' ' . number_format($get('total') ?? 0, 2)
                                        )
                                        ->columnSpan(1),
                                    
                                    // Hidden fields
                                    TextInput::make('max_quantity')->hidden()->default(0),
                                    TextInput::make('total')->hidden()->default(0),
                                ])
                                ->columns(6)
                                ->defaultItems(0)
                                ->addActionLabel('Add Item')
                                ->reorderable(false)
                                ->columnSpanFull(),
                        ]),
                    
                    // Summary
                    Placeholder::make('summary')
                        ->content(fn (callable $get) => 
                            '**Total Invoice Amount:** ' . $currency . ' ' . 
                            number_format(collect($get('items_to_convert') ?? [])->sum('total'), 2)
                        )
                        ->columnSpanFull(),
                ];
            })
            ->action(function (Consignment $record, array $data) {
                try {
                    // Filter and prepare items
                    $items = collect($data['items_to_convert'] ?? [])
                        ->filter(fn ($item) => !empty($item['item_id']) && ($item['quantity'] ?? 0) > 0)
                        ->map(function ($item) {
                            return [
                                'item_id' => $item['item_id'],
                                'quantity' => $item['quantity'],
                                'price' => $item['price'],
                            ];
                        })
                        ->toArray();

                    if (empty($items)) {
                        Notification::make()
                            ->warning()
                            ->title('No Items to Convert')
                            ->body('Please add at least one item.')
                            ->send();
                        return;
                    }
                    
                    // Call service to convert to invoice
                    $service = app(ConsignmentInvoiceService::class);
                    $invoice = $service->convertToInvoice(
                        consignment: $record,
                        items: $items
                    );
                    
                    // Success notification
                    $totalQty = collect($items)->sum('quantity');
                    
                    Notification::make()
                        ->success()
                        ->title('Invoice Created Successfully')
                        ->body("Invoice #{$invoice->invoice_number} created with {$totalQty} items from consignment.")
                        ->actions([
                            \Filament\Notifications\Actions\Action::make('view_invoice')
                                ->label('View Invoice')
                                ->url(fn () => InvoiceResource::getUrl('view', ['record' => $invoice]))
                                ->button(),
                        ])
                        ->send();
                    
                } catch (\Exception $e) {
                    Notification::make()
                        ->danger()
                        ->title('Error Converting to Invoice')
                        ->body($e->getMessage())
                        ->send();
                }
            });
    }
}
