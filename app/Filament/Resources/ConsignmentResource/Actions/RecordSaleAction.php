<?php

namespace App\Filament\Resources\ConsignmentResource\Actions;

use App\Modules\Consignments\Models\Consignment;
use App\Modules\Consignments\Services\ConsignmentInvoiceService;
use App\Modules\Settings\Models\CurrencySetting;
use App\Filament\Resources\InvoiceResource;
use Filament\Actions\Action;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

class RecordSaleAction
{
    public static function make(): Action
    {
        return Action::make('record_sale')
            ->label('Record Sale')
            ->icon('heroicon-o-currency-dollar')
            ->color('success')
            ->modalHeading(fn (Consignment $record) => 'Record Sale - Consignment #' . $record->consignment_number)
            ->modalDescription('Select items that were sold. This will create an invoice and update the consignment status.')
            ->modalWidth('7xl')
            ->visible(fn (Consignment $record) => $record->canRecordSale())
            ->form(function (Consignment $record) {
                $currency = CurrencySetting::getBase()?->currency_symbol ?? 'AED';
                
                // Get items that can be sold (have available quantity)
                $availableItems = $record->items()
                    ->get()
                    ->filter(function ($item) {
                        $available = $item->quantity_sent - ($item->quantity_sold ?? 0) - ($item->quantity_returned ?? 0);
                        return $available > 0;
                    })
                    ->map(function ($item) {
                        $available = $item->quantity_sent - ($item->quantity_sold ?? 0) - ($item->quantity_returned ?? 0);
                        $item->quantity_available = $available;
                        return $item;
                    });

                if ($availableItems->isEmpty()) {
                    return [
                        Placeholder::make('no_items')
                            ->content('⚠️ No items available to sell. All items have been sold or returned.')
                            ->columnSpanFull(),
                    ];
                }

                return [
                    // Customer Information Section
                    Fieldset::make('Customer Information')
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
                    
                    // Items to Sell Section
                    Fieldset::make('Items to Sell')
                        ->schema([
                            Repeater::make('sold_items')
                                ->label('')
                                ->schema([
                                    Select::make('item_id')
                                        ->label('Item')
                                        ->options($availableItems->mapWithKeys(fn ($item) => [
                                            $item->id => "{$item->product_name} - SKU: {$item->sku} (Available: {$item->quantity_available})"
                                        ]))
                                        ->required()
                                        ->reactive()
                                        ->afterStateUpdated(function ($state, callable $set) use ($availableItems) {
                                            $item = $availableItems->firstWhere('id', $state);
                                            if ($item) {
                                                $set('max_quantity', $item->quantity_available);
                                                $set('price', $item->price);
                                                $set('quantity', 1);
                                            }
                                        })
                                        ->searchable()
                                        ->columnSpan(3),
                                    
                                    TextInput::make('quantity')
                                        ->label('Quantity to Sell')
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
                                        ->label("Sale Price ({$currency})")
                                        ->numeric()
                                        ->required()
                                        ->minValue(0)
                                        ->step(0.01)
                                        ->reactive()
                                        ->afterStateUpdated(fn ($state, callable $set, callable $get) => 
                                            $set('total', ($state ?? 0) * ($get('quantity') ?? 0))
                                        )
                                        ->columnSpan(1),
                                    
                                    Placeholder::make('total')
                                        ->label('Total')
                                        ->content(fn (callable $get) => 
                                            $currency . ' ' . number_format($get('total') ?? 0, 2)
                                        )
                                        ->columnSpan(1),
                                    
                                    // Hidden fields for tracking
                                    TextInput::make('max_quantity')->hidden()->default(0),
                                    TextInput::make('total')->hidden()->default(0),
                                ])
                                ->columns(6)
                                ->defaultItems(0)
                                ->addActionLabel('Add Item to Sale')
                                ->reorderable(false)
                                ->columnSpanFull(),
                        ]),
                    
                    // Payment Information Section
                    Fieldset::make('Payment Information')
                        ->schema([
                            Grid::make(2)
                                ->schema([
                                    Select::make('payment_method')
                                        ->label('Payment Method')
                                        ->options([
                                            'cash' => 'Cash',
                                            'card' => 'Credit/Debit Card',
                                            'bank_transfer' => 'Bank Transfer',
                                            'check' => 'Check',
                                            'other' => 'Other',
                                        ])
                                        ->required()
                                        ->default('cash'),
                                    
                                    Select::make('payment_type')
                                        ->label('Payment Type')
                                        ->options([
                                            'full' => 'Full Payment',
                                            'partial' => 'Partial Payment',
                                        ])
                                        ->required()
                                        ->default('full')
                                        ->reactive()
                                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                            if ($state === 'full') {
                                                // Calculate total from sold_items
                                                $soldItems = $get('sold_items') ?? [];
                                                $total = collect($soldItems)->sum('total');
                                                $set('payment_amount', $total);
                                            }
                                        }),
                                    
                                    TextInput::make('payment_amount')
                                        ->label("Payment Amount ({$currency})")
                                        ->numeric()
                                        ->required()
                                        ->minValue(0)
                                        ->step(0.01)
                                        ->reactive()
                                        ->helperText('Enter the amount received from customer'),
                                ]),
                        ]),
                    
                    // Notes Section
                    Textarea::make('sale_notes')
                        ->label('Sale Notes (Optional)')
                        ->rows(3)
                        ->placeholder('Add any notes about this sale...')
                        ->columnSpanFull(),
                ];
            })
            ->action(function (Consignment $record, array $data) {
                try {
                    // Filter out items with missing data
                    $soldItems = collect($data['sold_items'] ?? [])
                        ->filter(fn ($item) => !empty($item['item_id']) && ($item['quantity'] ?? 0) > 0)
                        ->map(function ($item) {
                            return [
                                'item_id' => $item['item_id'],
                                'quantity' => $item['quantity'],
                                'price' => $item['price'],
                            ];
                        })
                        ->toArray();

                    if (empty($soldItems)) {
                        Notification::make()
                            ->warning()
                            ->title('No Items to Sell')
                            ->body('Please add at least one item with a quantity greater than 0.')
                            ->send();
                        return;
                    }

                    // Prepare payment data
                    $paymentData = [
                        'method' => $data['payment_method'],
                        'type' => $data['payment_type'],
                        'amount' => $data['payment_amount'],
                    ];
                    
                    // Call service to record sale and create invoice
                    $service = app(ConsignmentInvoiceService::class);
                    $invoice = $service->recordSaleAndCreateInvoice(
                        consignment: $record,
                        soldItems: $soldItems,
                        paymentData: $paymentData,
                        notes: $data['sale_notes'] ?? null
                    );
                    
                    // Success notification
                    $totalQty = collect($soldItems)->sum('quantity');
                    
                    Notification::make()
                        ->success()
                        ->title('Sale Recorded Successfully')
                        ->body("Invoice #{$invoice->invoice_number} created with {$totalQty} items. Payment: {$paymentData['amount']}")
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
                        ->title('Error Recording Sale')
                        ->body($e->getMessage())
                        ->send();
                }
            });
    }
}
