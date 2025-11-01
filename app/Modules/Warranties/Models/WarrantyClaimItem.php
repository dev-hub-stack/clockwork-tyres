<?php

namespace App\Modules\Warranties\Models;

use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductVariant;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;
use App\Modules\Warranties\Enums\ResolutionAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarrantyClaimItem extends Model
{
    protected $fillable = [
        'warranty_claim_id',
        'product_id',
        'product_variant_id',
        'invoice_id',
        'invoice_item_id',
        'quantity',
        'issue_description',
        'resolution_action',
    ];

    protected $casts = [
        'resolution_action' => ResolutionAction::class,
        'quantity' => 'integer',
    ];

    // ========== RELATIONSHIPS ==========

    public function warrantyClaim(): BelongsTo
    {
        return $this->belongsTo(WarrantyClaim::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'invoice_id');
    }

    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'invoice_item_id');
    }

    // ========== HELPER METHODS ==========

    /**
     * Get product name (from variant or product)
     */
    public function getProductNameAttribute(): string
    {
        if ($this->productVariant) {
            $brand = $this->productVariant->product->brand?->name ?? 'Unknown Brand';
            $model = $this->productVariant->product->model?->name ?? 'Unknown Model';
            return $brand . ' ' . $model;
        }
        
        if ($this->product) {
            $brand = $this->product->brand?->name ?? 'Unknown Brand';
            $model = $this->product->model?->name ?? 'Unknown Model';
            return $brand . ' ' . $model;
        }

        return 'Unknown Product';
    }

    /**
     * Get brand name
     */
    public function getBrandNameAttribute(): ?string
    {
        if ($this->productVariant) {
            return $this->productVariant->product->brand?->name;
        }
        
        if ($this->product) {
            return $this->product->brand?->name;
        }

        return null;
    }

    /**
     * Get product SKU
     */
    public function getSkuAttribute(): ?string
    {
        return $this->productVariant?->sku ?? $this->product?->sku ?? null;
    }

    /**
     * Check if this item was imported from invoice
     */
    public function isFromInvoice(): bool
    {
        return $this->invoice_id !== null && $this->invoice_item_id !== null;
    }
}
