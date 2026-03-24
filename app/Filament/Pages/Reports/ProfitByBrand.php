<?php

namespace App\Filament\Pages\Reports;

class ProfitByBrand extends AbstractProfitReportPage
{
    protected static ?string $navigationLabel = 'Profit by Brand';
    protected static ?string $title = 'Profit by Brand';
    protected static ?string $slug = 'reports/profit-by-brand';
    protected static ?int $navigationSort = 21;

    protected function groupExpression(): string { return 'oi.brand_name'; }
    protected function labelHeader(): string { return 'Brand'; }
    protected function description(): string { return 'This report distributes invoice profit across brands by line-value share so brand profitability can be tracked month by month.'; }
}