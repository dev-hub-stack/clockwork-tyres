<?php

namespace App\Filament\Widgets;

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
        
        // Build date filter condition
        $dateCondition = '';
        if ($filter === 'year') {
            $dateCondition = "AND orders.created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
        } elseif ($filter !== 'all') {
            $dateCondition = "AND YEAR(orders.created_at) = " . intval($filter);
        }
        
        // Use raw SQL to avoid MySQL strict mode issues
        $sql = "
            SELECT 
                brand_derived as brand_name,
                SUM(line_total) as revenue,
                COUNT(*) as times_sold
            FROM (
                SELECT 
                    order_items.line_total,
                    COALESCE(
                        NULLIF(order_items.brand_name, ''), 
                        TRIM(SUBSTRING_INDEX(order_items.product_name, ' - ', 1))
                    ) as brand_derived
                FROM order_items
                INNER JOIN orders ON order_items.order_id = orders.id
                WHERE orders.external_source = 'tunerstop_historical'
                {$dateCondition}
            ) as subquery
            WHERE brand_derived IS NOT NULL AND brand_derived != ''
            GROUP BY brand_derived
            ORDER BY revenue DESC
            LIMIT 10
        ";
        
        $data = collect(DB::select($sql));
        
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
