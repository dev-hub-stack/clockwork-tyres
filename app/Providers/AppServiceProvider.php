<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Filament\Navigation\NavigationItem;
use Filament\Facades\Filament;
use App\Modules\Inventory\Models\Warehouse;
use App\Observers\WarehouseObserver;
use App\Modules\Products\Models\ProductVariant;
use App\Observers\ProductVariantInventoryObserver;
use App\Models\Addon;
use App\Observers\AddonInventoryObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register observers for auto-inventory creation
        Warehouse::observe(WarehouseObserver::class);
        ProductVariant::observe(ProductVariantInventoryObserver::class);
        Addon::observe(AddonInventoryObserver::class);

        // Add custom navigation item for Products Grid
        Filament::serving(function () {
            Filament::registerNavigationItems([
                NavigationItem::make('Products Grid')
                    ->url('/admin/products-grid')
                    ->icon('heroicon-o-table-cells')
                    ->group('Products')
                    ->sort(3),
            ]);
        });
    }
}
