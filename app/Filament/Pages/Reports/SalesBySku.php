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
}