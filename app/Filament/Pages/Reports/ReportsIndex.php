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
                    ['label' => 'Sales by Model', 'url' => null, 'enabled' => false],
                    ['label' => 'Sales by Size', 'url' => null, 'enabled' => false],
                    ['label' => 'Sales by Vehicle', 'url' => null, 'enabled' => false],
                    ['label' => 'Sales by Dealer', 'url' => null, 'enabled' => false],
                    ['label' => 'Sales by SKU', 'url' => null, 'enabled' => false],
                    ['label' => 'Sales by Channel', 'url' => null, 'enabled' => false],
                    ['label' => 'Sales by Team', 'url' => null, 'enabled' => false],
                ],
                'Profit Reports' => [
                    ['label' => 'Planned in next slice', 'url' => null, 'enabled' => false],
                ],
                'Inventory Reports' => [
                    ['label' => 'Planned in next slice', 'url' => null, 'enabled' => false],
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