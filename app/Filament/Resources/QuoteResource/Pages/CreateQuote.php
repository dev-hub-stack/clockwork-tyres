<?php

namespace App\Filament\Resources\QuoteResource\Pages;

use App\Filament\Resources\QuoteResource;
use App\Modules\Orders\Enums\DocumentType;
use App\Modules\Orders\Enums\QuoteStatus;
use App\Modules\Products\Models\ProductVariant;
use App\Modules\Settings\Models\CompanyBranding;
use App\Modules\Settings\Models\TaxSetting;
use Filament\Resources\Pages\CreateRecord;

class CreateQuote extends CreateRecord
{
    protected static string $resource = QuoteResource::class;
    
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Get settings
        $taxSetting = TaxSetting::getDefault();
        $taxRate = $taxSetting ? floatval($taxSetting->rate) : 5;
        
        // Ensure it's created as a quote
        $data['document_type'] = DocumentType::QUOTE;
        $data['quote_status'] = QuoteStatus::DRAFT;
        $data['issue_date'] = $data['issue_date'] ?? now();
        $data['currency'] = $data['currency'] ?? 'AED';
        $data['tax_inclusive'] = $data['tax_inclusive'] ?? false;
        
        // Calculate totals from line items and populate product details
        if (isset($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as &$item) {
                $qty = floatval($item['quantity'] ?? 0);
                $price = floatval($item['unit_price'] ?? 0);
                $discount = floatval($item['discount'] ?? 0);
                $lineTotal = ($qty * $price) - $discount;

                // Set line_total for this item
                $item['line_total'] = $lineTotal;
                
                // Populate product details from variant
                if (isset($item['product_variant_id'])) {
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
    
    protected function afterCreate(): void
    {
        $this->recalculateTotals($this->getRecord());
    }

    private function recalculateTotals(\App\Modules\Orders\Models\Order $record): void
    {
        $record->refresh();

        // Don't overwrite totals if no items — would reset correct values to 0
        if ($record->items->isEmpty()) {
            return;
        }

        $taxSetting = TaxSetting::getDefault();
        $taxRate    = $taxSetting ? floatval($taxSetting->rate) : 5;
        $multiplier = 1 + ($taxRate / 100);

        $inclGross = 0.0;
        $exclNet   = 0.0;

        foreach ($record->items as $item) {
            // Always compute fresh — never trust stored line_total (may be stale/0)
            $lineTotal    = (floatval($item->quantity) * floatval($item->unit_price)) - floatval($item->discount ?? 0);
            // Default to tax-inclusive (true) when not explicitly set
            $taxInclusive = isset($item->tax_inclusive) ? (bool) $item->tax_inclusive : true;

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

        $subTotal = round($inclNet  + $exclBase, 2);
        $vat      = round($inclTax  + $exclTax,  2);
        $total    = round($inclGross + $exclBase + $exclTax, 2);

        // Fallback: derive VAT from total - sub_total if calculation gave 0
        if ($vat == 0 && $total > 0 && $subTotal > 0) {
            $vat = round($total - $subTotal, 2);
        }

        $record->update([
            'sub_total' => $subTotal,
            'vat'       => $vat,
            'total'     => $total,
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }}