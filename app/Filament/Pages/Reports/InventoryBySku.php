<?php

namespace App\Filament\Pages\Reports;

class InventoryBySku extends AbstractInventoryReportPage
{
    protected static ?string $navigationLabel = 'Inventory by SKU';
    protected static ?string $title = 'Inventory by SKU';
    protected static ?string $slug = 'reports/inventory-by-sku';
    protected static ?int $navigationSort = 40;

    protected function inventoryGroupExpression(): string { return "COALESCE(NULLIF(pv.sku, ''), NULLIF(p.sku, ''))"; }
    protected function salesGroupExpression(): string { return 'oi.sku'; }
    protected function labelHeader(): string { return 'SKU'; }
    protected function description(): string { return 'This report combines inventory additions with invoice-based sold quantities by SKU, with clickable sold counts for invoice drilldown.'; }

    protected function showBrandFilter(): bool { return true; }
    protected function showCategoryFilter(): bool { return true; }
    protected function showSearchFilter(): bool { return true; }
    protected function searchPlaceholder(): string { return 'Search SKU'; }
    protected function inventorySearchExpression(): ?string { return "COALESCE(NULLIF(pv.sku, ''), NULLIF(p.sku, ''))"; }
    protected function salesSearchExpression(): ?string { return 'oi.sku'; }
}