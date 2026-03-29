<?php

namespace App\Modules\Procurement\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementRequestItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'procurement_request_id',
        'product_variant_id',
        'warehouse_id',
        'sku',
        'product_name',
        'size',
        'source',
        'status',
        'quantity',
        'unit_price',
        'line_total',
        'note',
        'payload',
    ];

    protected $casts = [
        'product_variant_id' => 'integer',
        'warehouse_id' => 'integer',
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
        'payload' => 'array',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(ProcurementRequest::class, 'procurement_request_id');
    }

    public function getSizeLabelAttribute(): ?string
    {
        return $this->size;
    }

    public function setSizeLabelAttribute(?string $value): void
    {
        $this->attributes['size'] = $value;
    }

    public function getSourceLabelAttribute(): ?string
    {
        return $this->source;
    }

    public function setSourceLabelAttribute(?string $value): void
    {
        $this->attributes['source'] = $value;
    }

    public function getStatusLabelAttribute(): ?string
    {
        return $this->status;
    }

    public function setStatusLabelAttribute(?string $value): void
    {
        $this->attributes['status'] = $value;
    }

    public function getItemAttributesAttribute(): ?array
    {
        return $this->payload;
    }
}
