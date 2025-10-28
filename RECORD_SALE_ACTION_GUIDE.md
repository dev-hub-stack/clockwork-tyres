# Quick Implementation Guide - Record Sale Action

## Overview
This guide shows how to implement the Record Sale action in Filament, which is the most critical action for the consignment workflow.

## Backend Service (Already Done ✅)
```php
// app/Modules/Consignments/Services/ConsignmentService.php

public function recordSale(
    Consignment $consignment, 
    array $soldItems, 
    bool $createInvoice = false
): ?Order {
    // Validates quantities
    // Updates ConsignmentItem quantities
    // Updates Consignment status
    // Optionally creates Invoice (Order with document_type='invoice')
    // Logs history
    // Returns invoice if created
}
```

**Example call:**
```php
$soldItems = [
    [
        'item_id' => 1,
        'quantity_sold' => 2,
        'actual_sale_price' => 150.00,
    ],
    [
        'item_id' => 3,
        'quantity_sold' => 1,
        'actual_sale_price' => 200.00,
    ],
];

$invoice = $consignmentService->recordSale($consignment, $soldItems, $createInvoice = true);
```

---

## Frontend Implementation

### Step 1: Create Action File
**Location:** `app/Filament/Resources/ConsignmentResource/Actions/RecordSaleAction.php`

```php
<?php

namespace App\Filament\Resources\ConsignmentResource\Actions;

use App\Modules\Consignments\Models\Consignment;
use App\Modules\Consignments\Services\ConsignmentService;
use App\Modules\Settings\Models\CurrencySetting;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class RecordSaleAction
{
    public static function make(): Action
    {
        return Action::make('record_sale')
            ->label('Record Sale')
            ->icon('heroicon-o-currency-dollar')
            ->color('success')
            ->modalHeading('Record Items Sold')
            ->modalDescription('Select which items have been sold and enter sale details')
            ->modalWidth('5xl')
            ->form(function (Consignment $record) {
                $currency = CurrencySetting::getBase()?->currency_symbol ?? 'AED';
                
                // Get items that can be sold (have available quantity)
                $availableItems = $record->items()
                    ->get()
                    ->filter(fn ($item) => $item->getAvailableToSell() > 0)
                    ->map(function ($item) use ($currency) {
                        return [
                            'item_id' => $item->id,
                            'product_name' => $item->product_name,
                            'sku' => $item->sku,
                            'brand' => $item->brand_name,
                            'quantity_sent' => $item->quantity_sent,
                            'quantity_sold' => $item->quantity_sold,
                            'available' => $item->getAvailableToSell(),
                            'price' => $item->price,
                            'currency' => $currency,
                        ];
                    });

                return [
                    Repeater::make('sold_items')
                        ->label('Items to Sell')
                        ->schema([
                            Select::make('item_id')
                                ->label('Item')
                                ->options($availableItems->pluck('product_name', 'item_id'))
                                ->required()
                                ->reactive()
                                ->afterStateUpdated(function ($state, $set) use ($availableItems) {
                                    $item = $availableItems->firstWhere('item_id', $state);
                                    if ($item) {
                                        $set('quantity_to_sell', 1);
                                        $set('sale_price', $item['price']);
                                        $set('max_quantity', $item['available']);
                                    }
                                }),
                            
                            TextInput::make('quantity_to_sell')
                                ->label('Quantity to Sell')
                                ->numeric()
                                ->required()
                                ->minValue(1)
                                ->maxValue(fn ($get) => $get('max_quantity') ?? 1),
                            
                            TextInput::make('sale_price')
                                ->label('Sale Price')
                                ->numeric()
                                ->required()
                                ->prefix($currency)
                                ->minValue(0.01),
                            
                            TextInput::make('max_quantity')
                                ->label('Available')
                                ->disabled()
                                ->dehydrated(false),
                        ])
                        ->columns(4)
                        ->defaultItems(0)
                        ->addActionLabel('Add Item')
                        ->reorderable(false)
                        ->required()
                        ->minItems(1),
                    
                    Checkbox::make('create_invoice')
                        ->label('Create Invoice Automatically')
                        ->helperText('If checked, an invoice will be created for the sold items')
                        ->default(true),
                ];
            })
            ->action(function (array $data, Consignment $record): void {
                try {
                    DB::beginTransaction();
                    
                    // Transform form data to service format
                    $soldItems = collect($data['sold_items'])->map(function ($item) {
                        return [
                            'item_id' => $item['item_id'],
                            'quantity_sold' => $item['quantity_to_sell'],
                            'actual_sale_price' => $item['sale_price'],
                        ];
                    })->toArray();
                    
                    $createInvoice = $data['create_invoice'] ?? false;
                    
                    // Call service
                    $consignmentService = app(ConsignmentService::class);
                    $invoice = $consignmentService->recordSale($record, $soldItems, $createInvoice);
                    
                    DB::commit();
                    
                    // Success notification
                    $message = 'Sale recorded successfully!';
                    if ($invoice) {
                        $message .= " Invoice #{$invoice->order_number} created.";
                    }
                    
                    Notification::make()
                        ->success()
                        ->title('Sale Recorded')
                        ->body($message)
                        ->send();
                    
                    // Redirect to invoice if created
                    if ($invoice) {
                        redirect()->route('filament.admin.resources.orders.view', ['record' => $invoice->id]);
                    }
                    
                } catch (\Exception $e) {
                    DB::rollBack();
                    
                    Notification::make()
                        ->danger()
                        ->title('Error Recording Sale')
                        ->body($e->getMessage())
                        ->send();
                }
            })
            ->visible(fn (Consignment $record): bool => $record->canRecordSale());
    }
}
```

