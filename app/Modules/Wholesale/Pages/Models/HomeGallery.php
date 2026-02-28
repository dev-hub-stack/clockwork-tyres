<?php

namespace App\Modules\Wholesale\Pages\Models;

use Illuminate\Database\Eloquent\Model;

class HomeGallery extends Model
{
    protected $table = 'wholesale_home_galleries';

    protected $fillable = [
        'title',
        'image_path',
        'link',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function getImageUrlAttribute()
    {
        return $this->image_path 
            ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->image_path)
            : null;
    }
}
