<?php

namespace App\Filament\Pages\Reports;

class SalesBySku extends AbstractSalesReportPage
{
    protected static ?string $navigationLabel = 'Sales by SKU';

    protected static ?string $title = 'Sales by SKU';

    protected static ?string $slug = 'reports/sales-by-sku';

    protected static ?int $navigationSort = 6;

    protected function groupExpression(): string
    {
        return 'oi.sku';
    }

    protected function labelHeader(): string
    {
        return 'SKU';
    }

    protected function description(): string
    {
        return 'This report groups invoice lines by SKU so exact item-level quantity and revenue can be reviewed month over month.';
    }

    protected function showBrandFilter(): bool
    {
        return true;
    }

    protected function showSearchFilter(): bool
    {
        return true;
    }

    protected function searchPlaceholder(): string
    {
        return 'Search SKU';
    }

    protected function searchExpression(): ?string
    {
        return 'oi.sku';
    }

    protected function showQtyDrilldown(): bool
    {
        return true;
    }
}