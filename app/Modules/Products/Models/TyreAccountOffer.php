<?php

namespace App\Modules\Products\Models;

use App\Modules\Accounts\Models\Account;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TyreAccountOffer extends Model
{
    protected $fillable = [
        'tyre_catalog_group_id',
        'account_id',
        'source_batch_id',
        'source_row_id',
        'source_sku',
        'retail_price',
        'wholesale_price_lvl1',
        'wholesale_price_lvl2',
        'wholesale_price_lvl3',
        'brand_image',
        'product_image_1',
        'product_image_2',
        'product_image_3',
        'media_status',
        'inventory_status',
        'offer_payload',
    ];

    protected $casts = [
        'retail_price' => 'decimal:2',
        'wholesale_price_lvl1' => 'decimal:2',
        'wholesale_price_lvl2' => 'decimal:2',
        'wholesale_price_lvl3' => 'decimal:2',
        'offer_payload' => 'array',
    ];

    public function tyreCatalogGroup(): BelongsTo
    {
        return $this->belongsTo(TyreCatalogGroup::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function sourceBatch(): BelongsTo
    {
        return $this->belongsTo(TyreImportBatch::class, 'source_batch_id');
    }

    public function sourceRow(): BelongsTo
    {
        return $this->belongsTo(TyreImportRow::class, 'source_row_id');
    }
}
