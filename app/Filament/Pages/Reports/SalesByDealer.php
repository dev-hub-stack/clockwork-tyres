<?php

namespace App\Filament\Pages\Reports;

use Illuminate\Support\Facades\DB;

class SalesByDealer extends AbstractSalesReportPage
{
    protected static ?string $navigationLabel = 'Sales by Dealer';

    protected static ?string $title = 'Sales by Dealer';

    protected static ?string $slug = 'reports/sales-by-dealer';

    protected static ?int $navigationSort = 5;

    protected function groupExpression(): string
    {
        return DB::getDriverName() === 'sqlite'
            ? "COALESCE(NULLIF(c.business_name, ''), TRIM(COALESCE(c.first_name, '') || ' ' || COALESCE(c.last_name, '')))"
            : "COALESCE(NULLIF(c.business_name, ''), CONCAT_WS(' ', NULLIF(c.first_name, ''), NULLIF(c.last_name, '')))";
    }

    protected function labelHeader(): string
    {
        return 'Dealer';
    }

    protected function description(): string
    {
        return 'This report shows monthly quantity and value by dealer name so wholesale customer performance can be compared directly.';
    }
}