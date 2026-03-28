<?php

namespace App\Filament\Pages\Reports;

class SalesByModel extends AbstractSalesReportPage
{
    protected static ?string $navigationLabel = 'Sales by Model';

    protected static ?string $title = 'Sales by Model';

    protected static ?string $slug = 'reports/sales-by-model';

    protected static ?int $navigationSort = 2;

    protected function groupExpression(): string
    {
        return 'oi.model_name';
    }

    protected function labelHeader(): string
    {
        return 'Model';
    }

    protected function description(): string
    {
        return 'This report aggregates invoice line items by model and pivots quantity and value across the selected month range.';
    }

    protected function showBrandFilter(): bool
    {
        return true;
    }

    protected function showQtyDrilldown(): bool
    {
        return true;
    }
}