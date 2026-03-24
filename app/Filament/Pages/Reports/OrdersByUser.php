<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Pages\Concerns\HasReportFilters;
use App\Services\ReportService;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Str;
use UnitEnum;

class OrdersByUser extends Page
{
    use HasReportFilters;

    protected string $view = 'filament.pages.reports.team-report';

    protected static ?string $navigationLabel = 'Orders by User';

    protected static ?string $title = 'Orders by User';

    protected static ?string $slug = 'reports/orders-by-user';

    protected static string | UnitEnum | null $navigationGroup = 'Reports';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-user-group';

    protected static ?int $navigationSort = 50;

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
        $rows = $reportService->teamPerformance(
            $this->reportStartDate(),
            $this->reportEndDate(),
            $this->teamFilters(),
        );

        $selectedUserId = filled(request()->query('selected_user_id'))
            ? (int) request()->query('selected_user_id')
            : $rows->firstWhere('user_id', '!==', null)['user_id'] ?? null;

        if (! $rows->contains(fn (array $row) => $row['user_id'] === $selectedUserId)) {
            $selectedUserId = $rows->firstWhere('user_id', '!==', null)['user_id'] ?? null;
        }

        $selectedUser = $rows->firstWhere('user_id', $selectedUserId);
        $detailRows = $reportService->userOrderDetails(
            $selectedUserId,
            $this->reportStartDate(),
            $this->reportEndDate(),
            $this->teamFilters(),
        );

        return [
            'titleText' => static::$title,
            'description' => 'This report compares invoice performance by assigned CRM representative and shows invoice-level detail for the selected user.',
            'rows' => $rows,
            'months' => $reportService->monthsBetween($this->reportStartDate(), $this->reportEndDate()),
            'selectedUserId' => $selectedUserId,
            'selectedUserName' => $selectedUser['label'] ?? null,
            'detailRows' => $detailRows,
            'detailTotals' => [
                'value' => $detailRows->sum('value'),
                'profit' => $detailRows->sum('profit'),
            ],
            'toolbar' => [
                'startMonth' => $this->startMonth,
                'endMonth' => $this->endMonth,
                'sort' => $this->sort,
                'channel' => $this->channel,
                'dealerId' => null,
                'userId' => null,
                'dealers' => [],
                'users' => [],
                'showDealerFilter' => false,
                'showUserFilter' => false,
                'showChannelFilter' => true,
                'sortOptions' => [
                    'alpha' => 'Alphabetical A-Z',
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
            && ($user?->can('view_team_reports') ?? false);
    }

    protected function canExportReports(): bool
    {
        return auth()->user()?->can('export_reports') ?? false;
    }

    protected function teamFilters(): array
    {
        return [
            'channel' => $this->channel,
        ];
    }

    protected function reportKey(): string
    {
        return Str::after((string) static::$slug, 'reports/');
    }
}