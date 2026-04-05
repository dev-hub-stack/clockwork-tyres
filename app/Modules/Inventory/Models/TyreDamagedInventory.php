<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Consignments\Models\Consignment;
use App\Modules\Products\Models\TyreAccountOffer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TyreDamagedInventory extends Model
{
    use HasFactory;

    protected $table = 'tyre_damaged_inventories';

    protected $fillable = [
        'tyre_account_offer_id',
        'warehouse_id',
        'quantity',
        'condition',
        'notes',
        'consignment_id',
    ];

    protected $casts = [
        'tyre_account_offer_id' => 'integer',
        'warehouse_id' => 'integer',
        'quantity' => 'integer',
        'consignment_id' => 'integer',
    ];

    public function tyreAccountOffer(): BelongsTo
    {
        return $this->belongsTo(TyreAccountOffer::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function consignment(): BelongsTo
    {
        return $this->belongsTo(Consignment::class);
    }
}
