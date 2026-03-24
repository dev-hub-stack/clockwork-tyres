<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Pages\Concerns\HasReportFilters;
use App\Services\ReportService;
use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

abstract class AbstractProfitReportPage extends Page
{
    use HasReportFilters;

    protected string $view = 'filament.pages.reports.profit-report';

    protected static string | UnitEnum | null $navigationGroup = 'Reports';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-banknotes';

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
        $rows = $reportService->profitByDimension(
            $this->groupExpression(),
            $this->reportStartDate(),
            $this->reportEndDate(),
            $this->getFiltersArray(),
        );

        return [
            'titleText' => static::$title,
            'kicker' => 'Reports / Profit Reports',
            'description' => $this->description(),
            'labelHeader' => $this->labelHeader(),
            'rows' => $this->applyMetricSort($rows, 'total_profit'),
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
                'sortOptions' => $this->sortOptions(),
            ],
        ];
    }

    protected function sortOptions(): array
    {
        return [
            'alpha' => 'Alphabetical A-Z',
            'value_desc' => 'Profit High to Low',
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