<?php

namespace App\Filament\Pages\Reports;

class InventoryByBrand extends AbstractInventoryReportPage
{
    protected static ?string $navigationLabel = 'Inventory by Brand';
    protected static ?string $title = 'Inventory by Brand';
    protected static ?string $slug = 'reports/inventory-by-brand';
    protected static ?int $navigationSort = 41;

    protected function inventoryGroupExpression(): string { return 'b.name'; }
    protected function salesGroupExpression(): string { return 'oi.brand_name'; }
    protected function labelHeader(): string { return 'Brand'; }
    protected function description(): string { return 'This report shows stock added versus sold by brand across the selected month range.'; }
}