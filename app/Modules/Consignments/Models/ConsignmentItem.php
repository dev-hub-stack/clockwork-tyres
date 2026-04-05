<?php

namespace App\Modules\Consignments\Models;

use App\Modules\AddOns\Models\Addon;
use App\Modules\Consignments\Enums\ConsignmentItemStatus;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductVariant;
use App\Modules\Products\Models\TyreAccountOffer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConsignmentItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        // Relationships
        'consignment_id',
        'product_variant_id',
        'tyre_account_offer_id',
        'warehouse_id',
        
        // Snapshots (JSONB) - preserve product data at time of consignment
        'product_snapshot',
        
        // Denormalized fields (from snapshot for quick access)
        'product_name',
        'brand_name',
        'sku',
        'description',
        
        // Quantity tracking
        'quantity_sent',
        'quantity_sold',
        'quantity_returned',
        
        // Pricing
        'price',
        'tax_inclusive',
        'actual_sale_price',
        
        // Status & Dates
        'status',
        'date_sold',
        'date_returned',
    ];

    protected $casts = [
        'status' => ConsignmentItemStatus::class,
        'product_snapshot' => 'array',
        'quantity_sent' => 'integer',
        'quantity_sold' => 'integer',
        'quantity_returned' => 'integer',
        'price' => 'decimal:2',
        'tax_inclusive' => 'boolean',
        'actual_sale_price' => 'decimal:2',
        'date_sold' => 'datetime',
        'date_returned' => 'datetime',
    ];

    /**
     * Auto-populate product_snapshot with variant specs whenever an item is saved.
     */
    protected static function booted(): void
    {
        static::saving(function (ConsignmentItem $item) {
            if (!$item->product_variant_id) {
                if (! $item->tyre_account_offer_id) {
                    return;
                }

                $offer = TyreAccountOffer::with('tyreCatalogGroup')->find($item->tyre_account_offer_id);

                if (! $offer) {
                    return;
                }

                $group = $offer->tyreCatalogGroup;
                $brand = $group?->brand_name ?? 'Unknown Brand';
                $model = $group?->model_name ?? 'Unknown Model';
                $fullSize = $group?->full_size ?? 'Unknown Size';
                $loadIndex = $group?->load_index;
                $speedRating = $group?->speed_rating;

                $item->product_name = $item->product_name ?: trim($brand . ' ' . $model);
                $item->brand_name = $item->brand_name ?: $brand;
                $item->description = $item->description ?: trim(collect([
                    $fullSize,
                    $loadIndex ? 'Load ' . $loadIndex : null,
                    $speedRating ? 'Speed ' . $speedRating : null,
                ])->filter()->implode('  '));

                $snapshot = is_array($item->product_snapshot) ? $item->product_snapshot : [];
                $snapshot['product_type'] = 'tyre';
                $snapshot['brand_name'] = $brand;
                $snapshot['model_name'] = $model;
                $snapshot['specifications'] = array_filter([
                    'full_size' => $fullSize,
                    'width' => $group?->width,
                    'height' => $group?->height,
                    'rim_size' => $group?->rim_size,
                    'load_index' => $loadIndex,
                    'speed_rating' => $speedRating,
                    'dot_year' => $group?->dot_year,
                    'runflat' => $group?->runflat,
                ], fn ($value) => $value !== null && $value !== '');

                $item->product_snapshot = $snapshot;
                return;
            }

            $variant = \App\Modules\Products\Models\ProductVariant::with(['finishRelation', 'product.finish'])
                ->find($item->product_variant_id);

            if (!$variant) {
                return;
            }

            $snapshot = is_array($item->product_snapshot) ? $item->product_snapshot : [];

            $finishName = $variant->finishRelation?->finish
                ?? $variant->product?->finish?->finish
                ?? (is_array($snapshot['finish'] ?? null) ? ($snapshot['finish']['finish'] ?? null) : null)
                ?? $variant->getRawOriginal('finish');

            $snapshot['specifications'] = [
                'size'         => $variant->size,
                'bolt_pattern' => $variant->bolt_pattern,
                'offset'       => $variant->offset,
                'finish'       => $finishName,
            ];

            $item->product_snapshot = $snapshot;
        });
    }

    /**
     * Get the consignment that owns this item
     */
    public function consignment(): BelongsTo
    {
        return $this->belongsTo(Consignment::class);
    }

    /**
     * Get the product variant for this item
     */
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function tyreAccountOffer(): BelongsTo
    {
        return $this->belongsTo(TyreAccountOffer::class);
    }

    /**
     * Get the warehouse for this item
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Inventory\Models\Warehouse::class);
    }

    /**
     * Get the product for this item (through variant)
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_variant_id');
    }

    /**
     * Accessors for snapshots
     */
    public function getProductSnapshotDataAttribute(): ?array
    {
        return $this->product_snapshot;
    }

    /**
     * Get snapshot value by key
     */
    public function getProductSnapshotValue(string $key, $default = null)
    {
        return data_get($this->product_snapshot, $key, $default);
    }

    /**
     * Calculate line total based on quantity sent and price
     */
    public function calculateLineTotal(): float
    {
        return $this->quantity_sent * $this->price;
    }

    /**
     * Get available quantity to sell
     */
    public function getAvailableToSell(): int
    {
        return $this->quantity_sent - $this->quantity_sold - $this->quantity_returned;
    }

    /**
     * Get available quantity to return (items still with customer, not yet sold or returned)
     */
    public function getAvailableToReturn(): int
    {
        // Return items that are with customer: sent - sold - already returned
        return $this->quantity_sent - $this->quantity_sold - $this->quantity_returned;
    }

    /**
     * Mark items as sold
     */
    public function markAsSold(int $quantity, ?float $salePrice = null): void
    {
        $this->quantity_sold += $quantity;
        
        if ($salePrice !== null) {
            $this->actual_sale_price = $salePrice;
        }
        
        $this->date_sold = now();
        
        // Update status if all sent items are sold
        if ($this->quantity_sold >= $this->quantity_sent) {
            $this->status = ConsignmentItemStatus::SOLD;
        }
        
        $this->save();
    }

    /**
     * Mark items as returned
     */
    public function markAsReturned(int $quantity): void
    {
        $this->quantity_returned += $quantity;
        $this->date_returned = now();
        
        // Update status if all items are returned
        if ($this->quantity_returned >= $this->quantity_sent) {
            $this->status = ConsignmentItemStatus::RETURNED;
        }
        
        $this->save();
    }

    /**
     * Check if item can be sold
     */
    public function canBeSold(): bool
    {
        return $this->status->canBeSold() && $this->getAvailableToSell() > 0;
    }

    /**
     * Check if item can be returned
     */
    public function canBeReturned(): bool
    {
        return $this->status->canBeReturned() && $this->getAvailableToReturn() > 0;
    }

    /**
     * Get the effective sale price (actual sale price or original price)
     */
    public function getEffectiveSalePrice(): float
    {
        return $this->actual_sale_price ?? $this->price;
    }
}
