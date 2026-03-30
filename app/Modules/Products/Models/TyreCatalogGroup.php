<?php

namespace App\Modules\Products\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TyreCatalogGroup extends Model
{
    protected $fillable = [
        'storefront_merge_key',
        'brand_id',
        'brand_name',
        'model_id',
        'model_name',
        'width',
        'height',
        'rim_size',
        'full_size',
        'load_index',
        'speed_rating',
        'dot_year',
        'country',
        'tyre_type',
        'runflat',
        'rfid',
        'sidewall',
        'warranty',
        'reference_resolution',
        'meta',
    ];

    protected $casts = [
        'width' => 'integer',
        'height' => 'integer',
        'rim_size' => 'integer',
        'runflat' => 'boolean',
        'rfid' => 'boolean',
        'reference_resolution' => 'array',
        'meta' => 'array',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function model(): BelongsTo
    {
        return $this->belongsTo(ProductModel::class, 'model_id');
    }

    public function offers(): HasMany
    {
        return $this->hasMany(TyreAccountOffer::class);
    }
}
