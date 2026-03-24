<?php

namespace App\Filament\Pages\Reports;

class DealerSalesByModel extends AbstractDealerSalesReportPage
{
    protected static ?string $navigationLabel = 'Dealer Sales by Model';

    protected static ?string $title = 'Dealer Sales by Model';

    protected static ?string $slug = 'reports/dealer-sales-by-model';

    protected static ?int $navigationSort = 21;

    protected function groupExpression(): string
    {
        return "COALESCE(oi.model_name, pv.model, 'Unknown Model')";
    }

    protected function labelHeader(): string
    {
        return 'Model';
    }

    protected function description(): string
    {
        return 'This report breaks a selected wholesale dealer down by wheel model so product mix and monthly value trends can be compared inside one account.';
    }
}