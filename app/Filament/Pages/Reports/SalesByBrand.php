<?php

namespace App\Filament\Pages\Reports;

class SalesByBrand extends AbstractSalesReportPage
{
    protected static ?string $navigationLabel = 'Sales by Brand';

    protected static ?string $title = 'Sales by Brand';

    protected static ?string $slug = 'reports/sales-by-brand';

    protected static ?int $navigationSort = 1;

    protected function groupExpression(): string
    {
        return 'oi.brand_name';
    }

    protected function labelHeader(): string
    {
        return 'Brand';
    }

    protected function description(): string
    {
        return 'This report aggregates invoice line items by brand, then pivots quantity and value across the selected month range. It uses CRM invoices only, matching the meeting requirement.';
    }

    protected function showQtyDrilldown(): bool
    {
        return true;
    }
}