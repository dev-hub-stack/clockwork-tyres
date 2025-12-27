<?php

namespace App\Filament\Widgets;

use App\Modules\Products\Models\Product;
use App\Modules\Orders\Models\OrderItem;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class TopProductsChart extends ChartWidget
{
    protected static ?int $sort = 3;
    
    public ?string $filter = 'year';
    
    public function getHeading(): ?string
    {
        return 'Top 10 Products by Revenue';
    }
    
    protected function getData(): array
    {
        $filter = $this->filter;
        
        $query = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->where('orders.external_source', 'tunerstop_historical');
        
        // Apply date filter
        if ($filter === 'year') {
            $query->where('orders.created_at', '>=', now()->subYear());
        } elseif ($filter !== 'all') {
            $query->whereYear('orders.created_at', $filter);
        }
        
        $data = $query
            ->select(
                'products.name',
                'products.sku',
                DB::raw('SUM(order_items.line_total) as revenue'),
                DB::raw('SUM(order_items.quantity) as quantity_sold')
            )
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get();
        
        // Truncate long product names for better display
        $labels = $data->map(function ($item) {
            $name = $item->name;
            if (strlen($name) > 30) {
                $name = substr($name, 0, 27) . '...';
            }
            return $name;
        })->toArray();
        
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
            'labels' => $labels,
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
    
    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y', // Horizontal bar chart
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
            'scales' => [
                'x' => [
                    'ticks' => [
                        'callback' => 'function(value) { return "AED " + value.toLocaleString(); }',
                    ],
                ],
            ],
        ];
    }
}
