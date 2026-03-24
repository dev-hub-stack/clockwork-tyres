<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Pages\Concerns\HasReportFilters;
use App\Services\ReportService;
use BackedEnum;
use Filament\Pages\Page;
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
        return auth()->user()?->can('view_reports') ?? false;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_reports') ?? false;
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
            ],
        ];
    }

    protected function teamFilters(): array
    {
        return [
            'channel' => $this->channel,
        ];
    }
}