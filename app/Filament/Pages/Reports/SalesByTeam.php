<?php

namespace App\Filament\Pages\Reports;

class SalesByTeam extends AbstractSalesReportPage
{
    protected static ?string $navigationLabel = 'Sales by Team';

    protected static ?string $title = 'Sales by Team';

    protected static ?string $slug = 'reports/sales-by-team';

    protected static ?int $navigationSort = 8;

    protected function groupExpression(): string
    {
        return 'u.name';
    }

    protected function labelHeader(): string
    {
        return 'User';
    }

    protected function description(): string
    {
        return 'This report groups monthly sales by the assigned CRM representative so team performance can be compared without leaving the reporting module.';
    }

    protected function showUserFilter(): bool
    {
        return false;
    }
}