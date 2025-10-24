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
        $subtotal = 0;
        if (isset($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as &$item) {
                $qty = floatval($item['quantity'] ?? 0);
                $price = floatval($item['unit_price'] ?? 0);
                $discount = floatval($item['discount'] ?? 0);
                $lineTotal = ($qty * $price) - $discount;
                $subtotal += $lineTotal;
                
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
        
        // Calculate VAT from settings
        $vat = $subtotal * ($taxRate / 100);
        
        $data['sub_total'] = $subtotal;
        $data['vat'] = $vat;
        $data['shipping'] = floatval($data['shipping'] ?? 0);
        $data['discount'] = floatval($data['discount'] ?? 0);
        $data['total'] = $subtotal + $vat + $data['shipping'] - $data['discount'];
        
        return $data;
    }
    
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