---

### Step 2: Register Action in ConsignmentResource

**Location:** `app/Filament/Resources/ConsignmentResource.php`

```php
use App\Filament\Resources\ConsignmentResource\Actions\RecordSaleAction;

public static function getHeaderActions(): array
{
    return [
        RecordSaleAction::make(),
        // ... other actions
    ];
}

// OR in the view page specifically:
// app/Filament/Resources/ConsignmentResource/Pages/ViewConsignment.php

protected function getHeaderActions(): array
{
    return [
        RecordSaleAction::make(),
        Actions\EditAction::make(),
    ];
}
```

---

### Step 3: Test the Action

1. **Create a test consignment:**
   - Go to Consignments → Create
   - Add customer, warehouse, items
   - Save

2. **Mark as Sent (if needed):**
   - Status must be SENT or DELIVERED to record sales

3. **Click "Record Sale":**
   - Modal should open
   - Select items
   - Enter quantities
   - Enter prices
   - Check "Create Invoice"
   - Submit

4. **Verify:**
   - ✅ Consignment items show updated quantities
   - ✅ Status changed to PARTIALLY_SOLD or INVOICED_IN_FULL
   - ✅ Invoice created in orders table (if checked)
   - ✅ History entry added
   - ✅ Redirect to invoice

---

## Improvements & Extensions

### Add Item Summary in Modal
Show a summary of what will be sold:

```php
Placeholder::make('summary')
    ->label('Sale Summary')
    ->content(function ($get) use ($currency) {
        $items = $get('sold_items') ?? [];
        $total = collect($items)->sum(function ($item) {
            return ($item['quantity_to_sell'] ?? 0) * ($item['sale_price'] ?? 0);
        });
        return "Total Sale Value: {$currency} " . number_format($total, 2);
    }),
```

### Add Validation Messages
```php
->rules([
    function () {
        return function (string $attribute, $value, Closure $fail) {
            if ($value > $maxQuantity) {
                $fail("Cannot sell more than {$maxQuantity} available.");
            }
        };
    },
])
```

### Add Bulk Select
Allow selecting all available items at once:

```php
Actions\Action::make('select_all')
    ->label('Select All Available Items')
    ->action(function ($set) use ($availableItems) {
        $items = $availableItems->map(function ($item) {
            return [
                'item_id' => $item['item_id'],
                'quantity_to_sell' => $item['available'],
                'sale_price' => $item['price'],
                'max_quantity' => $item['available'],
            ];
        })->toArray();
        
        $set('sold_items', $items);
    }),
```

---

## Similar Pattern for Other Actions

### Record Return Action
- Same structure, different fields
- Filter for `quantity_sold > 0`
- Add "Update Inventory" checkbox
- Call `recordReturn()` service

### Convert to Invoice Action
- Simpler modal (just confirmation)
- Show summary of all sold items
- Call `createInvoiceForSoldItems()` service
- Always redirect to created invoice

---

## Reference Files

**Similar implementations in the codebase:**
- `app/Filament/Resources/QuoteResource.php` - Modal actions for approve/convert
- Filament Actions docs: https://filamentphp.com/docs/3.x/actions/overview

**Backend service:**
- `app/Modules/Consignments/Services/ConsignmentService.php` (line 145)

**Model methods:**
- `Consignment::canRecordSale()` (line 215)
- `ConsignmentItem::getAvailableToSell()` (line 98)

---

## Time Estimate
- Create action file: **45 min**
- Register in resource: **5 min**
- Testing: **30 min**
- Refinements: **20 min**

**Total: ~2 hours**

Ready to implement! 🚀
