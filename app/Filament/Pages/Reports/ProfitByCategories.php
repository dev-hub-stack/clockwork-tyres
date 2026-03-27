<?php

namespace App\Filament\Pages\Reports;

class ProfitByCategories extends AbstractProfitReportPage
{
    protected static ?string $navigationLabel = 'Profit by Categories';
    protected static ?string $title = 'Profit by Categories';
    protected static ?string $slug = 'reports/profit-by-categories';
    protected static ?int $navigationSort = 30;

    protected function groupExpression(): string { return "CASE WHEN oi.add_on_id IS NOT NULL THEN 'Accessories' ELSE 'Wheels' END"; }
    protected function labelHeader(): string { return 'Category'; }
    protected function description(): string { return 'This report separates wheel and add-on profit contribution.'; }
}