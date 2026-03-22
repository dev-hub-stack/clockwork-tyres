<?php

namespace App\Filament\Resources\DealerActivityLogResource\Pages;

use App\Filament\Resources\DealerActivityLogResource;
use Filament\Resources\Pages\ListRecords;

class ListDealerActivityLogs extends ListRecords
{
    protected static string $resource = DealerActivityLogResource::class;

    public function getTitle(): string
    {
        return 'Dealer Activity Log';
    }
}