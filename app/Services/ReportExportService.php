<?php

namespace App\Services;

use App\Modules\Customers\Models\Customer;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ReportExportService
{
    public function __construct(private readonly ReportService $reportService)
    {
    }

    public function build(string $reportKey, array $query): array
    {
        [$startMonth, $endMonth] = $this->normalizeMonths($query);
        $startDate = Carbon::createFromFormat('Y-m', $startMonth)->startOfMonth();
        $endDate = Carbon::createFromFormat('Y-m', $endMonth)->endOfMonth();

        if ($config = $this->salesConfigs()[$reportKey] ?? null) {
            $filters = $this->normalizeStandardFilters($query, $config);
            $rows = $this->applySalesSort(
                $this->reportService->salesByDimension($config['group'], $startDate, $endDate, $filters),
                (string) ($query['sort'] ?? 'alpha'),
            );

            return [
                'type' => 'pivot',
                'mode' => 'sales',
                'reportKey' => $reportKey,
                'title' => $config['title'],
                'description' => $config['description'],
                'labelHeader' => $config['label'],
                'months' => $this->reportService->monthsBetween($startDate, $endDate),
                'rows' => $rows,
                'filters' => $this->filterSummary($startMonth, $endMonth, $filters),
            ];
        }

        if ($config = $this->profitConfigs()[$reportKey] ?? null) {
            $filters = $this->normalizeStandardFilters($query, $config);
            $rows = $this->applyProfitSort(
                $this->reportService->profitByDimension($config['group'], $startDate, $endDate, $filters),
                (string) ($query['sort'] ?? 'alpha'),
            );

            return [
                'type' => 'pivot',
                'mode' => 'profit',
                'reportKey' => $reportKey,
                'title' => $config['title'],
                'description' => $config['description'],
                'labelHeader' => $config['label'],
                'months' => $this->reportService->monthsBetween($startDate, $endDate),
                'rows' => $rows,
                'filters' => $this->filterSummary($startMonth, $endMonth, $filters),
            ];
        }

        if ($config = $this->inventoryConfigs()[$reportKey] ?? null) {
            $filters = $this->normalizeStandardFilters($query, $config);
            $rows = $this->applyInventorySort(
                $this->reportService->inventoryByDimension($config['inventory_group'], $config['sales_group'], $startDate, $endDate, $filters),
                (string) ($query['sort'] ?? 'alpha'),
            );

            return [
                'type' => 'pivot',
                'mode' => 'inventory',
                'reportKey' => $reportKey,
                'title' => $config['title'],
                'description' => $config['description'],
                'labelHeader' => $config['label'],
                'months' => $this->reportService->monthsBetween($startDate, $endDate),
                'rows' => $rows,
                'filters' => $this->filterSummary($startMonth, $endMonth, $filters),
            ];
        }

        if ($reportKey === 'profit-by-order') {
            $filters = $this->normalizeStandardFilters($query);
            $rows = $this->reportService->profitByOrder($startDate, $endDate, $filters);

            return [
                'type' => 'orders',
                'mode' => 'profit-order',
                'reportKey' => $reportKey,
                'title' => 'Profit by Order',
                'description' => 'Invoice-level totals and recorded gross profit for the selected period.',
                'rows' => $rows,
                'totals' => [
                    'value' => $rows->sum('value'),
                    'profit' => $rows->sum('profit'),
                ],
                'filters' => $this->filterSummary($startMonth, $endMonth, $filters),
            ];
        }

        if ($reportKey === 'orders-by-user') {
            $filters = [
                'channel' => (string) ($query['channel'] ?? 'all'),
            ];
            $rows = $this->reportService->teamPerformance($startDate, $endDate, $filters);
            $selectedUserId = filled($query['selected_user_id'] ?? null)
                ? (int) $query['selected_user_id']
                : $this->firstTeamUserId($rows);

            if (! $rows->contains(fn (array $row) => $row['user_id'] === $selectedUserId)) {
                $selectedUserId = $this->firstTeamUserId($rows);
            }

            $selectedUser = $rows->firstWhere('user_id', $selectedUserId);
            $detailRows = $this->reportService->userOrderDetails($selectedUserId, $startDate, $endDate, $filters);

            return [
                'type' => 'team',
                'mode' => 'team',
                'reportKey' => $reportKey,
                'title' => 'Orders by User',
                'description' => 'Comparison table by representative plus invoice-level detail for the selected user.',
                'months' => $this->reportService->monthsBetween($startDate, $endDate),
                'rows' => $rows,
                'selectedUserId' => $selectedUserId,
                'selectedUserName' => $selectedUser['label'] ?? null,
                'detailRows' => $detailRows,
                'detailTotals' => [
                    'value' => $detailRows->sum('value'),
                    'profit' => $detailRows->sum('profit'),
                ],
                'filters' => $this->filterSummary($startMonth, $endMonth, $filters),
            ];
        }

        throw new NotFoundHttpException('Unknown report export requested.');
    }

    public function requiredPermission(string $reportKey): string
    {
        if (array_key_exists($reportKey, $this->salesConfigs())) {
            return str_starts_with($reportKey, 'dealer-') ? 'view_dealer_reports' : 'view_sales_reports';
        }

        if (array_key_exists($reportKey, $this->profitConfigs()) || $reportKey === 'profit-by-order') {
            return 'view_profit_reports';
        }

        if (array_key_exists($reportKey, $this->inventoryConfigs())) {
            return 'view_inventory_reports';
        }

        if ($reportKey === 'orders-by-user') {
            return 'view_team_reports';
        }

        throw new NotFoundHttpException('Unknown report export requested.');
    }

    public function csvFilename(array $payload): string
    {
        return Str::slug($payload['title']) . '-' . now()->format('Ymd-His') . '.csv';
    }

    public function pdfFilename(array $payload): string
    {
        return Str::slug($payload['title']) . '-' . now()->format('Ymd-His') . '.pdf';
    }

    private function normalizeMonths(array $query): array
    {
        $now = now();
        $startMonth = (string) ($query['start_month'] ?? $now->copy()->startOfYear()->format('Y-m'));
        $endMonth = (string) ($query['end_month'] ?? $now->copy()->format('Y-m'));

        if ($startMonth > $endMonth) {
            [$startMonth, $endMonth] = [$endMonth, $startMonth];
        }

        return [$startMonth, $endMonth];
    }

    private function normalizeStandardFilters(array $query, array $config = []): array
    {
        $filters = [
            'channel' => (string) ($query['channel'] ?? 'all'),
            'dealer_id' => filled($query['dealer_id'] ?? null) ? (int) $query['dealer_id'] : null,
            'user_id' => filled($query['user_id'] ?? null) ? (int) $query['user_id'] : null,
        ];

        if (isset($config['force_channel'])) {
            $filters['channel'] = $config['force_channel'];
        }

        if (! empty($config['default_wholesale_dealer']) && empty($filters['dealer_id'])) {
            $filters['dealer_id'] = Customer::query()
                ->where('customer_type', 'wholesale')
                ->value('id');
        }

        return $filters;
    }

    private function filterSummary(string $startMonth, string $endMonth, array $filters): array
    {
        $dealerName = null;
        if (! empty($filters['dealer_id'])) {
            $dealerName = Customer::query()->find($filters['dealer_id'])?->name;
        }

        $userName = null;
        if (! empty($filters['user_id'])) {
            $userName = User::query()->whereKey($filters['user_id'])->value('name');
        }

        return [
            'startMonth' => $startMonth,
            'endMonth' => $endMonth,
            'channel' => $filters['channel'] ?? 'all',
            'dealer' => $dealerName,
            'user' => $userName,
        ];
    }

    private function firstTeamUserId(Collection $rows): ?int
    {
        $first = $rows->first(fn (array $row) => $row['user_id'] !== null);

        return $first['user_id'] ?? null;
    }

    private function applySalesSort(Collection $rows, string $sort): Collection
    {
        return match ($sort) {
            'qty_desc' => $rows->sortByDesc('total_qty')->values(),
            'value_desc' => $rows->sortByDesc('total_value')->values(),
            default => $rows->sortBy(fn (array $row) => mb_strtolower($row['label']))->values(),
        };
    }

    private function applyProfitSort(Collection $rows, string $sort): Collection
    {
        return match ($sort) {
            'value_desc' => $rows->sortByDesc('total_profit')->values(),
            default => $rows->sortBy(fn (array $row) => mb_strtolower($row['label']))->values(),
        };
    }

    private function applyInventorySort(Collection $rows, string $sort): Collection
    {
        return match ($sort) {
            'qty_desc' => $rows->sortByDesc('total_sold')->values(),
            'value_desc' => $rows->sortByDesc('total_added')->values(),
            default => $rows->sortBy(fn (array $row) => mb_strtolower($row['label']))->values(),
        };
    }

    private function salesConfigs(): array
    {
        return [
            'sales-by-brand' => ['title' => 'Sales by Brand', 'label' => 'Brand', 'group' => 'oi.brand_name', 'description' => 'Monthly quantity and value by brand.'],
            'sales-by-model' => ['title' => 'Sales by Model', 'label' => 'Model', 'group' => 'oi.model_name', 'description' => 'Monthly quantity and value by model.'],
            'sales-by-size' => ['title' => 'Sales by Size', 'label' => 'Size', 'group' => "JSON_UNQUOTE(JSON_EXTRACT(oi.item_attributes, '$.size'))", 'description' => 'Monthly quantity and value by size.'],
            'sales-by-vehicle' => ['title' => 'Sales by Vehicle', 'label' => 'Vehicle', 'group' => "CONCAT_WS(' ', NULLIF(o.vehicle_make, ''), NULLIF(o.vehicle_model, ''), NULLIF(o.vehicle_sub_model, ''))", 'description' => 'Monthly quantity and value by vehicle.'],
            'sales-by-dealer' => ['title' => 'Sales by Dealer', 'label' => 'Dealer', 'group' => "COALESCE(NULLIF(c.business_name, ''), CONCAT_WS(' ', NULLIF(c.first_name, ''), NULLIF(c.last_name, '')))", 'description' => 'Monthly quantity and value by dealer.'],
            'sales-by-sku' => ['title' => 'Sales by SKU', 'label' => 'SKU', 'group' => 'oi.sku', 'description' => 'Monthly quantity and value by SKU.'],
            'sales-by-channel' => ['title' => 'Sales by Channel', 'label' => 'Channel', 'group' => 'o.external_source', 'description' => 'Monthly quantity and value by sales channel.'],
            'sales-by-team' => ['title' => 'Sales by Team', 'label' => 'User', 'group' => 'u.name', 'description' => 'Monthly quantity and value by representative.'],
            'sales-by-categories' => ['title' => 'Sales by Categories', 'label' => 'Category', 'group' => "CASE WHEN oi.add_on_id IS NOT NULL THEN 'Accessories' ELSE 'Wheels' END", 'description' => 'Monthly quantity and value by category.'],
            'dealer-sales-by-brand' => ['title' => 'Dealer Sales by Brand', 'label' => 'Brand', 'group' => 'oi.brand_name', 'description' => 'Monthly quantity and value for a selected wholesale dealer by brand.', 'force_channel' => 'wholesale', 'default_wholesale_dealer' => true],
            'dealer-sales-by-model' => ['title' => 'Dealer Sales by Model', 'label' => 'Model', 'group' => "COALESCE(NULLIF(oi.model_name, ''), 'Unknown Model')", 'description' => 'Monthly quantity and value for a selected wholesale dealer by model.', 'force_channel' => 'wholesale', 'default_wholesale_dealer' => true],
        ];
    }

    private function profitConfigs(): array
    {
        return [
            'profit-by-brand' => ['title' => 'Profit by Brand', 'label' => 'Brand', 'group' => 'oi.brand_name', 'description' => 'Monthly allocated profit by brand.'],
            'profit-by-model' => ['title' => 'Profit by Model', 'label' => 'Model', 'group' => 'oi.model_name', 'description' => 'Monthly allocated profit by model.'],
            'profit-by-size' => ['title' => 'Profit by Size', 'label' => 'Size', 'group' => "JSON_UNQUOTE(JSON_EXTRACT(oi.item_attributes, '$.size'))", 'description' => 'Monthly allocated profit by size.'],
            'profit-by-vehicle' => ['title' => 'Profit by Vehicle', 'label' => 'Vehicle', 'group' => "CONCAT_WS(' ', NULLIF(o.vehicle_make, ''), NULLIF(o.vehicle_model, ''), NULLIF(o.vehicle_sub_model, ''))", 'description' => 'Monthly allocated profit by vehicle.'],
            'profit-by-dealer' => ['title' => 'Profit by Dealer', 'label' => 'Dealer', 'group' => "COALESCE(NULLIF(c.business_name, ''), CONCAT_WS(' ', NULLIF(c.first_name, ''), NULLIF(c.last_name, '')))", 'description' => 'Monthly allocated profit by dealer.'],
            'profit-by-sku' => ['title' => 'Profit by SKU', 'label' => 'SKU', 'group' => 'oi.sku', 'description' => 'Monthly allocated profit by SKU.'],
            'profit-by-month' => ['title' => 'Profit by Month', 'label' => 'Month', 'group' => "DATE_FORMAT(COALESCE(o.issue_date, o.created_at), '%b %Y')", 'description' => 'Profit summarized by month.'],
            'profit-by-salesman' => ['title' => 'Profit by Salesman', 'label' => 'Salesman', 'group' => 'u.name', 'description' => 'Monthly allocated profit by representative.'],
            'profit-by-channel' => ['title' => 'Profit by Channel', 'label' => 'Channel', 'group' => 'o.external_source', 'description' => 'Monthly allocated profit by channel.'],
            'profit-by-categories' => ['title' => 'Profit by Categories', 'label' => 'Category', 'group' => "CASE WHEN oi.add_on_id IS NOT NULL THEN 'Accessories' ELSE 'Wheels' END", 'description' => 'Monthly allocated profit by category.'],
        ];
    }

    private function inventoryConfigs(): array
    {
        return [
            'inventory-by-sku' => ['title' => 'Inventory by SKU', 'label' => 'SKU', 'inventory_group' => "COALESCE(NULLIF(pv.sku, ''), NULLIF(p.sku, ''))", 'sales_group' => 'oi.sku', 'description' => 'Added versus sold by SKU.'],
            'inventory-by-brand' => ['title' => 'Inventory by Brand', 'label' => 'Brand', 'inventory_group' => 'b.name', 'sales_group' => 'oi.brand_name', 'description' => 'Added versus sold by brand.'],
            'inventory-by-model' => ['title' => 'Inventory by Model', 'label' => 'Model', 'inventory_group' => 'm.name', 'sales_group' => 'oi.model_name', 'description' => 'Added versus sold by model.'],
        ];
    }
}