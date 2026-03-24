<?php

namespace App\Filament\Pages\Reports;

class ProfitByVehicle extends AbstractProfitReportPage
{
    protected static ?string $navigationLabel = 'Profit by Vehicle';
    protected static ?string $title = 'Profit by Vehicle';
    protected static ?string $slug = 'reports/profit-by-vehicle';
    protected static ?int $navigationSort = 24;

    protected function groupExpression(): string { return "CONCAT_WS(' ', NULLIF(o.vehicle_make, ''), NULLIF(o.vehicle_model, ''), NULLIF(o.vehicle_sub_model, ''))"; }
    protected function labelHeader(): string { return 'Vehicle'; }
    protected function description(): string { return 'This report allocates invoice profit to vehicle groupings based on the sold line items and recorded vehicle data.'; }
}