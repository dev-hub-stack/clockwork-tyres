<?php

namespace App\Filament\Widgets;

use App\Modules\Orders\Models\Order;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class RevenueByMonthChart extends ChartWidget
{
    protected static ?int $sort = 2;
    
    public ?string $filter = 'year';
    
    public function getHeading(): ?string
    {
        return 'Revenue Trend (Monthly)';
    }
    
    protected function getData(): array
    {
        $filter = $this->filter;
        
        if ($filter === 'all') {
            return $this->getAllTimeData();
        } elseif ($filter === 'year') {
            return $this->getLastYearData();
        } else {
            return $this->getYearData($filter);
        }
    }
    
    protected function getType(): string
    {
        return 'line';
    }
    
    protected function getFilters(): ?array
    {
        return [
            'year' => 'Last 12 Months',
            'all' => 'All Time (By Year)',
            '2025' => '2025',
            '2024' => '2024',
            '2023' => '2023',
            '2022' => '2022',
            '2021' => '2021',
            '2020' => '2020',
        ];
    }
    
    /**
     * Get last 12 months data
     */
    protected function getLastYearData(): array
    {
        $startDate = now()->subYear();
        $endDate = now();
        
        $data = Order::where('external_source', 'tunerstop_historical')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select(
                DB::raw('DATE_FORMAT(created_at, "%b %Y") as month'),
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month_sort'),
                DB::raw('SUM(total) as revenue'),
                DB::raw('COUNT(*) as orders')
            )
            ->groupBy('month', 'month_sort')
            ->orderBy('month_sort')
            ->get();
        
        return [
            'datasets' => [
                [
                    'label' => 'Revenue (AED)',
                    'data' => $data->pluck('revenue')->toArray(),
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                ],
            ],
            'labels' => $data->pluck('month')->toArray(),
        ];
    }
    
    /**
     * Get specific year data (monthly breakdown)
     */
    protected function getYearData(string $year): array
    {
        $data = Order::where('external_source', 'tunerstop_historical')
            ->whereYear('created_at', $year)
            ->select(
                DB::raw('DATE_FORMAT(created_at, "%b") as month'),
                DB::raw('MONTH(created_at) as month_num'),
                DB::raw('SUM(total) as revenue'),
                DB::raw('COUNT(*) as orders')
            )
            ->groupBy('month', 'month_num')
            ->orderBy('month_num')
            ->get();
        
        return [
            'datasets' => [
                [
                    'label' => "Revenue (AED) - {$year}",
                    'data' => $data->pluck('revenue')->toArray(),
                    'borderColor' => 'rgb(34, 197, 94)',
                    'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                    'fill' => true,
                ],
            ],
            'labels' => $data->pluck('month')->toArray(),
        ];
    }
    
    /**
     * Get all time data (yearly breakdown)
     */
    protected function getAllTimeData(): array
    {
        $data = Order::where('external_source', 'tunerstop_historical')
            ->select(
                DB::raw('YEAR(created_at) as year'),
                DB::raw('SUM(total) as revenue'),
                DB::raw('COUNT(*) as orders')
            )
            ->groupBy('year')
            ->orderBy('year')
            ->get();
        
        return [
            'datasets' => [
                [
                    'label' => 'Revenue (AED)',
                    'data' => $data->pluck('revenue')->toArray(),
                    'borderColor' => 'rgb(168, 85, 247)',
                    'backgroundColor' => 'rgba(168, 85, 247, 0.1)',
                    'fill' => true,
                ],
            ],
            'labels' => $data->pluck('year')->toArray(),
        ];
    }
}
