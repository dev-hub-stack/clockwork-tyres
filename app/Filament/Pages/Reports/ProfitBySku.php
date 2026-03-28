<?php

namespace App\Filament\Pages\Reports;

class ProfitBySku extends AbstractProfitReportPage
{
    protected static ?string $navigationLabel = 'Profit by SKU';
    protected static ?string $title = 'Profit by SKU';
    protected static ?string $slug = 'reports/profit-by-sku';
    protected static ?int $navigationSort = 26;

    protected function groupExpression(): string { return 'oi.sku'; }
    protected function labelHeader(): string { return 'SKU'; }
    protected function description(): string { return 'This report shows month-by-month profit at the SKU level.'; }

    protected function showBrandFilter(): bool { return true; }
}