<?php

namespace App\Filament\Resources\ConsignmentResource\Actions;

use App\Modules\Consignments\Models\Consignment;
use App\Modules\Consignments\Services\ConsignmentInvoiceService;
use App\Modules\Settings\Models\CurrencySetting;
use App\Filament\Resources\InvoiceResource;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
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
                
                // Get ALL items with calculated available quantities
                $allItems = $record->items()
                    ->get()
                    ->map(function ($item) {
                        $available = $item->quantity_sent - ($item->quantity_sold ?? 0) - ($item->quantity_returned ?? 0);
                        $item->quantity_available = $available;
                        return $item;
                    });

                // Filter items that can be sold (have available quantity > 0)
                $availableItems = $allItems->filter(fn ($item) => $item->quantity_available > 0);

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
                    
                    // Items to Sell Section - Pre-populated with all available items
                    Section::make('Items to Sell')
                        ->description($availableItems->isEmpty()
                            ? '⚠️ No items available to sell. All items have been sold or returned.'
                            : 'All available items are pre-filled. Adjust quantities or prices as needed.')
                        ->schema([
                            Repeater::make('sold_items')
                                ->label('')
                                ->schema([
                                    // Read-only item info display
                                    Placeholder::make('item_info')
                                        ->label('Item')
                                        ->content(function (callable $get) use ($availableItems) {
                                            $itemId = $get('item_id');
                                            $item = $availableItems->firstWhere('id', $itemId);
                                            if (!$item) return new \Illuminate\Support\HtmlString('<em>Unknown item</em>');
                                            return new \Illuminate\Support\HtmlString(
                                                "<strong>{$item->product_name}</strong><br>"
                                                . "<small class='text-gray-500'>SKU: {$item->sku} | Available: {$item->quantity_available}</small>"
                                            );
                                        })
                                        ->columnSpan(3),

                                    // Hidden: carries the item ID and metadata
                                    Hidden::make('item_id'),
                                    Hidden::make('max_quantity'),
                                    Hidden::make('total'),

                                    TextInput::make('quantity')
                                        ->label('Qty to Sell')
                                        ->numeric()
                                        ->required()
                                        ->minValue(1)
                                        ->maxValue(fn (callable $get) => $get('max_quantity') ?? 999)
                                        ->live(onBlur: true)
                                        ->rule('integer')
                                        ->rule('min:1')
                                        ->rule(function (callable $get) {
                                            return function ($attribute, $value, $fail) use ($get) {
                                                $maxQty = $get('max_quantity');
                                                if ($maxQty && $value > $maxQty) {
                                                    $fail("Cannot sell more than {$maxQty} units.");
                                                }
                                            };
                                        })
                                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                            $set('total', ($state ?? 0) * ($get('price') ?? 0));
                                        })
                                        ->columnSpan(1),

                                    TextInput::make('price')
                                        ->label("Price ({$currency})")
                                        ->numeric()
                                        ->required()
                                        ->minValue(0)
                                        ->step(0.01)
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                            $set('total', ($state ?? 0) * ($get('quantity') ?? 0));
                                        })
                                        ->columnSpan(1),

                                    Placeholder::make('total_display')
                                        ->label('Total')
                                        ->content(fn (callable $get) =>
                                            $currency . ' ' . number_format(($get('quantity') ?? 0) * ($get('price') ?? 0), 2)
                                        )
                                        ->columnSpan(2),
                                ])
                                ->default(
                                    $availableItems->map(fn ($item) => [
                                        'item_id'      => $item->id,
                                        'max_quantity' => $item->quantity_available,
                                        'quantity'     => $item->quantity_available,
                                        'price'        => $item->price,
                                        'total'        => $item->quantity_available * $item->price,
                                    ])->values()->toArray()
                                )
                                ->columns(7)
                                ->addable(false)
                                ->deletable($availableItems->count() > 1)
                                ->reorderable(false)
                                ->collapsed(false)
                                ->itemLabel(fn (array $state) => $state['item_id']
                                    ? $availableItems->firstWhere('id', $state['item_id'])?->product_name ?? 'Item'
                                    : 'Item')
                                ->columnSpanFull()
                                ->disabled($availableItems->isEmpty()),
                        ]),
                    
                    // Payment Information Section
                    Section::make('Payment Information')
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
                                        ->default('full'),
                                    
                                    TextInput::make('payment_amount')
                                        ->label("Payment Amount ({$currency})")
                                        ->numeric()
                                        ->required()
                                        ->minValue(0)
                                        ->step(0.01)
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
