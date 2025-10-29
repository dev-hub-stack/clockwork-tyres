<?php

namespace App\Filament\Resources\ConsignmentResource\Actions;

use App\Modules\Consignments\Models\Consignment;
use App\Modules\Consignments\Services\ConsignmentService;
use App\Modules\Settings\Models\CurrencySetting;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Number;

class ConvertToInvoiceAction
{
    public static function make(): Action
    {
        return Action::make('convert_to_invoice')
            ->label('Convert to Invoice')
            ->icon('heroicon-o-document-text')
            ->color('primary')
            ->modalHeading('Convert Consignment to Invoice')
            ->modalDescription('Create an invoice for all sold items in this consignment')
            ->modalWidth('3xl')
            ->visible(fn (Consignment $record) => 
                $record->items_sold_count > 0 && 
                !$record->converted_to_invoice_id
            )
            ->form(function (Consignment $record) {
                $currency = CurrencySetting::getBase()?->currency_symbol ?? 'AED';
                
                // Calculate sold items total
                $soldItems = $record->items()
                    ->where('qty_sold', '>', 0)
                    ->get();
                
                $totalSoldQty = $soldItems->sum('qty_sold');
                $totalValue = $soldItems->sum(function ($item) {
                    $price = $item->actual_sale_price ?? $item->price;
                    return $item->qty_sold * $price;
                });

                return [
                    Placeholder::make('consignment_info')
                        ->label('Consignment Details')
                        ->content(function () use ($record) {
                            return "**Consignment:** {$record->consignment_number}<br>" .
                                   "**Customer:** {$record->customer->business_name}<br>" .
                                   "**Issue Date:** {$record->issue_date->format('d/m/Y')}";
                        })
                        ->columnSpanFull(),
                    
                    Placeholder::make('sold_items_summary')
                        ->label('Sold Items Summary')
                        ->content(function () use ($soldItems, $totalSoldQty, $totalValue, $currency, $record) {
                            $itemsList = $soldItems->map(function ($item) use ($currency) {
                                $price = $item->actual_sale_price ?? $item->price;
                                $total = $item->qty_sold * $price;
                                return "• {$item->product_name} - Qty: {$item->qty_sold} × " . 
                                       Number::currency($price, $currency) . " = " . 
                                       Number::currency($total, $currency);
                            })->join('<br>');
                            
                            $subtotal = $totalValue;
                            $tax = $subtotal * ($record->tax_rate / 100);
                            $total = $subtotal + $tax;
                            
                            return "**Items to Invoice:**<br>{$itemsList}<br><br>" .
                                   "**Subtotal:** " . Number::currency($subtotal, $currency) . "<br>" .
                                   "**Tax ({$record->tax_rate}%):** " . Number::currency($tax, $currency) . "<br>" .
                                   "**Total:** " . Number::currency($total, $currency);
                        })
                        ->columnSpanFull(),
                    
                    DatePicker::make('due_date')
                        ->label('Invoice Due Date')
                        ->default(now()->addDays(30))
                        ->required()
                        ->helperText('Payment due date for the invoice')
                        ->columnSpanFull(),
                    
                    Textarea::make('payment_terms')
                        ->label('Payment Terms')
                        ->rows(2)
                        ->placeholder('e.g., Net 30, Payment due within 30 days')
                        ->helperText('Optional: Specify payment terms for this invoice')
                        ->columnSpanFull(),
                    
                    Textarea::make('invoice_notes')
                        ->label('Invoice Notes')
                        ->rows(3)
                        ->placeholder('Any additional notes for the invoice...')
                        ->helperText('Optional: These notes will appear on the invoice')
                        ->columnSpanFull(),
                ];
            })
            ->action(function (Consignment $record, array $data, ConsignmentService $service) {
                try {
                    // Check if already converted
                    if ($record->converted_to_invoice_id) {
                        Notification::make()
                            ->warning()
                            ->title('Already Converted')
                            ->body('This consignment has already been converted to an invoice.')
                            ->send();
                        return;
                    }

                    // Check if has sold items
                    if ($record->items_sold_count === 0) {
                        Notification::make()
                            ->warning()
                            ->title('No Sold Items')
                            ->body('This consignment has no sold items to invoice.')
                            ->send();
                        return;
                    }

                    // Convert to invoice
                    $invoice = $service->convertToInvoice($record);
                    
                    // Update invoice with additional data if provided
                    $updateData = [];
                    if (!empty($data['due_date'])) {
                        $updateData['due_date'] = $data['due_date'];
                    }
                    if (!empty($data['payment_terms'])) {
                        $updateData['payment_terms'] = $data['payment_terms'];
                    }
                    if (!empty($data['invoice_notes'])) {
                        $updateData['order_notes'] = ($invoice->order_notes ?? '') . "\n\n" . $data['invoice_notes'];
                    }
                    
                    if (!empty($updateData)) {
                        $invoice->update($updateData);
                    }
                    
                    // Success notification with action button
                    Notification::make()
                        ->success()
                        ->title('Invoice Created Successfully')
                        ->body("Invoice {$invoice->order_number} has been created from consignment {$record->consignment_number}")
                        ->actions([
                            \Filament\Notifications\Actions\Action::make('view_invoice')
                                ->label('View Invoice')
                                ->url(route('filament.admin.resources.invoices.edit', $invoice->id))
                                ->button(),
                        ])
                        ->send();
                    
                    // Redirect to invoice edit page
                    return redirect()->route('filament.admin.resources.invoices.edit', $invoice->id);
                    
                } catch (\Exception $e) {
                    Notification::make()
                        ->danger()
                        ->title('Error Creating Invoice')
                        ->body($e->getMessage())
                        ->send();
                    
                    throw $e;
                }
            })
            ->requiresConfirmation()
            ->modalSubmitActionLabel('Create Invoice')
            ->modalIcon('heroicon-o-document-text');
    }
}
