<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Pages\Concerns\HasReportFilters;
use App\Services\ReportService;
use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

class SalesByBrand extends Page
{
    use HasReportFilters;

    protected string $view = 'filament.pages.reports.sales-by-brand';

    protected static ?string $navigationLabel = 'Sales by Brand';

    protected static ?string $title = 'Sales by Brand';

    protected static ?string $slug = 'reports/sales-by-brand';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static string | UnitEnum | null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 1;

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
        $rows = $reportService->salesByDimension(
            'oi.brand_name',
            $this->reportStartDate(),
            $this->reportEndDate(),
            $this->getFiltersArray(),
        );

        return [
            'rows' => $this->applySort($rows),
            'months' => $reportService->monthsBetween($this->reportStartDate(), $this->reportEndDate()),
            'toolbar' => [
                'startMonth' => $this->startMonth,
                'endMonth' => $this->endMonth,
                'sort' => $this->sort,
                'channel' => $this->channel,
                'dealerId' => $this->dealerId,
                'userId' => $this->userId,
                'dealers' => $this->dealerOptions(),
                'users' => $this->userOptions(),
                'showDealerFilter' => true,
                'showUserFilter' => true,
                'showChannelFilter' => true,
            ],
        ];
    }
}