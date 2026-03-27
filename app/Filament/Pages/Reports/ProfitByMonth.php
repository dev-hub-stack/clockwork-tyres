<?php

namespace App\Filament\Pages\Reports;

use Illuminate\Support\Facades\DB;

class ProfitByMonth extends AbstractProfitReportPage
{
    protected static ?string $navigationLabel = 'Profit by Month';
    protected static ?string $title = 'Profit by Month';
    protected static ?string $slug = 'reports/profit-by-month';
    protected static ?int $navigationSort = 27;

    protected function groupExpression(): string
    {
        return DB::getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', COALESCE(o.issue_date, o.created_at))"
            : "DATE_FORMAT(COALESCE(o.issue_date, o.created_at), '%b %Y')";
    }
    protected function labelHeader(): string { return 'Month'; }
    protected function description(): string { return 'This report summarizes the profit pool by month within the selected range.'; }

    protected function showChannelFilter(): bool { return true; }
    protected function showUserFilter(): bool { return false; }
    protected function showDealerFilter(): bool { return false; }
}