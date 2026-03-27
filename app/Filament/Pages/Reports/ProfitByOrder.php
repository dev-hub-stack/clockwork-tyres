<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Pages\Concerns\HasReportFilters;
use App\Services\ReportService;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Str;
use UnitEnum;

class ProfitByOrder extends Page
{
    use HasReportFilters;

    protected string $view = 'filament.pages.reports.profit-by-order';

    protected static ?string $navigationLabel = 'Profit by Order';

    protected static ?string $title = 'Profit by Order';

    protected static ?string $slug = 'reports/profit-by-order';

    protected static string | UnitEnum | null $navigationGroup = 'Reports';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?int $navigationSort = 20;

    public function mount(): void
    {
        $this->initializeReportFilters();

        if (! request()->has('start_month') && ! request()->has('end_month')) {
            $lastMonth = now()->subMonthNoOverflow();
            $this->startMonth = $lastMonth->format('Y-m');
            $this->endMonth = $lastMonth->format('Y-m');
        }
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
        $rows = app(ReportService::class)->profitByOrder(
            $this->reportStartDate(),
            $this->reportEndDate(),
            $this->getFiltersArray(),
        );

        return [
            'rows' => $rows,
            'totals' => [
                'value' => $rows->sum('value'),
                'profit' => $rows->sum('profit'),
            ],
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
                    'alpha' => 'Invoice A-Z',
                    'value_desc' => 'Value High to Low',
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
            && ($user?->can('view_profit_reports') ?? false);
    }

    protected function canExportReports(): bool
    {
        return auth()->user()?->can('export_reports') ?? false;
    }

    protected function reportKey(): string
    {
        return Str::after((string) static::$slug, 'reports/');
    }
}