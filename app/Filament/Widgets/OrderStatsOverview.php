<?php

namespace App\Filament\Widgets;

use App\Modules\Orders\Models\Order;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class OrderStatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;
    
    protected function getStats(): array
    {
        // Pending orders (pending, processing status)
        $pendingOrders = Order::whereIn('order_status', ['pending', 'processing'])->count();
        
        // Monthly revenue from completed orders
        $monthlyRevenue = Order::where('order_status', 'completed')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('total_amount');
        
        // Today's orders
        $todayOrders = Order::whereDate('created_at', today())->count();
        
        // Notifications: low stock + pending warranty claims + overdue invoices
        $lowStockCount = DB::table('product_inventories')
            ->where('available_quantity', '<', 5)
            ->count();
        
        $pendingWarranty = DB::table('warranty_claims')
            ->where('status', 'submitted')
            ->count();
        
        $overdueInvoices = DB::table('invoices')
            ->where('due_date', '<', now())
            ->where('payment_status', '!=', 'paid')
            ->count();
        
        $notifications = $lowStockCount + $pendingWarranty + $overdueInvoices;
        
        return [
            Stat::make('Pending Orders', $pendingOrders)
                ->description('Orders awaiting processing')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('primary')
                ->chart([7, 3, 4, 5, 6, 3, 5, 3]),
                
            Stat::make('Monthly Revenue', '$' . number_format($monthlyRevenue, 2))
                ->description(now()->format('F Y'))
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('success')
                ->chart([7, 2, 10, 3, 15, 4, 17]),
                
            Stat::make("Today's Orders", $todayOrders)
                ->description('Orders placed today')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('warning')
                ->chart([3, 3, 2, 5, 6, 4, 3]),
                
            Stat::make('Notifications', $notifications)
                ->description("{$lowStockCount} Low Stock, {$pendingWarranty} Warranty, {$overdueInvoices} Overdue")
                ->descriptionIcon('heroicon-m-bell-alert')
                ->color('danger')
                ->chart([2, 4, 3, 7, 5, 9, 8]),
        ];
    }
}
