<?php

namespace App\Filament\Pages\Reports;

class ProfitByDealer extends AbstractProfitReportPage
{
    protected static ?string $navigationLabel = 'Profit by Dealer';
    protected static ?string $title = 'Profit by Dealer';
    protected static ?string $slug = 'reports/profit-by-dealer';
    protected static ?int $navigationSort = 25;

    protected function groupExpression(): string { return "COALESCE(NULLIF(c.business_name, ''), CONCAT_WS(' ', NULLIF(c.first_name, ''), NULLIF(c.last_name, '')))"; }
    protected function labelHeader(): string { return 'Dealer'; }
    protected function description(): string { return 'This report compares gross profit contribution by dealer over the selected months.'; }
}