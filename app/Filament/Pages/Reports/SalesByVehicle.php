<?php

namespace App\Filament\Pages\Reports;

use Illuminate\Support\Facades\DB;

class SalesByVehicle extends AbstractSalesReportPage
{
    protected static ?string $navigationLabel = 'Sales by Vehicle';

    protected static ?string $title = 'Sales by Vehicle';

    protected static ?string $slug = 'reports/sales-by-vehicle';

    protected static ?int $navigationSort = 4;

    protected function groupExpression(): string
    {
        return DB::getDriverName() === 'sqlite'
            ? "TRIM(COALESCE(o.vehicle_make, '') || ' ' || COALESCE(o.vehicle_model, '') || ' ' || COALESCE(o.vehicle_sub_model, ''))"
            : "CONCAT_WS(' ', NULLIF(o.vehicle_make, ''), NULLIF(o.vehicle_model, ''), NULLIF(o.vehicle_sub_model, ''))";
    }

    protected function labelHeader(): string
    {
        return 'Vehicle';
    }

    protected function description(): string
    {
        return 'This report groups invoice activity by vehicle make, model, and sub model so the monthly demand profile can be reviewed at vehicle level.';
    }

    protected function showQtyDrilldown(): bool
    {
        return true;
    }

    protected function quantityAggregation(): string
    {
        return 'invoice_count';
    }

    protected function quantityHeader(): string
    {
        return 'Invoices';
    }
}