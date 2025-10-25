@php
    $variantId = $getRecord()?->product_variant_id ?? data_get($getState(), '../product_variant_id');
    
    if ($variantId) {
        $variant = \App\Modules\Products\Models\ProductVariant::find($variantId);
        $productImage = $variant?->image;
        $productName = $variant?->product?->name ?? 'Product';
    }
@endphp

@if(isset($productImage) && $productImage)
    <div style="display: flex; align-items: center; gap: 15px; padding: 12px; background: #f8f9fa; border-radius: 8px; border: 1px solid #e5e7eb;">
        <img src="{{ $productImage }}" 
             alt="{{ $productName }}" 
             style="width: 100px; height: 100px; object-fit: cover; border-radius: 6px; border: 2px solid #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <div style="flex: 1;">
            <div style="font-weight: 600; font-size: 14px; color: #374151;">{{ $productName }}</div>
            @if($variant?->sku)
                <div style="font-size: 12px; color: #6b7280; margin-top: 4px;">SKU: {{ $variant->sku }}</div>
            @endif
            @if($variant?->size)
                <div style="font-size: 12px; color: #6b7280; margin-top: 2px;">
                    Size: {{ $variant->size }}
                    @if($variant->bolt_pattern) | Bolt: {{ $variant->bolt_pattern }}@endif
                    @if($variant->offset) | Offset: {{ $variant->offset }}@endif
                </div>
            @endif
        </div>
    </div>
@endif
