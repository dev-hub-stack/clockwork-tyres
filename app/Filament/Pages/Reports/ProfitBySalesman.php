<?php

namespace App\Filament\Pages\Reports;

class ProfitBySalesman extends AbstractProfitReportPage
{
    protected static ?string $navigationLabel = 'Profit by Salesman';
    protected static ?string $title = 'Profit by Salesman';
    protected static ?string $slug = 'reports/profit-by-salesman';
    protected static ?int $navigationSort = 28;

    protected function groupExpression(): string { return 'u.name'; }
    protected function labelHeader(): string { return 'Salesman'; }
    protected function description(): string { return 'This report allocates recorded invoice profit by assigned CRM representative.'; }

    protected function showUserFilter(): bool { return false; }
}