<?php

namespace App\Modules\Wholesale\Pages\Models;

use Illuminate\Database\Eloquent\Model;

class WholesalePage extends Model
{
    protected $table = 'wholesale_pages';

    protected $fillable = [
        'slug',
        'title',
        'content',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
