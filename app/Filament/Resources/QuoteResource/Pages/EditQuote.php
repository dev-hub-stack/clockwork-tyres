<?php

namespace App\Filament\Resources\QuoteResource\Pages;

use App\Filament\Resources\QuoteResource;
use App\Modules\Products\Models\ProductVariant;
use App\Modules\Settings\Models\TaxSetting;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditQuote extends EditRecord
{
    protected static string $resource = QuoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
    
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Get tax rate from settings
        $taxSetting = TaxSetting::getDefault();
        $taxRate = $taxSetting ? floatval($taxSetting->rate) : 5;
        
        // Calculate totals from line items and populate product details
        if (isset($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as &$item) {
                $qty = floatval($item['quantity'] ?? 0);
                $price = floatval($item['unit_price'] ?? 0);
                $discount = floatval($item['discount'] ?? 0);
                $lineTotal = ($qty * $price) - $discount;

                // Set line_total for this item
                $item['line_total'] = $lineTotal;
                
                // Populate product details from variant if not already set
                if (isset($item['product_variant_id']) && empty($item['product_name'])) {
                    $variant = ProductVariant::with(['product.brand', 'product.model'])->find($item['product_variant_id']);
                    if ($variant && $variant->product) {
                        $item['product_id'] = $variant->product_id;
                        $item['product_name'] = $variant->product->name ?? 'Unknown Product';
                        $item['sku'] = $variant->sku;
                        $item['brand_name'] = $variant->product->brand?->name;
                        $item['model_name'] = $variant->product->model?->name;
                        $item['product_description'] = $variant->product->description;
                        
                        // Store snapshots for historical data
                        $item['product_snapshot'] = json_encode($variant->product->toArray());
                        $item['variant_snapshot'] = json_encode($variant->toArray());
                    }
                }
            }
        }
        
        // Calculate totals respecting per-item tax_inclusive flag
        $multiplier = 1 + ($taxRate / 100);
        $inclGross = 0.0;
        $exclNet   = 0.0;

        if (isset($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $item) {
                $qty          = floatval($item['quantity'] ?? 0);
                $price        = floatval($item['unit_price'] ?? 0);
                $lineDiscount = floatval($item['discount'] ?? 0);
                $taxInclusive = (bool) ($item['tax_inclusive'] ?? true);
                $lineTotal    = ($qty * $price) - $lineDiscount;

                if ($taxInclusive) {
                    $inclGross += $lineTotal;
                } else {
                    $exclNet += $lineTotal;
                }
            }
        }

        $shipping = floatval($data['shipping'] ?? 0);
        $discount = floatval($data['discount'] ?? 0);

        // Inclusive: extract tax, total stays the same
        $inclTax = $inclGross - ($inclGross / $multiplier);
        $inclNet = $inclGross / $multiplier;

        // Exclusive + shipping − discount: add tax on top
        $exclBase = $exclNet + $shipping - $discount;
        $exclTax  = $exclBase * ($taxRate / 100);

        $data['sub_total'] = round($inclNet  + $exclBase, 2);
        $data['vat']       = round($inclTax  + $exclTax,  2);
        $data['shipping']  = $shipping;
        $data['discount']  = $discount;
        $data['total']     = round($inclGross + $exclBase + $exclTax, 2);

        return $data;
    }
    
    protected function afterSave(): void
    {
        $this->recalculateTotals($this->getRecord());
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    private function recalculateTotals(\App\Modules\Orders\Models\Order $record): void
    {
        $record->refresh();

        $taxSetting = TaxSetting::getDefault();
        $taxRate    = $taxSetting ? floatval($taxSetting->rate) : 5;
        $multiplier = 1 + ($taxRate / 100);

        $inclGross = 0.0;
        $exclNet   = 0.0;

        foreach ($record->items as $item) {
            // Always compute fresh — never trust stored line_total (may be stale/0)
            $lineTotal    = (floatval($item->quantity) * floatval($item->unit_price)) - floatval($item->discount ?? 0);
            $taxInclusive = (bool) $item->tax_inclusive;

            // Fix the stored line_total on the item as well
            $item->timestamps = false;
            $item->update(['line_total' => $lineTotal]);

            if ($taxInclusive) {
                $inclGross += $lineTotal;
            } else {
                $exclNet += $lineTotal;
            }
        }

        $shipping = floatval($record->shipping ?? 0);
        $discount = floatval($record->discount ?? 0);

        $inclTax  = $inclGross - ($inclGross / $multiplier);
        $inclNet  = $inclGross / $multiplier;
        $exclBase = $exclNet + $shipping - $discount;
        $exclTax  = $exclBase * ($taxRate / 100);

        $record->update([
            'sub_total' => round($inclNet  + $exclBase, 2),
            'vat'       => round($inclTax  + $exclTax,  2),
            'total'     => round($inclGross + $exclBase + $exclTax, 2),
        ]);
    }
}
