<?php

namespace App\Filament\Pages\Reports;

class DealerSalesByBrand extends AbstractDealerSalesReportPage
{
    protected static ?string $navigationLabel = 'Dealer Sales by Brand';

    protected static ?string $title = 'Dealer Sales by Brand';

    protected static ?string $slug = 'reports/dealer-sales-by-brand';

    protected static ?int $navigationSort = 20;

    protected function groupExpression(): string
    {
        return "COALESCE(oi.brand_name, 'Unbranded')";
    }

    protected function labelHeader(): string
    {
        return 'Brand';
    }

    protected function description(): string
    {
        return 'This report reuses the sales-by-brand pivot, but locks the data to wholesale invoices for one dealer at a time so account performance can be reviewed brand by brand.';
    }
}