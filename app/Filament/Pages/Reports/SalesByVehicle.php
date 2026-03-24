<?php

namespace App\Filament\Pages\Reports;

class SalesByVehicle extends AbstractSalesReportPage
{
    protected static ?string $navigationLabel = 'Sales by Vehicle';

    protected static ?string $title = 'Sales by Vehicle';

    protected static ?string $slug = 'reports/sales-by-vehicle';

    protected static ?int $navigationSort = 4;

    protected function groupExpression(): string
    {
        return "CONCAT_WS(' ', NULLIF(o.vehicle_make, ''), NULLIF(o.vehicle_model, ''), NULLIF(o.vehicle_sub_model, ''))";
    }

    protected function labelHeader(): string
    {
        return 'Vehicle';
    }

    protected function description(): string
    {
        return 'This report groups invoice activity by vehicle make, model, and sub model so the monthly demand profile can be reviewed at vehicle level.';
    }
}