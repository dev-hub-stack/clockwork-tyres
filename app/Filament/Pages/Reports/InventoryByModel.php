<?php

namespace App\Filament\Pages\Reports;

class InventoryByModel extends AbstractInventoryReportPage
{
    protected static ?string $navigationLabel = 'Inventory by Model';
    protected static ?string $title = 'Inventory by Model';
    protected static ?string $slug = 'reports/inventory-by-model';
    protected static ?int $navigationSort = 42;

    protected function inventoryGroupExpression(): string { return 'm.name'; }
    protected function salesGroupExpression(): string { return 'oi.model_name'; }
    protected function labelHeader(): string { return 'Model'; }
    protected function description(): string { return 'This report tracks inventory movement by model, combining inbound additions with invoice-based sales.'; }

    protected function showBrandFilter(): bool { return true; }
}