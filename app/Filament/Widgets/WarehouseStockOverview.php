<?php

namespace App\Filament\Widgets;

use App\Modules\Accounts\Support\CurrentAccountResolver;
use App\Modules\Inventory\Models\ProductInventory;
use App\Modules\Inventory\Models\Warehouse;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class WarehouseStockOverview extends BaseWidget
{
    protected static ?int $sort = 2;
    
    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $currentAccountId = auth()->check() && request()
            ? app(CurrentAccountResolver::class)->resolve(request(), auth()->user())->currentAccount?->id
            : null;

        if (! $currentAccountId) {
            return [];
        }

        $warehouses = Warehouse::query()
            ->where('account_id', $currentAccountId)
            ->with(['inventories' => function ($query) {
                $query->where('quantity', '>', 0);
            }])
            ->get();
        
        $stats = [];
        
        foreach ($warehouses as $warehouse) {
            // Calculate total items, total quantity, and low stock count
            $totalItems = $warehouse->inventories->count();
            $totalQuantity = $warehouse->inventories->sum('quantity');
            $lowStockCount = $warehouse->inventories->where('quantity', '<=', 10)->count();
            
            // Get low stock percentage
            $lowStockPercentage = $totalItems > 0 ? round(($lowStockCount / $totalItems) * 100) : 0;
            
            // Determine color based on low stock percentage
            $color = match(true) {
                $lowStockPercentage >= 50 => 'danger',
                $lowStockPercentage >= 25 => 'warning',
                default => 'success',
            };
            
            $stats[] = Stat::make($warehouse->warehouse_name, number_format($totalQuantity) . ' units')
                ->description($lowStockCount > 0 
                    ? "{$totalItems} products • {$lowStockCount} low stock ⚠️" 
                    : "{$totalItems} unique products")
                ->descriptionIcon('heroicon-m-cube')
                ->chart($this->getWarehouseChart($warehouse->id))
                ->color($color)
                ->extraAttributes([
                    'class' => 'cursor-pointer',
                ]);
        }
        
        // Add overall stats
        $scopedInventories = ProductInventory::query()
            ->whereHas('warehouse', fn ($query) => $query->where('account_id', $currentAccountId));

        $totalProducts = (clone $scopedInventories)->where('quantity', '>', 0)->count();
        $totalStock = (clone $scopedInventories)->sum('quantity');
        $totalLowStock = (clone $scopedInventories)->where('quantity', '<=', 10)->where('quantity', '>', 0)->count();
        
        array_unshift($stats, Stat::make('Total Stock', number_format($totalStock) . ' units')
            ->description($totalLowStock > 0 
                ? "{$totalProducts} products • {$totalLowStock} low stock ⚠️" 
                : "{$totalProducts} products in stock ✓")
            ->descriptionIcon('heroicon-m-cube-transparent')
            ->chart($this->getOverallChart())
            ->color($totalLowStock > 50 ? 'danger' : ($totalLowStock > 20 ? 'warning' : 'success')));
        
        return $stats;
    }
    
    protected function getWarehouseChart(int $warehouseId): array
    {
        // Get last 7 days of inventory changes (simplified - you may want to track this properly)
        // For now, return dummy data - you'd want to implement inventory history tracking
        return [65, 59, 80, 81, 56, 55, 40];
    }
    
    protected function getOverallChart(): array
    {
        // Get overall inventory trends
        return [100, 98, 105, 110, 95, 100, 108];
    }
}
