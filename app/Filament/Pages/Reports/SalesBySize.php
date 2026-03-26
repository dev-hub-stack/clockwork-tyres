<?php

namespace App\Filament\Pages\Reports;

use Illuminate\Support\Facades\DB;

class SalesBySize extends AbstractSalesReportPage
{
    protected static ?string $navigationLabel = 'Sales by Size';

    protected static ?string $title = 'Sales by Size';

    protected static ?string $slug = 'reports/sales-by-size';

    protected static ?int $navigationSort = 3;

    protected function groupExpression(): string
    {
        return DB::getDriverName() === 'sqlite'
            ? "json_extract(oi.item_attributes, '$.size')"
            : "JSON_UNQUOTE(JSON_EXTRACT(oi.item_attributes, '$.size'))";
    }

    protected function labelHeader(): string
    {
        return 'Size';
    }

    protected function description(): string
    {
        return 'This report groups invoice items by wheel size across all brands and models, matching the monthly pivot layout from the mockups.';
    }
}