<?php

namespace App\Filament\Pages\Reports;

class ProfitByModel extends AbstractProfitReportPage
{
    protected static ?string $navigationLabel = 'Profit by Model';
    protected static ?string $title = 'Profit by Model';
    protected static ?string $slug = 'reports/profit-by-model';
    protected static ?int $navigationSort = 22;

    protected function groupExpression(): string { return 'oi.model_name'; }
    protected function labelHeader(): string { return 'Model'; }
    protected function description(): string { return 'This report tracks model-level profit contribution across the selected month range.'; }
}