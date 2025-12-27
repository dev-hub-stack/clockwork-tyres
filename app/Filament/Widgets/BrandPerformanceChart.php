<?php

namespace App\Filament\Widgets;

use App\Modules\Products\Models\Brand;
use App\Modules\Orders\Models\OrderItem;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class BrandPerformanceChart extends ChartWidget
{
    protected static ?int $sort = 8;
    
    public ?string $filter = 'year';
    
    public function getHeading(): ?string
    {
        return 'Sales by Brand';
    }
    
    protected function getData(): array
    {
        $filter = $this->filter;
        
        // Extract brand from product_name (format: "Brand - Model Finish")
        // or from product_snapshot JSON
        $query = OrderItem::query()
            ->select(
                DB::raw("TRIM(SUBSTRING_INDEX(order_items.product_name, ' - ', 1)) as brand_name"),
                DB::raw('SUM(order_items.line_total) as revenue'),
                DB::raw('COUNT(DISTINCT order_items.id) as times_sold')
            )
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.external_source', 'tunerstop_historical')
            ->whereNotNull('order_items.product_name')
            ->where('order_items.product_name', '!=', '');
        
        // Apply date filter
        if ($filter === 'year') {
            $query->where('orders.created_at', '>=', now()->subYear());
        } elseif ($filter !== 'all') {
            $query->whereYear('orders.created_at', $filter);
        }
        
        $data = $query
            ->groupBy('brand_name')
            ->havingRaw('brand_name IS NOT NULL AND brand_name != ""')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get();
        
        return [
            'datasets' => [
                [
                    'label' => 'Revenue (AED)',
                    'data' => $data->pluck('revenue')->toArray(),
                    'backgroundColor' => [
                        'rgba(255, 99, 132, 0.7)',
                        'rgba(54, 162, 235, 0.7)',
                        'rgba(255, 206, 86, 0.7)',
                        'rgba(75, 192, 192, 0.7)',
                        'rgba(153, 102, 255, 0.7)',
                        'rgba(255, 159, 64, 0.7)',
                        'rgba(199, 199, 199, 0.7)',
                        'rgba(83, 102, 255, 0.7)',
                        'rgba(255, 99, 255, 0.7)',
                        'rgba(99, 255, 132, 0.7)',
                    ],
                ],
            ],
            'labels' => $data->pluck('brand_name')->toArray(),
        ];
    }
    
    protected function getType(): string
    {
        return 'bar';
    }
    
    protected function getFilters(): ?array
    {
        return [
            'year' => 'Last 12 Months',
            'all' => 'All Time',
            '2025' => '2025',
            '2024' => '2024',
            '2023' => '2023',
            '2022' => '2022',
            '2021' => '2021',
            '2020' => '2020',
        ];
    }
}
