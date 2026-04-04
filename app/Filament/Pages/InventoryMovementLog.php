<?php

namespace App\Filament\Pages;

use App\Filament\Support\PanelAccess;
use App\Modules\Accounts\Support\CurrentAccountResolver;
use App\Modules\Inventory\Models\InventoryLog;
use App\Modules\Inventory\Models\Warehouse;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use BackedEnum;
use UnitEnum;

class InventoryMovementLog extends Page
{
    private const ALLOWED_INVENTORY_TYPES = ['products', 'tyres', 'addons'];

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Movement Log';

    protected static UnitEnum|string|null $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.inventory-movement-log';

    public static function shouldRegisterNavigation(): bool
    {
        return PanelAccess::canAccessOperationalSurface('view_inventory');
    }

    public static function canAccess(): bool
    {
        return PanelAccess::canAccessOperationalSurface('view_inventory');
    }

    public function getViewData(): array
    {
        $defaultInventoryType = request()?->query('inventory_type');
        if (! in_array($defaultInventoryType, self::ALLOWED_INVENTORY_TYPES, true)) {
            $defaultInventoryType = '';
        }

        $currentAccountId = auth()->check() && request()
            ? app(CurrentAccountResolver::class)->resolve(request(), auth()->user())->currentAccount?->id
            : null;

        $warehouses = Warehouse::query()
            ->where('status', 1)
            ->when($currentAccountId, fn ($query) => $query->where('account_id', $currentAccountId))
            ->orderBy('code')
            ->get();

        return [
            'defaultInventoryType' => $defaultInventoryType,
            'warehouses' => $warehouses,
        ];
    }
}
