<?php

namespace App\Filament\Pages\Reports;

class SalesByChannel extends AbstractSalesReportPage
{
    protected static ?string $navigationLabel = 'Sales by Channel';

    protected static ?string $title = 'Sales by Channel';

    protected static ?string $slug = 'reports/sales-by-channel';

    protected static ?int $navigationSort = 7;

    protected function groupExpression(): string
    {
        return 'o.external_source';
    }

    protected function labelHeader(): string
    {
        return 'Channel';
    }

    protected function description(): string
    {
        return 'This report compares monthly sales between retail and wholesale channels using CRM invoices as the single source of truth.';
    }

    protected function showChannelFilter(): bool
    {
        return false;
    }
}