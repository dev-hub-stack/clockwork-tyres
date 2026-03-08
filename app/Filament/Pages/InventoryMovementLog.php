<?php

namespace App\Filament\Pages;

use App\Modules\Inventory\Models\InventoryLog;
use App\Modules\Inventory\Models\Warehouse;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use BackedEnum;
use UnitEnum;

class InventoryMovementLog extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Movement Log';

    protected static UnitEnum|string|null $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.inventory-movement-log';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_inventory') ?? false;
    }

    public function getViewData(): array
    {
        $warehouses = Warehouse::where('status', 1)->orderBy('code')->get();
        return [
            'warehouses' => $warehouses,
        ];
    }
}
