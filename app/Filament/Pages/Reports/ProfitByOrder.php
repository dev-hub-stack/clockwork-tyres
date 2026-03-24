<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Pages\Concerns\HasReportFilters;
use App\Services\ReportService;
use BackedEnum;
use Filament\Pages\Page;
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
        return auth()->user()?->can('view_reports') ?? false;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_reports') ?? false;
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
            ],
        ];
    }
}