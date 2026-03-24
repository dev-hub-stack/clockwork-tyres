<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Pages\Concerns\HasReportFilters;
use App\Services\ReportService;
use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

abstract class AbstractSalesReportPage extends Page
{
    use HasReportFilters;

    protected string $view = 'filament.pages.reports.sales-report';

    protected static string | UnitEnum | null $navigationGroup = 'Reports';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-presentation-chart-line';

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
            $this->groupExpression(),
            $this->reportStartDate(),
            $this->reportEndDate(),
            $this->getFiltersArray(),
        );

        return [
            'titleText' => static::$title,
            'kicker' => 'Reports / Sales Reports',
            'description' => $this->description(),
            'labelHeader' => $this->labelHeader(),
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
                'showDealerFilter' => $this->showDealerFilter(),
                'showUserFilter' => $this->showUserFilter(),
                'showChannelFilter' => $this->showChannelFilter(),
            ],
        ];
    }

    protected function showDealerFilter(): bool
    {
        return true;
    }

    protected function showUserFilter(): bool
    {
        return true;
    }

    protected function showChannelFilter(): bool
    {
        return true;
    }

    abstract protected function groupExpression(): string;

    abstract protected function labelHeader(): string;

    abstract protected function description(): string;
}