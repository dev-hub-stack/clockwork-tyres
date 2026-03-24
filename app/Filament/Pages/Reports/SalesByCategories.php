<?php

namespace App\Filament\Pages\Reports;

class SalesByCategories extends AbstractSalesReportPage
{
    protected static ?string $navigationLabel = 'Sales by Categories';

    protected static ?string $title = 'Sales by Categories';

    protected static ?string $slug = 'reports/sales-by-categories';

    protected static ?int $navigationSort = 9;

    protected function groupExpression(): string
    {
        return "CASE WHEN oi.add_on_id IS NOT NULL THEN 'Accessories' ELSE 'Wheels' END";
    }

    protected function labelHeader(): string
    {
        return 'Category';
    }

    protected function description(): string
    {
        return 'This report separates wheel sales from add-on sales so the monthly category mix can be reviewed quickly.';
    }
}