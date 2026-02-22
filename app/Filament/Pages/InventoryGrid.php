<?php

namespace App\Filament\Pages;

use App\Modules\Inventory\Models\Warehouse;
use Filament\Pages\Page;
use UnitEnum;
use BackedEnum;

class InventoryGrid extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'Inventory Grid';

    protected static UnitEnum|string|null $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_inventory') ?? false;
    }

    protected string $view = 'filament.pages.inventory-grid';

    public $warehouses = [];

    public function mount(): void
    {
        // Only load warehouses (small list) — variant data is loaded via AJAX
        // after page renders to avoid serializing 51k rows in Livewire snapshot
        $this->warehouses = Warehouse::where('status', 1)
            ->where('is_system', false)
            ->orderBy('code')
            ->get();
    }
}
