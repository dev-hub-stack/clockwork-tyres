<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Pages\Concerns\HasReportFilters;
use App\Services\ReportService;
use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

class ReportsIndex extends Page
{
    use HasReportFilters;

    protected string $view = 'filament.pages.reports.index';

    protected static ?string $navigationLabel = 'Reports';

    protected static ?string $title = 'Reports';

    protected static ?string $slug = 'reports';

    protected static string | UnitEnum | null $navigationGroup = 'Reports';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?int $navigationSort = 0;

    public function mount(): void
    {
        $this->initializeReportFilters();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->can('view_reports') ?? false;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_reports') ?? false;
    }

    protected function getViewData(): array
    {
        $reportService = app(ReportService::class);

        return [
            'cards' => $reportService->summaryCards($this->reportStartDate(), $this->reportEndDate()),
            'startMonth' => $this->startMonth,
            'endMonth' => $this->endMonth,
            'reports' => [
                'Sales Reports' => [
                    ['label' => 'Sales by Brand', 'url' => SalesByBrand::getUrl(), 'enabled' => true],
                    ['label' => 'Sales by Model', 'url' => SalesByModel::getUrl(), 'enabled' => true],
                    ['label' => 'Sales by Size', 'url' => SalesBySize::getUrl(), 'enabled' => true],
                    ['label' => 'Sales by Vehicle', 'url' => SalesByVehicle::getUrl(), 'enabled' => true],
                    ['label' => 'Sales by Dealer', 'url' => SalesByDealer::getUrl(), 'enabled' => true],
                    ['label' => 'Sales by SKU', 'url' => SalesBySku::getUrl(), 'enabled' => true],
                    ['label' => 'Sales by Channel', 'url' => SalesByChannel::getUrl(), 'enabled' => true],
                    ['label' => 'Sales by Team', 'url' => SalesByTeam::getUrl(), 'enabled' => true],
                    ['label' => 'Sales by Categories', 'url' => SalesByCategories::getUrl(), 'enabled' => true],
                ],
                'Profit Reports' => [
                    ['label' => 'Profit by Order', 'url' => ProfitByOrder::getUrl(), 'enabled' => true],
                    ['label' => 'Profit by Brand', 'url' => ProfitByBrand::getUrl(), 'enabled' => true],
                    ['label' => 'Profit by Model', 'url' => ProfitByModel::getUrl(), 'enabled' => true],
                    ['label' => 'Profit by Size', 'url' => ProfitBySize::getUrl(), 'enabled' => true],
                    ['label' => 'Profit by Vehicle', 'url' => ProfitByVehicle::getUrl(), 'enabled' => true],
                    ['label' => 'Profit by Dealer', 'url' => ProfitByDealer::getUrl(), 'enabled' => true],
                    ['label' => 'Profit by SKU', 'url' => ProfitBySku::getUrl(), 'enabled' => true],
                    ['label' => 'Profit by Month', 'url' => ProfitByMonth::getUrl(), 'enabled' => true],
                    ['label' => 'Profit by Salesman', 'url' => ProfitBySalesman::getUrl(), 'enabled' => true],
                    ['label' => 'Profit by Channel', 'url' => ProfitByChannel::getUrl(), 'enabled' => true],
                    ['label' => 'Profit by Categories', 'url' => ProfitByCategories::getUrl(), 'enabled' => true],
                ],
                'Inventory Reports' => [
                    ['label' => 'Inventory by SKU', 'url' => InventoryBySku::getUrl(), 'enabled' => true],
                    ['label' => 'Inventory by Brand', 'url' => InventoryByBrand::getUrl(), 'enabled' => true],
                    ['label' => 'Inventory by Model', 'url' => InventoryByModel::getUrl(), 'enabled' => true],
                ],
                'Dealer Reports' => [
                    ['label' => 'Planned in next slice', 'url' => null, 'enabled' => false],
                ],
                'Website Reports' => [
                    ['label' => 'Deferred pending tracking data', 'url' => null, 'enabled' => false],
                ],
                'Team Reports' => [
                    ['label' => 'Planned in later slice', 'url' => null, 'enabled' => false],
                ],
            ],
        ];
    }
}