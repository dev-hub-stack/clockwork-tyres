<?php

namespace App\Filament\Widgets;

use App\Modules\Orders\Models\Order;
use App\Modules\Customers\Models\Customer;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class SalesOverviewStats extends BaseWidget
{
    protected static ?int $sort = 1;
    
    protected function getStats(): array
    {
        // Get date range for filtering (default: all time)
        $startDate = now()->subYear(); // Last 12 months
        $endDate = now();
        
        // Total Revenue
        $totalRevenue = Order::where('external_source', 'tunerstop_historical')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('total');
        
        // Previous period for comparison
        $previousStart = $startDate->copy()->subYear();
        $previousEnd = $startDate->copy();
        
        $previousRevenue = Order::where('external_source', 'tunerstop_historical')
            ->whereBetween('created_at', [$previousStart, $previousEnd])
            ->sum('total');
        
        $revenueChange = $previousRevenue > 0 
            ? (($totalRevenue - $previousRevenue) / $previousRevenue) * 100 
            : 0;
        
        // Total Orders
        $totalOrders = Order::where('external_source', 'tunerstop_historical')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();
        
        $previousOrders = Order::where('external_source', 'tunerstop_historical')
            ->whereBetween('created_at', [$previousStart, $previousEnd])
            ->count();
        
        $ordersChange = $previousOrders > 0 
            ? (($totalOrders - $previousOrders) / $previousOrders) * 100 
            : 0;
        
        // Average Order Value
        $avgOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;
        
        $previousAvgOrderValue = $previousOrders > 0 ? $previousRevenue / $previousOrders : 0;
        
        $avgChange = $previousAvgOrderValue > 0 
            ? (($avgOrderValue - $previousAvgOrderValue) / $previousAvgOrderValue) * 100 
            : 0;
        
        // Total Customers (All Time)
        $totalCustomers = Customer::where('customer_type', 'retail')->count();
        
        // Customers who ordered in period
        $activeCustomers = Order::where('external_source', 'tunerstop_historical')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->distinct('customer_id')
            ->count('customer_id');
        
        return [
            Stat::make('Total Revenue (Last 12 Months)', 'AED ' . number_format($totalRevenue, 2))
                ->description($revenueChange > 0 ? "+" . number_format($revenueChange, 2) . "% from previous year" : number_format($revenueChange, 2) . "% from previous year")
                ->descriptionIcon($revenueChange > 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($revenueChange > 0 ? 'success' : 'danger')
                ->chart($this->getRevenueChartData($startDate, $endDate)),
            
            Stat::make('Total Orders (Last 12 Months)', number_format($totalOrders))
                ->description($ordersChange > 0 ? "+" . number_format($ordersChange, 2) . "% from previous year" : number_format($ordersChange, 2) . "% from previous year")
                ->descriptionIcon($ordersChange > 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($ordersChange > 0 ? 'success' : 'danger')
                ->chart($this->getOrdersChartData($startDate, $endDate)),
            
            Stat::make('Average Order Value', 'AED ' . number_format($avgOrderValue, 2))
                ->description($avgChange > 0 ? "+" . number_format($avgChange, 2) . "% from previous year" : number_format($avgChange, 2) . "% from previous year")
                ->descriptionIcon($avgChange > 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($avgChange > 0 ? 'success' : 'danger'),
            
            Stat::make('Active Customers', number_format($activeCustomers))
                ->description("Out of {$totalCustomers} total customers")
                ->descriptionIcon('heroicon-m-users')
                ->color('info'),
        ];
    }
    
    /**
     * Get revenue trend data for sparkline chart
     */
    protected function getRevenueChartData($startDate, $endDate): array
    {
        $data = Order::where('external_source', 'tunerstop_historical')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                DB::raw('SUM(total) as revenue')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->pluck('revenue')
            ->toArray();
        
        return $data;
    }
    
    /**
     * Get orders count data for sparkline chart
     */
    protected function getOrdersChartData($startDate, $endDate): array
    {
        $data = Order::where('external_source', 'tunerstop_historical')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                DB::raw('COUNT(*) as orders')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->pluck('orders')
            ->toArray();
        
        return $data;
    }
}
