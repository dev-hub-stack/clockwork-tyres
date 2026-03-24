<?php

namespace App\Filament\Pages\Reports;

class ProfitBySize extends AbstractProfitReportPage
{
    protected static ?string $navigationLabel = 'Profit by Size';
    protected static ?string $title = 'Profit by Size';
    protected static ?string $slug = 'reports/profit-by-size';
    protected static ?int $navigationSort = 23;

    protected function groupExpression(): string { return "JSON_UNQUOTE(JSON_EXTRACT(oi.item_attributes, '$.size'))"; }
    protected function labelHeader(): string { return 'Size'; }
    protected function description(): string { return 'This report groups invoice profit by wheel size across all products.'; }
}