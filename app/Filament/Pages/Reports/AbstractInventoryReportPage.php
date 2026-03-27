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
        return static::canViewReportPage();
    }

    public static function canAccess(): bool
    {
        return static::canViewReportPage();
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
                'exportCsvUrl' => $this->canExportReports() ? route('admin.reports.export', array_merge(['report' => $this->reportKey(), 'format' => 'csv'], request()->query())) : null,
                'exportPdfUrl' => $this->canExportReports() ? route('admin.reports.export', array_merge(['report' => $this->reportKey(), 'format' => 'pdf'], request()->query())) : null,
            ],
        ];
    }

    protected static function canViewReportPage(): bool
    {
        $user = auth()->user();

        return ($user?->can('view_reports') ?? false)
            && ($user?->can(static::requiredReportPermission()) ?? false);
    }

    protected static function requiredReportPermission(): string
    {
        return 'view_inventory_reports';
    }

    protected function canExportReports(): bool
    {
        return auth()->user()?->can('export_reports') ?? false;
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