<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Pages\Concerns\HasReportFilters;
use App\Services\ReportService;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Str;
use UnitEnum;

abstract class AbstractInventoryReportPage extends Page
{
    use HasReportFilters;

    protected string $view = 'filament.pages.reports.inventory-report';

    protected static string | UnitEnum | null $navigationGroup = 'Reports';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-archive-box';

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
        $rows = $reportService->inventoryByDimension(
            $this->inventoryGroupExpression(),
            $this->salesGroupExpression(),
            $this->reportStartDate(),
            $this->reportEndDate(),
            $this->getFiltersArray(),
        );

        return [
            'titleText' => static::$title,
            'kicker' => 'Reports / Inventory Reports',
            'description' => $this->description(),
            'labelHeader' => $this->labelHeader(),
            'rows' => $this->applyInventorySort($rows),
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
                'sortOptions' => [
                    'alpha' => 'Alphabetical A-Z',
                    'qty_desc' => 'Sold High to Low',
                    'value_desc' => 'Added High to Low',
                ],
                'exportCsvUrl' => route('admin.reports.export', array_merge(['report' => $this->reportKey(), 'format' => 'csv'], request()->query())),
                'exportPdfUrl' => route('admin.reports.export', array_merge(['report' => $this->reportKey(), 'format' => 'pdf'], request()->query())),
            ],
        ];
    }

    protected function reportKey(): string
    {
        return Str::after((string) static::$slug, 'reports/');
    }

    abstract protected function inventoryGroupExpression(): string;

    abstract protected function salesGroupExpression(): string;

    abstract protected function labelHeader(): string;

    abstract protected function description(): string;
}