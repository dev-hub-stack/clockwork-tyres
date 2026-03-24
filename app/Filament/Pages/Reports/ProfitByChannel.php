<?php

namespace App\Filament\Pages\Reports;

class ProfitByChannel extends AbstractProfitReportPage
{
    protected static ?string $navigationLabel = 'Profit by Channel';
    protected static ?string $title = 'Profit by Channel';
    protected static ?string $slug = 'reports/profit-by-channel';
    protected static ?int $navigationSort = 29;

    protected function groupExpression(): string { return 'o.external_source'; }
    protected function labelHeader(): string { return 'Channel'; }
    protected function description(): string { return 'This report compares retail and wholesale profit over the selected time frame.'; }

    protected function showChannelFilter(): bool { return false; }
}