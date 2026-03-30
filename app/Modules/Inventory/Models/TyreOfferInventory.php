<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Accounts\Models\Account;
use App\Modules\Products\Models\TyreAccountOffer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TyreOfferInventory extends Model
{
    protected $fillable = [
        'tyre_account_offer_id',
        'account_id',
        'warehouse_id',
        'quantity',
        'eta',
        'eta_qty',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'eta_qty' => 'integer',
    ];

    public function offer(): BelongsTo
    {
        return $this->belongsTo(TyreAccountOffer::class, 'tyre_account_offer_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function getTotalAvailableAttribute(): int
    {
        return $this->quantity + $this->eta_qty;
    }

    public function scopeInStock($query)
    {
        return $query->where('quantity', '>', 0);
    }
}
