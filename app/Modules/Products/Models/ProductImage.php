<?php

namespace App\Modules\Products\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'brand_id',
        'model_id',
        'finish_id',
        'image_1',
        'image_2',
        'image_3',
        'image_4',
        'image_5',
        'image_6',
        'image_7',
        'image_8',
        'image_9',
        'external_id',
        'external_source',
    ];

    /**
     * Relationship: Brand
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * Relationship: Model
     */
    public function model(): BelongsTo
    {
        return $this->belongsTo(ProductModel::class, 'model_id');
    }

    /**
     * Relationship: Finish
     */
    public function finish(): BelongsTo
    {
        return $this->belongsTo(Finish::class);
    }

    /**
     * Get all images as array
     */
    public function getImagesArray(): array
    {
        $images = [];
        
        for ($i = 1; $i <= 9; $i++) {
            $field = "image_{$i}";
            if (!empty($this->$field)) {
                $images[] = $this->$field;
            }
        }
        
        return $images;
    }

    /**
     * Get primary image (first available)
     */
    public function getPrimaryImage(): ?string
    {
        for ($i = 1; $i <= 9; $i++) {
            $field = "image_{$i}";
            if (!empty($this->$field)) {
                return $this->$field;
            }
        }
        
        return null;
    }

    /**
     * Check if has any images
     */
    public function hasImages(): bool
    {
        return !empty($this->getImagesArray());
    }

    /**
     * Accessor: Get combination key
     */
    public function getCombinationKeyAttribute(): string
    {
        return "{$this->brand_id}_{$this->model_id}_{$this->finish_id}";
    }

    /**
     * Scope: For specific combination
     */
    public function scopeForCombination($query, $brandId, $modelId, $finishId)
    {
        return $query->where('brand_id', $brandId)
            ->where('model_id', $modelId)
            ->where('finish_id', $finishId);
    }
}
