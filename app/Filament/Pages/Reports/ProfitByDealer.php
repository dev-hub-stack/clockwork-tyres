<?php

namespace App\Filament\Pages\Reports;

use Illuminate\Support\Facades\DB;

class ProfitByDealer extends AbstractProfitReportPage
{
    protected static ?string $navigationLabel = 'Profit by Dealer';
    protected static ?string $title = 'Profit by Dealer';
    protected static ?string $slug = 'reports/profit-by-dealer';
    protected static ?int $navigationSort = 25;

    protected function groupExpression(): string
    {
        return DB::getDriverName() === 'sqlite'
            ? "COALESCE(NULLIF(c.business_name, ''), TRIM(COALESCE(c.first_name, '') || ' ' || COALESCE(c.last_name, '')))"
            : "COALESCE(NULLIF(c.business_name, ''), CONCAT_WS(' ', NULLIF(c.first_name, ''), NULLIF(c.last_name, '')))";
    }
    protected function labelHeader(): string { return 'Dealer'; }
    protected function description(): string { return 'This report compares gross profit contribution by dealer over the selected months.'; }
}