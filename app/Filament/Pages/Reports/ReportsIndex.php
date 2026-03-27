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
        return static::canViewAnyReportModule();
    }

    public static function canAccess(): bool
    {
        return static::canViewAnyReportModule();
    }

    protected function getViewData(): array
    {
        $reportService = app(ReportService::class);
        $user = auth()->user();

        $canViewSales = $user?->can('view_sales_reports') ?? false;
        $canViewProfit = $user?->can('view_profit_reports') ?? false;
        $canViewInventory = $user?->can('view_inventory_reports') ?? false;
        $canViewDealer = $user?->can('view_dealer_reports') ?? false;
        $canViewTeam = $user?->can('view_team_reports') ?? false;

        return [
            'cards' => $reportService->summaryCards($this->reportStartDate(), $this->reportEndDate()),
            'startMonth' => $this->startMonth,
            'endMonth' => $this->endMonth,
            'pageDescription' => 'The reporting module now covers sales, profit, inventory, dealer, and team views on top of CRM invoice data, with shared CSV and PDF exports across the active report pages. Deferred website tracking reports can be added on top of this shared reporting foundation.',
            'reports' => collect([
                'Sales Reports' => [
                    ['label' => 'Sales by Brand', 'url' => $canViewSales ? SalesByBrand::getUrl() : null, 'enabled' => $canViewSales],
                    ['label' => 'Sales by Model', 'url' => $canViewSales ? SalesByModel::getUrl() : null, 'enabled' => $canViewSales],
                    ['label' => 'Sales by Size', 'url' => $canViewSales ? SalesBySize::getUrl() : null, 'enabled' => $canViewSales],
                    ['label' => 'Sales by Vehicle', 'url' => $canViewSales ? SalesByVehicle::getUrl() : null, 'enabled' => $canViewSales],
                    ['label' => 'Sales by Dealer', 'url' => $canViewSales ? SalesByDealer::getUrl() : null, 'enabled' => $canViewSales],
                    ['label' => 'Sales by SKU', 'url' => $canViewSales ? SalesBySku::getUrl() : null, 'enabled' => $canViewSales],
                    ['label' => 'Sales by Channel', 'url' => $canViewSales ? SalesByChannel::getUrl() : null, 'enabled' => $canViewSales],
                    ['label' => 'Sales by Team', 'url' => $canViewSales ? SalesByTeam::getUrl() : null, 'enabled' => $canViewSales],
                    ['label' => 'Sales by Categories', 'url' => $canViewSales ? SalesByCategories::getUrl() : null, 'enabled' => $canViewSales],
                ],
                'Profit Reports' => [
                    ['label' => 'Profit by Order', 'url' => $canViewProfit ? ProfitByOrder::getUrl() : null, 'enabled' => $canViewProfit],
                    ['label' => 'Profit by Brand', 'url' => $canViewProfit ? ProfitByBrand::getUrl() : null, 'enabled' => $canViewProfit],
                    ['label' => 'Profit by Model', 'url' => $canViewProfit ? ProfitByModel::getUrl() : null, 'enabled' => $canViewProfit],
                    ['label' => 'Profit by Size', 'url' => $canViewProfit ? ProfitBySize::getUrl() : null, 'enabled' => $canViewProfit],
                    ['label' => 'Profit by Vehicle', 'url' => $canViewProfit ? ProfitByVehicle::getUrl() : null, 'enabled' => $canViewProfit],
                    ['label' => 'Profit by Dealer', 'url' => $canViewProfit ? ProfitByDealer::getUrl() : null, 'enabled' => $canViewProfit],
                    ['label' => 'Profit by SKU', 'url' => $canViewProfit ? ProfitBySku::getUrl() : null, 'enabled' => $canViewProfit],
                    ['label' => 'Profit by Month', 'url' => $canViewProfit ? ProfitByMonth::getUrl() : null, 'enabled' => $canViewProfit],
                    ['label' => 'Profit by Salesman', 'url' => $canViewProfit ? ProfitBySalesman::getUrl() : null, 'enabled' => $canViewProfit],
                    ['label' => 'Profit by Channel', 'url' => $canViewProfit ? ProfitByChannel::getUrl() : null, 'enabled' => $canViewProfit],
                    ['label' => 'Profit by Categories', 'url' => $canViewProfit ? ProfitByCategories::getUrl() : null, 'enabled' => $canViewProfit],
                ],
                'Inventory Reports' => [
                    ['label' => 'Inventory by SKU', 'url' => $canViewInventory ? InventoryBySku::getUrl() : null, 'enabled' => $canViewInventory],
                    ['label' => 'Inventory by Brand', 'url' => $canViewInventory ? InventoryByBrand::getUrl() : null, 'enabled' => $canViewInventory],
                    ['label' => 'Inventory by Model', 'url' => $canViewInventory ? InventoryByModel::getUrl() : null, 'enabled' => $canViewInventory],
                ],
                'Dealer Reports' => [
                    ['label' => 'Dealer Sales by Brand', 'url' => $canViewDealer ? DealerSalesByBrand::getUrl() : null, 'enabled' => $canViewDealer],
                    ['label' => 'Dealer Sales by Model', 'url' => $canViewDealer ? DealerSalesByModel::getUrl() : null, 'enabled' => $canViewDealer],
                    ['label' => 'Dealer Vehicle Searches', 'url' => null, 'enabled' => false],
                ],
                'Website Reports' => [
                    ['label' => 'Deferred pending tracking data', 'url' => null, 'enabled' => false],
                ],
                'Team Reports' => [
                    ['label' => 'Orders by User', 'url' => $canViewTeam ? OrdersByUser::getUrl() : null, 'enabled' => $canViewTeam],
                ],
            ])->filter(fn (array $items, string $section) => $section === 'Website Reports' || collect($items)->contains(fn (array $item) => $item['enabled'] || $item['url'] === null))->all(),
        ];
    }

    protected static function canViewAnyReportModule(): bool
    {
        $user = auth()->user();

        return ($user?->can('view_reports') ?? false)
            && ($user?->hasAnyPermission([
                'view_sales_reports',
                'view_profit_reports',
                'view_inventory_reports',
                'view_dealer_reports',
                'view_team_reports',
            ]) ?? false);
    }
}